@extends('layouts.app')

@section('title', 'Gestion des services')
@section('breadcrumbs')
    <a class="hover:text-slate-800" href="{{ route('countries.dashboard', $country) }}">{{ $country->name }}</a>
    <span>/</span>
    <span class="text-slate-700">Services</span>
@endsection

@section('content')
    <div class="mb-6 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <form method="GET" class="grid flex-1 gap-3 sm:grid-cols-3">
            <x-input label="Rechercher" name="q" value="{{ request('q') }}" placeholder="Nom du service" />
            <x-select label="Statut" name="status">
                <option value="">Tous</option>
                <option value="active" @selected(request('status') === 'active')>Actif</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactif</option>
            </x-select>
            <div class="flex items-end gap-2">
                <x-button type="submit" variant="secondary">Filtrer</x-button>
                <x-button href="{{ route('countries.services.index', $country) }}" variant="ghost">Réinitialiser</x-button>
            </div>
        </form>
        <x-button href="{{ route('countries.services.create', $country) }}">
            <x-icon name="plus" class="h-4 w-4" /> Ajouter un service
        </x-button>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">Service</th>
                        <th class="hidden px-4 py-3 font-medium lg:table-cell">Source</th>
                        <th class="hidden px-4 py-3 font-medium lg:table-cell">Destination</th>
                        <th class="px-4 py-3 font-medium">Commandes</th>
                        <th class="px-4 py-3 font-medium">Statut</th>
                        <th class="hidden px-4 py-3 font-medium md:table-cell">Création</th>
                        <th class="px-4 py-3 font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($services as $service)
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-900">{{ $service->name }}</p>
                                <p class="mt-1 max-w-xs text-xs text-slate-500">{{ $service->description }}</p>
                                <p class="mt-1 text-xs text-slate-400">{{ $country->flag }} {{ $country->name }}</p>
                            </td>
                            <td class="hidden px-4 py-3 lg:table-cell">
                                <x-badge :tone="$service->sourceIntegration?->statusTone() ?? 'slate'">{{ $service->sourceIntegration?->statusLabel() ?? 'Non configuré' }}</x-badge>
                                <p class="mt-1 text-xs text-slate-500">{{ $service->sourceIntegration?->worksheet_name ?: 'Onglet non défini' }}</p>
                            </td>
                            <td class="hidden px-4 py-3 lg:table-cell">
                                <x-badge :tone="$service->destinationIntegration?->statusTone() ?? 'slate'">{{ $service->destinationIntegration?->statusLabel() ?? 'Non configuré' }}</x-badge>
                                <p class="mt-1 text-xs text-slate-500">{{ $service->destinationIntegration?->worksheet_name ?: 'Onglet non défini' }}</p>
                            </td>
                            <td class="px-4 py-3">{{ $service->orders_count }}</td>
                            <td class="px-4 py-3"><x-badge :tone="$service->is_active ? 'green' : 'slate'">{{ $service->is_active ? 'Actif' : 'Inactif' }}</x-badge></td>
                            <td class="hidden px-4 py-3 md:table-cell">{{ $service->created_at->translatedFormat('d M Y') }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-2 text-sm">
                                    <a class="font-medium text-blue-700" href="{{ route('countries.services.show', [$country, $service]) }}">Détails</a>
                                    <a class="font-medium text-slate-700" href="{{ route('countries.services.edit', [$country, $service]) }}">Modifier</a>
                                    <form method="POST" action="{{ route('countries.services.toggle', [$country, $service]) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button class="font-medium text-slate-600" type="submit">{{ $service->is_active ? 'Désactiver' : 'Activer' }}</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">Aucun service pour ce pays.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($services->hasPages())
            <div class="border-t border-slate-100 px-4 py-3">{{ $services->links() }}</div>
        @endif
    </div>
@endsection
