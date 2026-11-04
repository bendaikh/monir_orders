@extends('layouts.app')

@section('title', 'Gestion des pays')
@section('breadcrumbs')
    <a class="hover:text-slate-800" href="{{ route('dashboard') }}">Tableau de bord</a>
    <span>/</span>
    <span class="text-slate-700">Pays</span>
@endsection

@section('content')
    <div
        x-data="countryModal()"
        @if ($errors->any())
            x-init="open = true; mode = @js(old('_mode', 'create')); action = @js(old('_action', route('countries.store'))); name = @js(old('name', '')); code = @js(old('code', '')); currency = @js(old('currency', $defaultCurrency)); description = @js(old('description', '')); is_active = @js(filter_var(old('is_active', true), FILTER_VALIDATE_BOOLEAN) ? '1' : '0');"
        @endif
    >
        <div class="mb-6 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <form method="GET" class="grid flex-1 gap-3 sm:grid-cols-3">
                <x-input label="Rechercher" name="q" value="{{ request('q') }}" placeholder="Nom ou code" />
                <x-select label="Statut" name="status">
                    <option value="">Tous</option>
                    <option value="active" @selected(request('status') === 'active')>Actif</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Inactif</option>
                </x-select>
                <div class="flex items-end gap-2">
                    <x-button type="submit" variant="secondary">Filtrer</x-button>
                    <x-button href="{{ route('countries.index') }}" variant="ghost">Réinitialiser</x-button>
                </div>
            </form>
            <button type="button" class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700" @click="openCreate(@js(route('countries.store')), @js($defaultCurrency))">
                <x-icon name="plus" class="h-4 w-4" /> Ajouter un pays
            </button>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-medium">Pays</th>
                            <th class="px-4 py-3 font-medium">Code</th>
                            <th class="px-4 py-3 font-medium">Devise</th>
                            <th class="hidden px-4 py-3 font-medium md:table-cell">Services</th>
                            <th class="hidden px-4 py-3 font-medium md:table-cell">Commandes</th>
                            <th class="px-4 py-3 font-medium">Statut</th>
                            <th class="hidden px-4 py-3 font-medium lg:table-cell">Création</th>
                            <th class="px-4 py-3 font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($countries as $country)
                            <tr class="align-top">
                                <td class="px-4 py-3">
                                    <a href="{{ route('countries.dashboard', $country) }}" class="font-medium text-slate-900 hover:text-blue-700">
                                        <span class="mr-1">{{ $country->flag }}</span>{{ $country->name }}
                                    </a>
                                    @if ($country->description)
                                        <p class="mt-1 max-w-xs text-xs text-slate-500">{{ $country->description }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $country->code }}</td>
                                <td class="px-4 py-3">{{ $country->currency }}</td>
                                <td class="hidden px-4 py-3 md:table-cell">{{ $country->services_count }}</td>
                                <td class="hidden px-4 py-3 md:table-cell">{{ $country->orders_count }}</td>
                                <td class="px-4 py-3">
                                    <x-badge :tone="$country->is_active ? 'green' : 'slate'">{{ $country->is_active ? 'Actif' : 'Inactif' }}</x-badge>
                                </td>
                                <td class="hidden px-4 py-3 lg:table-cell">{{ $country->created_at->translatedFormat('d M Y') }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <a href="{{ route('countries.dashboard', $country) }}" class="inline-flex h-8 w-8 items-center justify-center rounded-lg hover:bg-slate-100 text-slate-700 ring-1 ring-slate-200" title="Espace" aria-label="Espace"><x-icon name="open" class="h-5 w-5" /></a>
                                        <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-lg hover:bg-slate-100 text-blue-700 hover:text-blue-800" title="Modifier" aria-label="Modifier" @click="openEdit(@js(route('countries.update', $country)), @js(['name' => $country->name, 'code' => $country->code, 'currency' => $country->currency, 'description' => $country->description, 'is_active' => $country->is_active]))"><x-icon name="pencil" class="h-5 w-5" /></button>
                                        <form method="POST" action="{{ route('countries.toggle', $country) }}" @submit="if (!confirm(@js($country->is_active ? 'Désactiver ce pays ?' : 'Activer ce pays ?'))) $event.preventDefault()">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-lg hover:bg-slate-100 {{ $country->is_active ? 'text-slate-600' : 'text-green-700' }} hover:text-slate-900" title="{{ $country->is_active ? 'Désactiver' : 'Activer' }}" aria-label="{{ $country->is_active ? 'Désactiver' : 'Activer' }}"><x-icon name="power" class="h-5 w-5" /></button>
                                        </form>
                                        <form method="POST" action="{{ route('countries.destroy', $country) }}" class="inline" onsubmit="return confirm(@js('Supprimer le pays '.$country->name.' ?'))">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-lg hover:bg-red-50 text-red-600" title="Supprimer" aria-label="Supprimer"><x-icon name="trash" class="h-5 w-5" /></button>
                                    </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-10 text-center text-slate-500">Aucun pays ne correspond à la recherche.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($countries->hasPages())
                <div class="border-t border-slate-100 px-4 py-3">{{ $countries->links() }}</div>
            @endif
        </div>

        <div x-cloak x-show="open" class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 p-4 sm:items-center">
            <form method="POST" :action="action" class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl" @click.outside="open = false">
                @csrf
                <input type="hidden" name="_mode" :value="mode">
                <input type="hidden" name="_action" :value="action">
                <template x-if="mode === 'edit'">
                    <input type="hidden" name="_method" value="PUT">
                </template>
                <div class="mb-4 flex items-start justify-between">
                    <h2 class="text-lg font-semibold text-slate-900" x-text="mode === 'edit' ? 'Modifier le pays' : 'Ajouter un pays'"></h2>
                    <button type="button" class="text-slate-400" @click="open = false" aria-label="Fermer"><x-icon name="close" /></button>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block sm:col-span-2">
                        <span class="mb-1.5 block text-sm font-medium text-slate-700">Nom du pays</span>
                        <input name="name" x-model="name" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        @error('name')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-slate-700">Code du pays</span>
                        <input name="code" x-model="code" maxlength="2" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm uppercase">
                        @error('code')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-slate-700">Devise</span>
                        <input name="currency" x-model="currency" maxlength="3" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm uppercase">
                        @error('currency')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror
                    </label>
                    <label class="block sm:col-span-2">
                        <span class="mb-1.5 block text-sm font-medium text-slate-700">Description</span>
                        <textarea name="description" x-model="description" class="min-h-24 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></textarea>
                    </label>
                    <label class="block sm:col-span-2">
                        <span class="mb-1.5 block text-sm font-medium text-slate-700">Statut</span>
                        <select name="is_active" x-model="is_active" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            <option value="1">Actif</option>
                            <option value="0">Inactif</option>
                        </select>
                    </label>
                </div>
                <div class="mt-5 flex justify-end gap-2">
                    <x-button type="button" variant="secondary" @click="open = false">Annuler</x-button>
                    <x-button>Enregistrer</x-button>
                </div>
            </form>
        </div>
    </div>
@endsection
