<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\PartnerService;
use App\Services\Orders\DashboardFilters;
use App\Services\Orders\OrderStats;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, OrderStats $stats): View|RedirectResponse
    {
        $services = PartnerService::query()
            ->with('country')
            ->get()
            ->sortBy(
                fn (PartnerService $service) => mb_strtolower(($service->country->name ?? '').' '.$service->name),
                SORT_NATURAL,
            )
            ->values();

        $filters = DashboardFilters::fromRequest($request, $services);

        if ($filters->differsFromRequest($request)) {
            return redirect()->route('dashboard', $filters->canonicalQuery());
        }

        $summary = $stats->summarize($filters->orderQuery());

        $countries = Country::query()->orderBy('name')->get();

        if ($filters->service !== null) {
            $countries = $countries->where('id', $filters->service->country_id)->values();
        }

        $counts = $filters->countsByCountry();

        $countries->each(function (Country $country) use ($counts): void {
            $row = $counts->get($country->id);
            $country->setAttribute('orders_count', (int) ($row->orders_count ?? 0));
            $country->setAttribute('pending_orders_count', (int) ($row->pending_orders_count ?? 0));
            $country->setAttribute('transferred_orders_count', (int) ($row->transferred_orders_count ?? 0));
        });

        return view('dashboard.index', [
            'summary' => $summary,
            'countries' => $countries,
            // The period filters order stats, charts, tables and syncs. Country and service totals follow the selected service only.
            'countryCount' => $countries->count(),
            'serviceCount' => $filters->service !== null ? 1 : $services->count(),
            'recentOrders' => $filters->orderQuery()
                ->whereHas('service.country')
                ->with('service.country')
                ->latest('received_at')
                ->limit(8)
                ->get(),
            'recentSyncs' => $filters->syncQuery()
                ->with('service.country')
                ->latest('started_at')
                ->limit(6)
                ->get(),
            'hasDemo' => $filters->orderQuery()->where('is_demo', true)->exists(),
            'ordersByCountry' => [
                'labels' => $countries->pluck('name')->all(),
                'values' => $countries->pluck('orders_count')->map(fn ($count) => (int) $count)->all(),
            ],
            'statusChart' => [
                'labels' => ['En attente', 'Validées', 'Transfert en attente', 'Transférées', 'Rejetées', 'Échec du transfert'],
                'values' => [
                    $summary['pending'],
                    $summary['validated'],
                    $summary['transfer_pending'],
                    $summary['transferred'],
                    $summary['rejected'],
                    $summary['failed'],
                ],
            ],
            'services' => $services,
            'filters' => $filters,
        ]);
    }
}
