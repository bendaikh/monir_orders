<?php

namespace App\Http\Controllers;

use App\Enums\OrderDisplayStatus;
use App\Enums\ValidationStatus;
use App\Http\Requests\BulkValidationRequest;
use App\Http\Requests\RejectOrderRequest;
use App\Jobs\TransferOrderJob;
use Illuminate\Support\Facades\Bus;
use App\Models\Country;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Services\Sheets\OrderImporter;
use App\Services\Sheets\OrderTransferrer;
use App\Services\Sheets\SourceRowMarker;
use App\Models\PartnerService;
use App\Services\Orders\OrderFilters;
use App\Services\Orders\OrderStats;
use App\Services\Orders\OrderWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class ValidationController extends Controller
{
    public function index(Request $request, Country $country, OrderFilters $filters, OrderStats $stats): View
    {
        $base = Order::query()->whereHas('service', fn ($query) => $query->where('country_id', $country->id));

        if (! $request->exists('status')) {
            $request->merge(['status' => OrderDisplayStatus::PendingValidation->value]);
        }

        $orders = $filters->apply(clone $base, $request)
            ->with('service')
            ->latest('received_at')
            ->paginate($request->user()->rows_per_page)
            ->withQueryString();

        return view('validation.index', [
            'country' => $country,
            'orders' => $orders,
            'summary' => $stats->summarize($base),
            'services' => $country->services()->orderBy('name')->get(),
            'cities' => (clone $base)->whereNotNull('city')->distinct()->orderBy('city')->pluck('city'),
            'hasDemo' => (clone $base)->where('is_demo', true)->exists(),
        ]);
    }

    public function validateOrder(Country $country, Order $order): RedirectResponse
    {
        try {
            $updated = Bus::dispatchNow(new TransferOrderJob($order->id, request()->user()->id, true));
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with(
            $updated->displayStatus() === OrderDisplayStatus::Transferred ? 'success' : 'error',
            $this->outcomeMessage($updated, true),
        );
    }

    public function reject(RejectOrderRequest $request, Country $country, Order $order, OrderWorkflow $workflow): RedirectResponse
    {
        try {
            $workflow->reject($order, $request->user(), $request->validated('reason'));
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'La commande '.$order->reference.' a été rejetée. Elle n\'a pas été envoyée.');
    }

    public function bulk(BulkValidationRequest $request, Country $country): RedirectResponse
    {
        $orders = Order::query()
            ->whereIn('id', $request->validated('orders'))
            ->whereHas('service', fn ($query) => $query->where('country_id', $country->id))
            ->get();

        $transferred = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($request->validated('orders') as $id) {
            $order = $orders->firstWhere('id', (int) $id);

            if ($order === null || $order->validation_status !== ValidationStatus::Pending) {
                $skipped++;

                continue;
            }

            try {
                $updated = Bus::dispatchNow(new TransferOrderJob($order->id, $request->user()->id, true));
                $updated->displayStatus() === OrderDisplayStatus::Transferred ? $transferred++ : $failed++;
            } catch (Throwable) {
                $failed++;
            }
        }

        $message = $transferred.' transfert(s) confirmé(s), '.$failed.' échec(s), '.$skipped.' ignorée(s). Les validations déjà enregistrées sont conservées.';

        return back()->with($failed > 0 ? 'warning' : 'success', $message);
    }

    public function validateAll(Country $country, PartnerService $service, OrderTransferrer $transferrer, OrderImporter $importer): RedirectResponse
    {
        $lock = Cache::lock('service-validate-all-'.$service->id, 900);

        if (! $lock->get()) {
            return back()->with('error', 'Une validation globale est déjà en cours pour ce service.');
        }

        try {
            @set_time_limit(0);
            $user = request()->user();
            $today = now()->startOfDay();
            $lastId = (int) Order::query()->max('id');

            // Pull today's rows straight from the source sheet. Rows already read are skipped
            // (source fingerprint) and repeats (phone + product + day, incl. already-pushed orders)
            // are marked « Doublon » and never sent.
            $log = $importer->import($service, $user, 'validate-all', $today);
            Cache::forget(ServiceController::todayCountCacheKey($service));

            if ($log->result === 'failed') {
                return back()->with('error', 'Lecture de la feuille source impossible : '.$log->message);
            }

            $importDuplicates = $service->orders()
                ->where('id', '>', $lastId)
                ->where('validation_status', ValidationStatus::Duplicate->value)
                ->count();

            $pending = $service->orders()
                ->where('validation_status', ValidationStatus::Pending->value)
                ->whereBetween('ordered_at', [$today, $today->copy()->endOfDay()])
                ->orderBy('id')
                ->get();

            if ($pending->isEmpty()) {
                return back()->with('success', sprintf(
                    'Aucune nouvelle commande du jour à envoyer pour %s (%d doublon(s) supprimé(s)).',
                    $service->name,
                    $importDuplicates,
                ));
            }

            foreach ($pending as $order) {
                DB::transaction(function () use ($order, $user) {
                    $order->update([
                        'validation_status' => ValidationStatus::Validated,
                        'validated_at' => now(),
                        'validated_by' => $user->id,
                        'rejection_reason' => null,
                        'rejected_at' => null,
                    ]);

                    $order->validationLogs()->create([
                        'user_id' => $user->id,
                        'decision' => 'validated',
                    ]);
                });
            }

            $stats = $transferrer->transferBatch($service, $pending, $user);
            $marked = app(SourceRowMarker::class)->markLatest($service->fresh('sourceIntegration'), $pending->map->fresh());
        } finally {
            $lock->release();
        }

        $message = sprintf(
            '%d commande(s) du jour validée(s) : %d transférée(s), %d doublon(s) supprimé(s) (non envoyés), %d échec(s).',
            $pending->count(),
            $stats['transferred'],
            $importDuplicates + $stats['duplicates'],
            $stats['failed'],
        ).($marked ? ' Dernière commande envoyée marquée en vert (ligne '.$marked.' de la feuille source).' : '');

        return back()->with($stats['failed'] > 0 ? 'warning' : 'success', $message);
    }

    public function retry(Country $country, Order $order): RedirectResponse
    {
        try {
            $updated = Bus::dispatchNow(new TransferOrderJob($order->id, request()->user()->id, false));
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with(
            $updated->displayStatus() === OrderDisplayStatus::Transferred ? 'success' : 'error',
            $this->outcomeMessage($updated, false),
        );
    }

    private function outcomeMessage(Order $order, bool $validatedNow): string
    {
        $status = $order->displayStatus();

        if ($status === OrderDisplayStatus::Transferred) {
            return 'La commande '.$order->reference.' a été transférée vers la feuille de destination.';
        }

        $prefix = $validatedNow
            ? 'La validation de '.$order->reference.' est enregistrée, mais le transfert a échoué.'
            : 'Le nouvel envoi de '.$order->reference.' a échoué.';

        $detail = $order->transferLogs()->latest()->value('message');

        return trim($prefix.' '.($detail ?: 'Vous pouvez réessayer le transfert.'));
    }
}
