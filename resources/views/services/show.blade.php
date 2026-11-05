@extends('layouts.app')

@section('title', $service->name)
@section('breadcrumbs')
    <a class="hover:text-slate-800" href="{{ route('countries.services.index', $country) }}">Services</a>
    <span>/</span>
    <span class="text-slate-700">{{ $service->name }}</span>
@endsection

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <x-badge :tone="$service->is_active ? 'green' : 'slate'">{{ $service->is_active ? 'Actif' : 'Inactif' }}</x-badge>
            <p class="mt-2 max-w-2xl text-sm text-slate-500">{{ $service->description }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <form method="POST" action="{{ route('countries.services.validate-all', [$country, $service]) }}" @submit="if (!confirm('Valider et envoyer les {{ $todayCount ?? '' }} commande(s) en attente de {{ addslashes($service->name) }} ? Les doublons seront supprimés et non envoyés.')) $event.preventDefault()">
                @csrf
                <x-button :disabled="$todayCount === 0">Valider toutes les commandes en attente ({{ $todayCount ?? '?' }})</x-button>
                <p class="mt-1 text-xs text-slate-500">
                    @if ($todayError)
                        <span class="text-red-600">Comptage indisponible : {{ $todayError }}</span>
                    @else
                        Commandes du {{ now()->format('d/m/Y') }} pas encore envoyées (les commandes déjà envoyées ne sont jamais renvoyées).
                    @endif
                </p>
            </form>
            @if ($service->usesApi())
                <x-button href="{{ route('countries.services.api.edit', [$country, $service]) }}" variant="secondary">Système (API)</x-button>
            @endif
            <x-button href="{{ route('countries.services.edit', [$country, $service]) }}" variant="secondary">Modifier</x-button>
        </div>
    </div>

    <div
        class="mb-6"
        x-data="{
            run: @js($lastRun?->toPayload()),
            timer: null,
            init() { if (this.run && this.run.active) this.poll(); },
            poll() {
                this.timer = setInterval(async () => {
                    try {
                        const r = await fetch(@js(route('countries.services.push-status', [$country, $service])), { headers: { 'Accept': 'application/json' } });
                        const data = await r.json();
                        const wasActive = this.run && this.run.active;
                        this.run = data.run;
                        if (!this.run || !this.run.active) {
                            clearInterval(this.timer);
                            if (wasActive) window.location.reload();
                        }
                    } catch (e) {}
                }, 4000);
            },
        }"
        x-show="run"
        x-cloak
    >
        <div class="rounded-xl border px-5 py-4 text-sm shadow-soft"
             :class="{
                'border-blue-200 bg-blue-50 text-blue-800': run && run.active,
                'border-green-200 bg-green-50 text-green-800': run && !run.active && run.tone === 'success',
                'border-orange-200 bg-orange-50 text-orange-800': run && !run.active && run.tone === 'warning',
                'border-red-200 bg-red-50 text-red-800': run && !run.active && (run.tone === 'error' || run.status === 'failed'),
             }">
            <div class="font-semibold">
                Dernier envoi « Valider et push » :
                <span x-text="run ? (run.active ? run.label + '…' : run.label) : ''"></span>
                <span class="font-normal text-xs" x-text="run ? '(lancé le ' + run.created_at + (run.finished_at ? ', terminé le ' + run.finished_at : '') + ')' : ''"></span>
            </div>
            <template x-if="run && run.active">
                <p class="mt-1">L'envoi tourne en arrière-plan. Cette zone se met à jour toute seule, inutile de recharger la page.</p>
            </template>
            <template x-if="run && !run.active">
                <div>
                    <p class="mt-1" x-text="run.message || run.error || 'Envoi interrompu (aucune réponse du serveur depuis plus de 30 minutes).'"></p>
                    <div class="mt-2 flex flex-wrap gap-4 font-medium">
                        <span>Commandes traitées : <b x-text="run.today_count ?? run.total"></b></span>
                        <span>Envoyées : <b x-text="run.transferred"></b></span>
                        <span>Doublons : <b x-text="run.duplicates"></b></span>
                        <span>Échecs : <b x-text="run.failed"></b></span>
                    </div>
                    <ul class="mt-2 list-disc pl-5 text-xs" x-show="run.failures && run.failures.length">
                        <template x-for="f in (run.failures || [])"><li x-text="f.ref + ' ' + (f.name || '') + ' : ' + f.reason"></li></template>
                    </ul>
                </div>
            </template>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <x-card title="Informations">
            <dl class="space-y-3 text-sm">
                <div><dt class="text-slate-500">Pays</dt><dd class="font-medium">{{ $country->flag }} {{ $country->name }}</dd></div>
                <div><dt class="text-slate-500">Commandes</dt><dd class="font-medium">{{ $service->orders_count }} dont {{ $pendingCount }} en attente, {{ $duplicateCount }} doublon(s)</dd></div>
                <div><dt class="text-slate-500">Doublons</dt><dd class="font-medium">{{ \App\Support\OrderNormalizer::DUPLICATE_MODES[$service->duplicateMode()] ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Formats</dt><dd class="font-medium">Dates {{ $service->date_format }} · Montants {{ \App\Support\OrderNormalizer::DECIMAL_FORMATS[$service->decimal_format] ?? $service->decimal_format }}</dd></div>
                <div><dt class="text-slate-500">Identifiants générés</dt><dd class="font-medium">{{ $service->reference_prefix ? $service->reference_prefix.'1, '.$service->reference_prefix.'2…' : 'Non' }}</dd></div>
                @if ($service->exclude_products)
                    <div><dt class="text-slate-500">Produits exclus</dt><dd class="font-medium">{{ $service->exclude_products }}</dd></div>
                @endif
                <div><dt class="text-slate-500">Créé le</dt><dd class="font-medium">{{ $service->created_at->translatedFormat('d M Y') }}</dd></div>
            </dl>
        </x-card>
        <x-card title="Source — commandes entrantes">
            <dl class="space-y-3 text-sm">
                <div><dt class="text-slate-500">Onglet</dt><dd class="font-medium">{{ $service->sourceIntegration?->worksheet_name ?: '—' }}</dd></div>
                <div><dt class="text-slate-500">Identifiant</dt><dd class="break-all font-medium">{{ $service->sourceIntegration?->spreadsheet_id ?: '—' }}</dd></div>
                <div><dt class="text-slate-500">Connexion</dt><dd class="mt-1"><x-badge :tone="$service->sourceIntegration?->statusTone() ?? 'slate'">{{ $service->sourceIntegration?->statusLabel() ?? 'Non configuré' }}</x-badge></dd></div>
            </dl>
        </x-card>
        <x-card title="Destination — commandes validées">
            <dl class="space-y-3 text-sm">
                @if ($service->usesApi())
                    <div><dt class="text-slate-500">Système</dt><dd class="font-medium">Simon Express (API)</dd></div>
                    <div><dt class="text-slate-500">Connexion</dt><dd class="mt-1"><x-destination-status :service="$service" /></dd></div>
                @else
                <div><dt class="text-slate-500">Onglet</dt><dd class="font-medium">{{ $service->destinationIntegration?->worksheet_name ?: '—' }}</dd></div>
                <div><dt class="text-slate-500">Identifiant</dt><dd class="break-all font-medium">{{ $service->destinationIntegration?->spreadsheet_id ?: '—' }}</dd></div>
                <div><dt class="text-slate-500">Connexion</dt><dd class="mt-1"><x-badge :tone="$service->destinationIntegration?->statusTone() ?? 'slate'">{{ $service->destinationIntegration?->statusLabel() ?? 'Non configuré' }}</x-badge></dd></div>
                @endif
            </dl>
        </x-card>
    </div>

    <x-card class="mt-4" title="Mapping">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="pb-3 pr-4 font-medium">Champ</th>
                        <th class="pb-3 pr-4 font-medium">Colonne source</th>
                        <th class="pb-3 font-medium">Colonne de destination</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($fields as $field => $definition)
                        <tr>
                            <td class="py-3 pr-4">{{ $definition['label'] }}</td>
                            <td class="py-3 pr-4">{{ $service->mappingFor('source')[$field] ?? '—' }}</td>
                            <td class="py-3">{{ $service->mappingFor('destination')[$field] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>
@endsection
