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
        <x-button href="{{ route('countries.services.edit', [$country, $service]) }}">Modifier</x-button>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <x-card title="Informations">
            <dl class="space-y-3 text-sm">
                <div><dt class="text-slate-500">Pays</dt><dd class="font-medium">{{ $country->flag }} {{ $country->name }}</dd></div>
                <div><dt class="text-slate-500">Commandes</dt><dd class="font-medium">{{ $service->orders_count }}</dd></div>
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
                <div><dt class="text-slate-500">Onglet</dt><dd class="font-medium">{{ $service->destinationIntegration?->worksheet_name ?: '—' }}</dd></div>
                <div><dt class="text-slate-500">Identifiant</dt><dd class="break-all font-medium">{{ $service->destinationIntegration?->spreadsheet_id ?: '—' }}</dd></div>
                <div><dt class="text-slate-500">Connexion</dt><dd class="mt-1"><x-badge :tone="$service->destinationIntegration?->statusTone() ?? 'slate'">{{ $service->destinationIntegration?->statusLabel() ?? 'Non configuré' }}</x-badge></dd></div>
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
