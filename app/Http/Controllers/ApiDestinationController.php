<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\PartnerService;
use App\Services\Api\CityMatcher;
use App\Services\Api\SimonExpressClient;
use App\Support\OrderNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ApiDestinationController extends Controller
{
    public function edit(Country $country, PartnerService $service, CityMatcher $matcher): View
    {
        $service->load(['productMappings', 'cityMappings']);
        $config = $service->api_config ?? [];

        $products = $service->orders()->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->distinct()->orderBy('order_items.name')->pluck('order_items.name')
            ->merge($service->productMappings->pluck('source_product'))
            ->unique(fn ($p) => OrderNormalizer::product($p))->values();

        $cities = $service->orders()->whereNotNull('city')->where('city', '!=', '')
            ->distinct()->orderBy('city')->limit(300)->pluck('city')
            ->merge($service->cityMappings->pluck('source_city'))
            ->unique(fn ($c) => CityMatcher::normalize($c))->values();

        return view('services.api', [
            'country' => $country,
            'service' => $service,
            'config' => $config,
            'skus' => config('simon_express.skus', []),
            'simonCities' => config('simon_express.cities', []),
            'products' => $products,
            'cities' => $cities,
            'matcher' => $matcher,
        ]);
    }

    public function update(Request $request, Country $country, PartnerService $service): RedirectResponse
    {
        $skus = array_keys(config('simon_express.skus', []));
        $cities = config('simon_express.cities', []);
        $data = $request->validate([
            'base_url' => ['nullable', 'url', 'max:200'],
            'auth_mode' => ['required', Rule::in(['bearer', 'basic'])],
            'token' => ['nullable', 'string', 'max:300'],
            'consumer_key' => ['nullable', 'string', 'max:300'],
            'consumer_secret' => ['nullable', 'string', 'max:300'],
            'api_price_mode' => ['required', Rule::in(['total', 'unit'])],
            'api_city_fallback' => ['nullable', 'string', 'max:100'],
            'products' => ['array'],
            'products.*.source' => ['nullable', 'string', 'max:255'],
            'products.*.sku' => ['nullable', Rule::in($skus)],
            'products.*.price' => ['nullable', 'numeric', 'min:0'],
            'cities' => ['array'],
            'cities.*.source' => ['nullable', 'string', 'max:255'],
            'cities.*.city' => ['nullable', 'string', Rule::in(array_merge($cities, [$request->input('api_city_fallback')]))],
        ]);

        $config = $service->api_config ?? [];
        $config['provider'] = 'simon_express';
        $config['base_url'] = ($data['base_url'] ?? null) ?: config('simon_express.base_url');
        $config['auth_mode'] = $data['auth_mode'];

        // Blank secret fields keep the stored value.
        foreach (['token', 'consumer_key', 'consumer_secret'] as $field) {
            if (filled($data[$field] ?? null)) {
                $config[$field] = trim($data[$field]);
            }
        }

        DB::transaction(function () use ($service, $config, $data) {
            $service->update([
                'api_config' => $config,
                'api_price_mode' => $data['api_price_mode'],
                'api_city_fallback' => $data['api_city_fallback'] ?: null,
            ]);

            foreach ($data['products'] ?? [] as $row) {
                $source = trim((string) ($row['source'] ?? ''));

                if ($source === '') {
                    continue;
                }

                $existing = $service->productMappings()->get()->first(fn ($m) => OrderNormalizer::product($m->source_product) === OrderNormalizer::product($source));

                if (blank($row['sku'] ?? null)) {
                    continue; // leaving the SKU empty never deletes an existing mapping
                }

                $values = ['source_product' => $source, 'sku' => $row['sku'], 'price_override' => filled($row['price'] ?? null) ? $row['price'] : null];
                $existing ? $existing->update($values) : $service->productMappings()->create($values);
            }

            foreach ($data['cities'] ?? [] as $row) {
                $source = trim((string) ($row['source'] ?? ''));

                if ($source === '' || blank($row['city'] ?? null)) {
                    continue;
                }

                $existing = $service->cityMappings()->get()->first(fn ($m) => CityMatcher::normalize($m->source_city) === CityMatcher::normalize($source));
                $existing ? $existing->update(['city' => $row['city']]) : $service->cityMappings()->create(['source_city' => $source, 'city' => $row['city']]);
            }
        });

        return redirect()->route('countries.services.api.edit', [$country, $service])->with('success', 'Réglages du système enregistrés.');
    }

    public function test(Country $country, PartnerService $service, SimonExpressClient $client): RedirectResponse
    {
        $result = $client->test($service);

        return back()->with($result['ok'] ? 'success' : 'error', 'Simon Express : '.$result['message']);
    }
}
