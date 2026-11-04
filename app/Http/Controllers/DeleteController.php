<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\Order;
use App\Models\PartnerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/** Soft deletes (recoverable): rows get deleted_at, nothing is removed from the database. */
class DeleteController extends Controller
{
    public function country(Country $country): RedirectResponse
    {
        if (($r = Gate::inspect('delete', $country))->denied()) {
            return back()->with('error', $r->message());
        }

        $country->delete();

        return redirect()->route('countries.index')->with('success', 'Le pays '.$country->name.' a été supprimé.');
    }

    public function service(Country $country, PartnerService $service): RedirectResponse
    {
        if (($r = Gate::inspect('delete', $service))->denied()) {
            return back()->with('error', $r->message());
        }

        $service->delete();

        return redirect()->route('countries.services.index', $country)->with('success', 'Le service '.$service->name.' a été supprimé.');
    }

    public function order(Country $country, Order $order): RedirectResponse
    {
        if (($r = Gate::inspect('delete', $order))->denied()) {
            return back()->with('error', $r->message());
        }

        $order->delete();

        return back()->with('success', 'La commande '.$order->reference.' a été supprimée.');
    }
}
