@extends('layouts.app')

@section('title', 'Système (API) — '.$service->name)
@section('breadcrumbs')
    <a class="hover:text-slate-800" href="{{ route('countries.services.index', $country) }}">Services</a>
    <span>/</span>
    <a class="hover:text-slate-800" href="{{ route('countries.services.show', [$country, $service]) }}">{{ $service->name }}</a>
    <span>/</span>
    <span class="text-slate-700">Système (API)</span>
@endsection

@section('content')
    @if (! $service->usesApi())
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Ce service envoie actuellement vers un Google Sheet. Choisissez « Système (API) » dans <a class="underline" href="{{ route('countries.services.edit', [$country, $service]) }}">Modifier</a> pour utiliser ces réglages.
        </div>
    @endif

    <div class="mb-4 flex justify-end">
        <form method="POST" action="{{ route('countries.services.api.test', [$country, $service]) }}">
            @csrf
            <x-button variant="secondary">Tester la connexion</x-button>
        </form>
    </div>

    <form method="POST" action="{{ route('countries.services.api.update', [$country, $service]) }}" class="space-y-4">
        @csrf
        @method('PUT')

        <x-card title="Connexion Simon Express" description="Les identifiants sont chiffrés en base. Laissez un champ vide pour garder la valeur enregistrée.">
            <div class="grid gap-4 md:grid-cols-2">
                <x-input label="URL de base" name="base_url" :value="$config['base_url'] ?? config('simon_express.base_url')" />
                <x-select label="Authentification" name="auth_mode">
                    <option value="bearer" @selected(($config['auth_mode'] ?? 'bearer') === 'bearer')>Jeton (Authorization: Bearer tk_…)</option>
                    <option value="basic" @selected(($config['auth_mode'] ?? '') === 'basic')>Clé + secret (ck_… / cs_…)</option>
                </x-select>
                <x-input type="password" autocomplete="new-password" label="Jeton tk_… {{ filled($config['token'] ?? null) ? '(enregistré)' : '' }}" name="token" placeholder="{{ filled($config['token'] ?? null) ? '••••••••' : 'tk_…' }}" />
                <div></div>
                <x-input type="password" autocomplete="new-password" label="Consumer key ck_… {{ filled($config['consumer_key'] ?? null) ? '(enregistrée)' : '' }}" name="consumer_key" placeholder="{{ filled($config['consumer_key'] ?? null) ? '••••••••' : 'ck_…' }}" />
                <x-input type="password" autocomplete="new-password" label="Consumer secret cs_… {{ filled($config['consumer_secret'] ?? null) ? '(enregistré)' : '' }}" name="consumer_secret" placeholder="{{ filled($config['consumer_secret'] ?? null) ? '••••••••' : 'cs_…' }}" />
                <x-select label="Prix envoyé" name="api_price_mode">
                    <option value="total" @selected($service->api_price_mode !== 'unit')>Total de la ligne (quantité × prix)</option>
                    <option value="unit" @selected($service->api_price_mode === 'unit')>Prix unitaire</option>
                </x-select>
                <x-select label="Ville par défaut si aucune correspondance" name="api_city_fallback">
                    <option value="">— Aucune (la commande passe en erreur) —</option>
                    @foreach (array_unique(array_merge([config('simon_express.default_city_fallback')], $simonCities)) as $city)
                        <option value="{{ $city }}" @selected($service->api_city_fallback === $city)>{{ $city }}</option>
                    @endforeach
                </x-select>
            </div>
        </x-card>

        <x-card title="Produits → SKU Simon Express" description="Un produit source sans SKU n'est pas envoyé (statut « erreur: produit non mappé »). Prix forcé : optionnel, remplace le prix de la feuille.">
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr><th class="pb-3 pr-4">Produit source</th><th class="pb-3 pr-4">SKU Simon</th><th class="pb-3">Prix forcé</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($products->concat(array_fill(0, 3, '')) as $i => $product)
                        @php($map = $product === '' ? null : $service->productMappings->first(fn ($m) => \App\Support\OrderNormalizer::product($m->source_product) === \App\Support\OrderNormalizer::product($product)))
                        <tr>
                            <td class="py-2 pr-4"><input name="products[{{ $i }}][source]" value="{{ $product }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></td>
                            <td class="py-2 pr-4">
                                <select name="products[{{ $i }}][sku]" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                    <option value="">— non mappé —</option>
                                    @foreach ($skus as $sku => $price)
                                        <option value="{{ $sku }}" @selected($map?->sku === $sku)>{{ $sku }} ({{ number_format($price, 0, ',', ' ') }})</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="py-2"><input name="products[{{ $i }}][price]" value="{{ $map?->price_override }}" inputmode="decimal" class="w-32 rounded-lg border border-slate-200 px-3 py-2 text-sm"></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-card>

        <x-card title="Villes → villes Simon Express" description="Sans correspondance enregistrée, la ville est devinée automatiquement (proposition affichée), sinon la ville par défaut est utilisée.">
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr><th class="pb-3 pr-4">Ville / région source</th><th class="pb-3 pr-4">Ville Simon</th><th class="pb-3">Proposition auto</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($cities->concat(array_fill(0, 3, '')) as $i => $city)
                        @php($map = $city === '' ? null : $service->cityMappings->first(fn ($m) => \App\Services\Api\CityMatcher::normalize($m->source_city) === \App\Services\Api\CityMatcher::normalize($city)))
                        <tr>
                            <td class="py-2 pr-4"><input name="cities[{{ $i }}][source]" value="{{ $city }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></td>
                            <td class="py-2 pr-4">
                                <select name="cities[{{ $i }}][city]" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                    <option value="">— automatique —</option>
                                    @foreach ($simonCities as $simon)
                                        <option value="{{ $simon }}" @selected($map?->city === $simon)>{{ $simon }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="py-2 text-slate-500">{{ $city === '' ? '' : ($matcher->fuzzy($city) ?? '—') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-card>

        <div class="flex justify-end"><x-button>Enregistrer</x-button></div>
    </form>
@endsection
