<?php

namespace App\Http\Controllers;

use App\Http\Requests\CountryRequest;
use App\Models\Country;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CountryController extends Controller
{
    public function index(Request $request): View
    {
        $countries = Country::query()
            ->withCount(['services', 'orders'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = str_replace(['%', '_'], '', trim($request->string('q')->toString()));
                $query->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', '%'.$term.'%')
                        ->orWhere('code', 'like', '%'.strtoupper($term).'%');
                });
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('is_active', $request->string('status')->toString() === 'active');
            })
            ->orderBy('name')
            ->paginate($request->user()->rows_per_page)
            ->withQueryString();

        return view('countries.index', [
            'countries' => $countries,
            'defaultCurrency' => Setting::current()->default_currency ?: 'MAD',
        ]);
    }

    public function store(CountryRequest $request): RedirectResponse
    {
        $country = Country::query()->create($request->validated());

        return redirect()
            ->route('countries.index')
            ->with('success', 'Le pays '.$country->name.' a été ajouté.');
    }

    public function update(CountryRequest $request, Country $country): RedirectResponse
    {
        $country->update($request->validated());

        return redirect()
            ->route('countries.index')
            ->with('success', 'Le pays '.$country->name.' a été mis à jour.');
    }

    public function toggle(Country $country): RedirectResponse
    {
        $country->update(['is_active' => ! $country->is_active]);

        return back()->with('success', $country->is_active
            ? $country->name.' est maintenant actif.'
            : $country->name.' est maintenant inactif.');
    }

    public function show(Country $country): RedirectResponse
    {
        return redirect()->route('countries.dashboard', $country);
    }
}
