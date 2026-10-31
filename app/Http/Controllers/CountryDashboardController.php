<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\Order;
use App\Models\SyncLog;
use App\Services\Orders\OrderStats;
use Illuminate\View\View;

class CountryDashboardController extends Controller
{
    public function index(Country $country, OrderStats $stats): View
    {
        $orders = Order::query()->whereHas('service', fn ($query) => $query->where('country_id', $country->id));

        return view('countries.dashboard', [
            'country' => $country,
            'summary' => $stats->summarize($orders),
            'activeServices' => $country->services()->where('is_active', true)->count(),
            'recentOrders' => (clone $orders)->with('service')->latest('received_at')->limit(8)->get(),
            'recentSyncs' => SyncLog::query()
                ->whereHas('service', fn ($query) => $query->where('country_id', $country->id))
                ->with('service')
                ->latest('started_at')
                ->limit(6)
                ->get(),
            'hasDemo' => (clone $orders)->where('is_demo', true)->exists(),
        ]);
    }
}
