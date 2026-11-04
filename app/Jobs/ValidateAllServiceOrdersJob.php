<?php

namespace App\Jobs;

use App\Enums\TransferStatus;
use App\Enums\ValidationStatus;
use App\Http\Controllers\ServiceController;
use App\Models\Order;
use App\Models\PartnerService;
use App\Models\PushRun;
use App\Models\User;
use App\Services\Api\ApiOrderSender;
use App\Services\Sheets\OrderImporter;
use App\Services\Sheets\OrderTransferrer;
use App\Services\Sheets\SourceRowMarker;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * « Valider et push » for one service, run by the queue worker so the HTTP request never times out (504).
 * Logic is the same as the former synchronous ValidationController::validateAll.
 */
class ValidateAllServiceOrdersJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public bool $failOnTimeout = true;

    /** Unique lock lifetime (seconds): one push per service at a time. */
    public int $uniqueFor = 1800;

    public function __construct(
        public int $serviceId,
        public int $userId,
        public int $runId,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->serviceId;
    }

    public function handle(OrderTransferrer $transferrer, OrderImporter $importer): void
    {
        $run = PushRun::query()->find($this->runId);
        $service = PartnerService::query()->find($this->serviceId);
        $user = User::query()->find($this->userId);

        if ($run === null || $service === null || $user === null) {
            $run?->update(['status' => PushRun::FAILED, 'tone' => 'error', 'error' => 'Service ou utilisateur introuvable.', 'message' => 'Service ou utilisateur introuvable.', 'finished_at' => now()]);

            return;
        }

        $lock = Cache::lock('service-validate-all-'.$service->id, 900);

        if (! $lock->get()) {
            $this->finish($run, 'error', 'Une validation globale est déjà en cours pour ce service.', failed: true);

            return;
        }

        $run->update(['status' => PushRun::RUNNING, 'started_at' => now()]);

        try {
            @set_time_limit(0);
            $today = now()->startOfDay();
            $lastId = (int) Order::query()->max('id');

            $log = $importer->import($service, $user, 'validate-all', $today);
            Cache::forget(ServiceController::todayCountCacheKey($service));

            if ($log->result === 'failed') {
                $this->finish($run, 'error', 'Lecture de la feuille source impossible : '.$log->message, failed: true);

                return;
            }

            $importDuplicates = $service->orders()
                ->where('id', '>', $lastId)
                ->where('validation_status', ValidationStatus::Duplicate->value)
                ->count();

            $pending = $service->orders()
                ->whereBetween('ordered_at', [$today, $today->copy()->endOfDay()])
                ->where(function ($query) use ($service) {
                    $query->where('validation_status', ValidationStatus::Pending->value);

                    if ($service->usesApi()) {
                        $query->orWhere(fn ($q) => $q->where('validation_status', ValidationStatus::Validated->value)
                            ->where('transfer_status', TransferStatus::Failed->value)
                            ->whereNull('parcel_code'));
                    }
                })
                ->orderBy('id')
                ->get();

            if ($pending->isEmpty()) {
                $run->duplicates = $importDuplicates;
                $this->finish($run, 'success', sprintf(
                    'Aucune nouvelle commande du jour à envoyer pour %s (%d doublon(s) supprimé(s)).',
                    $service->name,
                    $importDuplicates,
                ));

                return;
            }

            foreach ($pending as $order) {
                if ($order->validation_status !== ValidationStatus::Pending) {
                    continue;
                }

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

            $stats = $service->usesApi()
                ? app(ApiOrderSender::class)->transferBatch($service, $pending, $user)
                : $transferrer->transferBatch($service, $pending, $user);
            $marked = app(SourceRowMarker::class)->markLatest($service->fresh('sourceIntegration'), $pending->map->fresh());

            $message = sprintf(
                '%d commande(s) du jour validée(s) : %d transférée(s), %d doublon(s) supprimé(s) (non envoyés), %d échec(s).',
                $pending->count(),
                $stats['transferred'],
                $importDuplicates + $stats['duplicates'],
                $stats['failed'],
            ).($marked ? ' Dernière commande envoyée marquée en vert (ligne '.$marked.' de la feuille source).' : '');

            $run->fill([
                'total' => $pending->count(),
                'transferred' => $stats['transferred'],
                'duplicates' => $importDuplicates + $stats['duplicates'],
                'failed' => $stats['failed'],
                'marked_row' => $marked ?: null,
            ]);
            $this->finish($run, $stats['failed'] > 0 ? 'warning' : 'success', $message);
        } catch (Throwable $e) {
            $this->finish($run, 'error', 'Erreur pendant l\'envoi : '.$e->getMessage(), failed: true);

            throw $e;
        } finally {
            $lock->release();
        }
    }

    /** Called by the queue when the job is killed (timeout) or throws. */
    public function failed(?Throwable $e): void
    {
        $run = PushRun::query()->find($this->runId);

        if ($run && $run->status !== PushRun::FAILED) {
            $this->finish($run, 'error', 'Échec de l\'envoi : '.($e?->getMessage() ?: 'arrêt inattendu'), failed: true);
        }
    }

    private function finish(PushRun $run, string $tone, string $message, bool $failed = false): void
    {
        $run->fill([
            'status' => $failed ? PushRun::FAILED : PushRun::DONE,
            'tone' => $tone,
            'message' => $message,
            'error' => $failed ? $message : null,
            'finished_at' => now(),
        ])->save();
    }
}
