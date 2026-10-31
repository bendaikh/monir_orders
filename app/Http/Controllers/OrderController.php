<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrderUpdateRequest;
use App\Models\Country;
use App\Models\Order;
use App\Services\Orders\OrderFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request, Country $country, OrderFilters $filters): View
    {
        $orders = $filters->apply(
            Order::query()->whereHas('service', fn ($query) => $query->where('country_id', $country->id)),
            $request,
        )
            ->with('service.country')
            ->latest('received_at')
            ->paginate($request->user()->rows_per_page)
            ->withQueryString();

        return view('orders.index', [
            'country' => $country,
            'orders' => $orders,
            'services' => $country->services()->orderBy('name')->get(),
            'cities' => Order::query()
                ->whereHas('service', fn ($query) => $query->where('country_id', $country->id))
                ->whereNotNull('city')
                ->distinct()
                ->orderBy('city')
                ->pluck('city'),
            'hasDemo' => Order::query()
                ->where('is_demo', true)
                ->whereHas('service', fn ($query) => $query->where('country_id', $country->id))
                ->exists(),
        ]);
    }

    public function global(Request $request, OrderFilters $filters): View
    {
        $orders = $filters->apply(Order::query(), $request)
            ->with('service.country')
            ->latest('received_at')
            ->paginate($request->user()->rows_per_page)
            ->withQueryString();

        return view('orders.index', [
            'country' => null,
            'orders' => $orders,
            'services' => \App\Models\PartnerService::query()->with('country')->orderBy('name')->get(),
            'countries' => Country::query()->orderBy('name')->get(),
            'cities' => Order::query()->whereNotNull('city')->distinct()->orderBy('city')->pluck('city'),
            'hasDemo' => Order::query()->where('is_demo', true)->exists(),
        ]);
    }

    public function show(Country $country, Order $order): View
    {
        $order->load([
            'service.country',
            'service.sourceIntegration',
            'service.destinationIntegration',
            'items',
            'validationLogs.user',
            'transferLogs.user',
            'decidedBy',
        ]);

        return view('orders.show', compact('country', 'order'));
    }

    public function update(OrderUpdateRequest $request, Country $country, Order $order): RedirectResponse
    {
        if (! $order->canEdit()) {
            return back()->with('error', 'Cette commande ne peut plus être modifiée.');
        }

        $order->update($request->validated());

        return redirect()
            ->route('countries.orders.show', [$country, $order])
            ->with('success', 'La commande '.$order->reference.' a été mise à jour.');
    }
}
