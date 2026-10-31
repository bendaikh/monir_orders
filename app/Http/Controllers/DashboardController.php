<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\Order;
use App\Models\PartnerService;
use App\Models\SyncLog;
use App\Services\Orders\OrderStats;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(OrderStats $stats): View
    {
        $summary = $stats->summarize(Order::query());

        $countries = Country::query()
            ->withCount([
                'services',
                'orders',
                'orders as pending_orders_count' => fn ($query) => $query->where('validation_status', 'pending'),
                'orders as transferred_orders_count' => fn ($query) => $query->where('transfer_status', 'transferred'),
                'orders as rejected_orders_count' => fn ($query) => $query->where('validation_status', 'rejected'),
            ])
            ->orderBy('name')
            ->get();

        return view('dashboard.index', [
            'summary' => $summary,
            'countries' => $countries,
            'countryCount' => $countries->count(),
            'serviceCount' => PartnerService::query()->count(),
            'recentOrders' => Order::query()->with('service.country')->latest('received_at')->limit(8)->get(),
            'recentSyncs' => SyncLog::query()->with('service.country')->latest('started_at')->limit(6)->get(),
            'hasDemo' => Order::query()->where('is_demo', true)->exists(),
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
        ]);
    }
}
