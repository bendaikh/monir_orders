<?php

namespace App\Http\Controllers;

use App\Enums\ValidationStatus;
use App\Http\Requests\ServiceRequest;
use App\Models\Country;
use App\Models\PartnerService;
use App\Models\PushRun;
use App\Services\Sheets\PartnerServiceProvisioner;
use App\Services\Sheets\SheetConfigurator;
use App\Support\OrderFields;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceController extends Controller
{
    private const SETTINGS = [
        'name', 'description', 'is_active', 'duplicate_key', 'phone_digits',
        'date_format', 'decimal_format', 'exclude_products', 'reference_prefix', 'destination_type',
    ];

    public function index(Request $request, Country $country): View
    {
        $services = $country->services()
            ->with(['sourceIntegration', 'destinationIntegration'])
            ->withCount('orders')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = str_replace(['%', '_'], '', trim($request->string('q')->toString()));
                $query->where('name', 'like', '%'.$term.'%');
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('is_active', $request->string('status')->toString() === 'active');
            })
            ->orderBy('name')
            ->paginate($request->user()->rows_per_page)
            ->withQueryString();

        $liveCounts = [];

        foreach ($services as $service) {
            $liveCounts[$service->id] = $service->sourceIntegration?->isReady()
                ? self::liveTodayCounts($service)
                : [null, 'Source non configurée'];
        }

        $activeRuns = [];

        foreach ($services as $service) {
            $run = PushRun::query()->where('service_id', $service->id)->latest('id')->first();
            $activeRuns[$service->id] = $run && $run->isActive() ? $run->toPayload() : null;
        }

        return view('services.index', compact('country', 'services', 'liveCounts', 'activeRuns'));
    }

    public function create(Country $country): View
    {
        return view('services.form', [
            'country' => $country,
            'service' => new PartnerService(['country_id' => $country->id, 'is_active' => true]),
            'fields' => OrderFields::definitions(),
            'sourceMap' => OrderFields::defaultSource(),
            'destinationMap' => OrderFields::defaultDestination(),
            'source' => null,
            'destination' => null,
        ]);
    }

    public function store(ServiceRequest $request, Country $country, PartnerServiceProvisioner $provisioner, SheetConfigurator $configurator): RedirectResponse
    {
        $service = $country->services()->create($request->safe()->only(self::SETTINGS));
        $provisioner->provision($service);
        $configurator->save($service, $request->validated());

        return redirect()
            ->route('countries.services.edit', [$country, $service])
            ->with('success', 'Le service '.$service->name.' a été créé.');
    }

    public function show(Country $country, PartnerService $service): View
    {
        $service->load(['sourceIntegration', 'destinationIntegration', 'columnMappings'])->loadCount('orders');
        [$todayCount, $todayError] = self::liveTodayCount($service);

        return view('services.show', [
            'country' => $country,
            'service' => $service,
            'fields' => OrderFields::definitions(),
            'pendingCount' => $service->orders()->where('validation_status', ValidationStatus::Pending->value)->count(),
            'todayCount' => $todayCount,
            'todayError' => $todayError,
            'duplicateCount' => $service->orders()->where('validation_status', ValidationStatus::Duplicate->value)->count(),
            'lastRun' => \App\Models\PushRun::query()->where('service_id', $service->id)->latest('id')->first(),
        ]);
    }

    public function edit(Country $country, PartnerService $service): View
    {
        $service->load(['sourceIntegration', 'destinationIntegration', 'columnMappings']);

        return view('services.form', [
            'country' => $country,
            'service' => $service,
            'fields' => OrderFields::definitions(),
            'sourceMap' => $service->mappingFor('source'),
            'destinationMap' => $service->mappingFor('destination'),
            'source' => $service->sourceIntegration,
            'destination' => $service->destinationIntegration,
        ]);
    }

    public function update(ServiceRequest $request, Country $country, PartnerService $service, SheetConfigurator $configurator): RedirectResponse
    {
        $service->update($request->safe()->only(self::SETTINGS));
        $configurator->save($service, $request->validated());

        return redirect()
            ->route('countries.services.edit', [$country, $service])
            ->with('success', 'La configuration de '.$service->name.' a été enregistrée.');
    }

    public function toggle(Country $country, PartnerService $service): RedirectResponse
    {
        $service->update(['is_active' => ! $service->is_active]);

        return back()->with('success', $service->is_active
            ? $service->name.' est maintenant actif.'
            : $service->name.' est maintenant inactif.');
    }

    /**
     * Orders waiting to be pushed (any day since the service was added) counted live from the source sheet, cached ~60 s.
     *
     * @return array{0: int|null, 1: string|null}
     */
    public static function liveTodayCount(PartnerService $service): array
    {
        [$counts, $error] = self::liveTodayCounts($service);

        return [$counts['pending'] ?? null, $error];
    }

    /**
     * @return array{0: array{today: int, pending: int}|null, 1: ?string} cached 60 s, one sheet read for both numbers
     */
    public static function liveTodayCounts(PartnerService $service): array
    {
        $today = $service->pushSince();

        try {
            $counts = \Illuminate\Support\Facades\Cache::remember(
                self::todayCountCacheKey($service),
                60,
                fn () => app(\App\Services\Sheets\OrderImporter::class)->countsForDate($service, $today),
            );

            if (! is_array($counts)) { // value cached by the previous version (int)
                \Illuminate\Support\Facades\Cache::forget(self::todayCountCacheKey($service));

                return self::liveTodayCounts($service);
            }

            return [$counts, null];
        } catch (\Throwable $exception) {
            report($exception);

            return [null, $exception instanceof \App\Services\Sheets\GoogleSheetsException
                ? $exception->getMessage()
                : 'Impossible de lire la feuille source pour le moment.'];
        }
    }

    public static function todayCountCacheKey(PartnerService $service): string
    {
        return 'service-waiting-count-'.$service->id;
    }
}
