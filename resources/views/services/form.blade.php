@extends('layouts.app')

@section('title', $service->exists ? 'Modifier le service' : 'Ajouter un service')
@section('breadcrumbs')
    <a class="hover:text-slate-800" href="{{ route('countries.services.index', $country) }}">Services</a>
    <span>/</span>
    <span class="text-slate-700">{{ $service->exists ? $service->name : 'Nouveau' }}</span>
@endsection

@section('content')
    <form method="POST" action="{{ $service->exists ? route('countries.services.update', [$country, $service]) : route('countries.services.store', $country) }}" class="space-y-4">
        @csrf
        @if ($service->exists)
            @method('PUT')
        @endif

        <x-card title="Informations générales" description="Un service représente une société partenaire de ce pays.">
            <div class="grid gap-4 md:grid-cols-2">
                <x-input label="Nom du service" name="name" :value="$service->name" required />
                <x-select label="Statut" name="is_active">
                    <option value="1" @selected(old('is_active', $service->is_active ? '1' : '0') == '1' || old('is_active', $service->is_active) === true)>Actif</option>
                    <option value="0" @selected(old('is_active', $service->is_active ? '1' : '0') == '0' || old('is_active') === false)>Inactif</option>
                </x-select>
                <x-select label="Destination des commandes validées" name="destination_type">
                    <option value="sheet" @selected(old('destination_type', $service->destination_type ?? 'sheet') === 'sheet')>Google Sheet</option>
                    <option value="api" @selected(old('destination_type', $service->destination_type) === 'api')>Système (API) — Simon Express</option>
                </x-select>
                <div class="text-sm text-slate-500 md:self-end">
                    @if ($service->exists && $service->usesApi())
                        <a class="font-medium text-blue-600 hover:underline" href="{{ route('countries.services.api.edit', [$country, $service]) }}">Configurer le système (clé API, produits, villes) →</a>
                    @else
                        En mode « Système (API) », la clé, les produits et les villes se règlent dans l'onglet Système du service.
                    @endif
                </div>
                <div class="md:col-span-2">
                    <x-textarea label="Description" name="description" :value="$service->description" />
                </div>
                <div>
                    <span class="mb-1.5 block text-sm font-medium text-slate-700">Pays associé</span>
                    <p class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">{{ $country->flag }} {{ $country->name }}</p>
                </div>
            </div>
        </x-card>

        <x-card title="Google Sheet source" description="Ce tableur contient les commandes entrantes. Il est distinct de la destination.">
            <div class="grid gap-4 md:grid-cols-2">
                <x-input label="URL du Google Sheet source" name="source[spreadsheet_url]" dot="source.spreadsheet_url" :value="$source->spreadsheet_url ?? null" placeholder="https://docs.google.com/spreadsheets/d/..." />
                <x-input label="Nom de l'onglet source" name="source[worksheet_name]" dot="source.worksheet_name" :value="$source->worksheet_name ?? null" placeholder="Commandes" />
                <x-input label="Identifiant du Google Sheet" name="source[spreadsheet_id]" dot="source.spreadsheet_id" :value="$source->spreadsheet_id ?? null" />
                <div>
                    <span class="mb-1.5 block text-sm font-medium text-slate-700">Statut de connexion</span>
                    <x-badge :tone="$source?->statusTone() ?? 'slate'">{{ $source?->statusLabel() ?? 'Non configuré' }}</x-badge>
                    @if ($source?->last_error)
                        <p class="mt-2 text-sm text-red-600">{{ $source->last_error }}</p>
                    @endif
                </div>
            </div>
        </x-card>

        <x-card title="Google Sheet destination" description="Ce tableur reçoit uniquement les commandes validées. Il peut être entièrement différent de la source.">
            <div class="grid gap-4 md:grid-cols-2">
                <x-input label="URL du Google Sheet de destination" name="destination[spreadsheet_url]" dot="destination.spreadsheet_url" :value="$destination->spreadsheet_url ?? null" />
                <x-input label="Nom de l'onglet de destination" name="destination[worksheet_name]" dot="destination.worksheet_name" :value="$destination->worksheet_name ?? null" />
                <x-input label="Identifiant du Google Sheet" name="destination[spreadsheet_id]" dot="destination.spreadsheet_id" :value="$destination->spreadsheet_id ?? null" />
                <div>
                    <span class="mb-1.5 block text-sm font-medium text-slate-700">Statut de connexion</span>
                    <x-badge :tone="$destination?->statusTone() ?? 'slate'">{{ $destination?->statusLabel() ?? 'Non configuré' }}</x-badge>
                    @if ($destination?->last_error)
                        <p class="mt-2 text-sm text-red-600">{{ $destination->last_error }}</p>
                    @endif
                </div>
            </div>
        </x-card>

        <x-card title="Doublons, formats et identifiants" description="Réglages propres à ce service : comment reconnaître une commande en double, lire les dates et les montants de la feuille source, et générer les identifiants.">
            <div class="grid gap-4 md:grid-cols-2">
                <x-select label="Règle de détection des doublons" name="duplicate_key">
                    <option value="" @selected(old('duplicate_key', $service->duplicate_key) === null || old('duplicate_key', $service->duplicate_key) === '')>Automatique (référence si mappée, sinon téléphone + produit + jour)</option>
                    @foreach (\App\Support\OrderNormalizer::DUPLICATE_MODES as $value => $label)
                        <option value="{{ $value }}" @selected(old('duplicate_key', $service->duplicate_key) === $value)>{{ $label }}</option>
                    @endforeach
                </x-select>
                <x-input label="Chiffres significatifs du téléphone" name="phone_digits" type="number" min="6" max="15" :value="$service->phone_digits ?? 8" />
                <x-input label="Format de date de la source" name="date_format" :value="$service->date_format ?? 'd/m/Y H:i:s'" placeholder="d/m/Y H:i:s" />
                <x-select label="Format des montants" name="decimal_format">
                    @foreach (\App\Support\OrderNormalizer::DECIMAL_FORMATS as $value => $label)
                        <option value="{{ $value }}" @selected(old('decimal_format', $service->decimal_format ?? 'auto') === $value)>{{ $label }}</option>
                    @endforeach
                </x-select>
                <x-input label="Préfixe des identifiants générés" name="reference_prefix" :value="$service->reference_prefix" placeholder="ex. CP-" />
                <x-input label="Exclure les produits contenant" name="exclude_products" :value="$service->exclude_products" placeholder="ex. -dar, test (séparés par des virgules)" />
            </div>
            <p class="mt-3 text-xs text-slate-500">Les doublons restent visibles avec le statut « Doublon » et ne sont jamais envoyés. Le téléphone est comparé sans indicatif ni zéros initiaux, sur ses derniers chiffres. Si un préfixe est renseigné et que la colonne source « Référence » est vide, l'application génère des identifiants stables (préfixe + numéro). Le filtre produit est désactivé tant que le champ est vide.</p>
        </x-card>

        <x-card title="Correspondance des colonnes" description="Chaque service peut utiliser des noms de colonnes différents. La source alimente la commande, la destination reçoit la commande validée.">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="pb-3 pr-4 font-medium">Champ de commande</th>
                            <th class="pb-3 pr-4 font-medium">Colonne source</th>
                            <th class="pb-3 font-medium">Colonne de destination</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($fields as $field => $definition)
                            <tr>
                                <td class="py-3 pr-4 font-medium text-slate-800">
                                    {{ $definition['label'] }}
                                    @if ($definition['required'])
                                        <span class="text-red-600">*</span>
                                    @endif
                                </td>
                                <td class="py-3 pr-4">
                                    <input name="mappings[source][{{ $field }}]" value="{{ old('mappings.source.'.$field, $sourceMap[$field] ?? '') }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                    @error('mappings.source.'.$field)
                                        <span class="mt-1 block text-xs text-red-600">{{ $message }}</span>
                                    @enderror
                                </td>
                                <td class="py-3">
                                    <input name="mappings[destination][{{ $field }}]" value="{{ old('mappings.destination.'.$field, $destinationMap[$field] ?? '') }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                    @error('mappings.destination.'.$field)
                                        <span class="mt-1 block text-xs text-red-600">{{ $message }}</span>
                                    @enderror
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>

        <div class="flex justify-end gap-2">
            <x-button href="{{ route('countries.services.index', $country) }}" variant="secondary">Annuler</x-button>
            <x-button>Enregistrer la configuration</x-button>
        </div>
    </form>

    @if ($service->exists)
        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <x-card title="Actions sur la source">
                <p class="mb-3 text-sm text-slate-500">Enregistrez d'abord la configuration, puis testez ou importez.</p>
                <div class="flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('countries.services.test', [$country, $service, 'source']) }}">
                        @csrf
                        <x-button variant="secondary">Tester la connexion</x-button>
                    </form>
                    <form method="POST" action="{{ route('countries.services.import', [$country, $service]) }}" @submit="if (!confirm('Importer les commandes de la feuille source ?')) $event.preventDefault()">
                        @csrf
                        <x-button>Importer les commandes</x-button>
                    </form>
                </div>
            </x-card>
            <x-card title="Actions sur la destination">
                <p class="mb-3 text-sm text-slate-500">Le test d'envoi écrit une ligne réelle préfixée par TEST-.</p>
                <div class="flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('countries.services.test', [$country, $service, 'destination']) }}">
                        @csrf
                        <x-button variant="secondary">Tester la connexion</x-button>
                    </form>
                    <form method="POST" action="{{ route('countries.services.test-send', [$country, $service]) }}" @submit="if (!confirm('Écrire une ligne de test dans la feuille de destination ?')) $event.preventDefault()">
                        @csrf
                        <x-button variant="secondary">Tester l'envoi</x-button>
                    </form>
                </div>
            </x-card>
        </div>
    @endif
@endsection
