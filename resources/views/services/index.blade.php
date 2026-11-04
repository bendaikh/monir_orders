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
                        <th class="px-4 py-3 font-medium" title="Commandes datées d'aujourd'hui dans la feuille source">Commandes du jour</th>
                        <th class="px-4 py-3 font-medium" title="Commandes du jour pas encore envoyées (même nombre que le bouton Valider)">En attente d'envoi</th>
                        <th class="px-4 py-3 font-medium" title="Toutes les commandes enregistrées pour ce service">Total enregistré</th>
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
                                <x-destination-status :service="$service" />
                            </td>
                            @php [$live, $liveError] = $liveCounts[$service->id] ?? [null, null]; @endphp
                            <td class="px-4 py-3 font-medium">@if ($live){{ $live['today'] }}@else<span class="text-slate-400" title="{{ $liveError }}">—</span>@endif</td>
                            <td class="px-4 py-3">@if ($live)<span class="{{ $live['pending'] > 0 ? 'font-semibold text-orange-700' : 'text-green-700' }}">{{ $live['pending'] }}</span>@else<span class="text-slate-400" title="{{ $liveError }}">—</span>@endif</td>
                            <td class="px-4 py-3 text-slate-500">{{ $service->orders_count }}</td>
                            <td class="px-4 py-3"><x-badge :tone="$service->is_active ? 'green' : 'slate'">{{ $service->is_active ? 'Actif' : 'Inactif' }}</x-badge></td>
                            <td class="hidden px-4 py-3 md:table-cell">{{ $service->created_at->translatedFormat('d M Y') }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-2 text-sm">
                                    <a class="inline-flex h-8 w-8 items-center justify-center rounded-lg hover:bg-slate-100 text-blue-700" title="Détails" aria-label="Détails" href="{{ route('countries.services.show', [$country, $service]) }}"><x-icon name="eye" class="h-5 w-5" /></a>
                                    <a class="inline-flex h-8 w-8 items-center justify-center rounded-lg hover:bg-slate-100 text-slate-700" title="Modifier" aria-label="Modifier" href="{{ route('countries.services.edit', [$country, $service]) }}"><x-icon name="pencil" class="h-5 w-5" /></a>
                                    <form method="POST" action="{{ route('countries.services.toggle', [$country, $service]) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button class="inline-flex h-8 w-8 items-center justify-center rounded-lg hover:bg-slate-100 {{ $service->is_active ? 'text-slate-600' : 'text-green-700' }}" type="submit" title="{{ $service->is_active ? 'Désactiver' : 'Activer' }}" aria-label="{{ $service->is_active ? 'Désactiver' : 'Activer' }}"><x-icon name="power" class="h-5 w-5" /></button>
                                    </form>
                                    <form method="POST" action="{{ route('countries.services.destroy', [$country, $service]) }}" class="inline" onsubmit="return confirm(@js('Supprimer le service '.$service->name.' ? (récupérable par l\'administrateur)'))">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-lg hover:bg-red-50 text-red-600" title="Supprimer" aria-label="Supprimer"><x-icon name="trash" class="h-5 w-5" /></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-10 text-center text-slate-500">Aucun service pour ce pays.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($services->hasPages())
            <div class="border-t border-slate-100 px-4 py-3">{{ $services->links() }}</div>
        @endif
    </div>
@endsection
