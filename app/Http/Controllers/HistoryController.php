<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\Order;
use App\Models\SyncLog;
use App\Services\Orders\OrderFilters;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HistoryController extends Controller
{
    public function index(Request $request, Country $country, OrderFilters $filters): View
    {
        $tab = $request->string('tab')->toString() === 'sync' ? 'sync' : 'orders';

        $orders = $filters->apply(
            Order::query()->whereHas('service', fn ($query) => $query->where('country_id', $country->id)),
            $request,
        )
            ->with(['service', 'transferLogs' => fn ($query) => $query->latest()])
            ->latest('received_at')
            ->paginate($request->user()->rows_per_page)
            ->withQueryString();

        $syncs = SyncLog::query()
            ->whereHas('service', fn ($query) => $query->where('country_id', $country->id))
            ->with('service')
            ->when($request->filled('service_id'), fn ($query) => $query->where('service_id', $request->integer('service_id')))
            ->when($request->filled('sync_result'), fn ($query) => $query->where('result', $request->string('sync_result')->toString()))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('started_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('started_at', '<=', $request->date('date_to')))
            ->latest('started_at')
            ->paginate($request->user()->rows_per_page)
            ->withQueryString();

        return view('history.index', [
            'country' => $country,
            'tab' => $tab,
            'orders' => $orders,
            'syncs' => $syncs,
            'services' => $country->services()->orderBy('name')->get(),
            'cities' => Order::query()
                ->whereHas('service', fn ($query) => $query->where('country_id', $country->id))
                ->whereNotNull('city')
                ->distinct()
                ->orderBy('city')
                ->pluck('city'),
        ]);
    }
}
