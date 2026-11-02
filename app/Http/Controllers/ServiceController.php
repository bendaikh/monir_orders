<?php

namespace App\Http\Controllers;

use App\Enums\ValidationStatus;
use App\Http\Requests\ServiceRequest;
use App\Models\Country;
use App\Models\PartnerService;
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

        return view('services.index', compact('country', 'services'));
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
     * Today's orders (Africa/Casablanca) counted live from the source sheet, cached ~60 s.
     *
     * @return array{0: int|null, 1: string|null}
     */
    public static function liveTodayCount(PartnerService $service): array
    {
        $today = now()->startOfDay();

        try {
            $count = \Illuminate\Support\Facades\Cache::remember(
                self::todayCountCacheKey($service),
                60,
                fn () => app(\App\Services\Sheets\OrderImporter::class)->countForDate($service, $today),
            );

            return [(int) $count, null];
        } catch (\Throwable $exception) {
            report($exception);

            return [null, $exception instanceof \App\Services\Sheets\GoogleSheetsException
                ? $exception->getMessage()
                : 'Impossible de lire la feuille source pour le moment.'];
        }
    }

    public static function todayCountCacheKey(PartnerService $service): string
    {
        return 'service-today-count-'.$service->id.'-'.now()->format('Y-m-d');
    }
}
