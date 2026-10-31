@extends('layouts.app')

@section('title', 'Validation des commandes')
@section('breadcrumbs')
    <a class="hover:text-slate-800" href="{{ route('countries.dashboard', $country) }}">{{ $country->name }}</a>
    <span>/</span>
    <span class="text-slate-700">Validation</span>
@endsection

@section('content')
    @if ($hasDemo)
        <x-demo-banner />
    @endif

    <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <x-stat label="Commandes importées" :value="$summary['total']" tone="slate"><x-icon name="orders" /></x-stat>
        <x-stat label="En attente" :value="$summary['pending']" tone="orange"><x-icon name="alert" /></x-stat>
        <x-stat label="Validées" :value="$summary['validated'] + $summary['transfer_pending'] + $summary['transferred'] + $summary['failed']" tone="blue"><x-icon name="check" /></x-stat>
        <x-stat label="Rejetées" :value="$summary['rejected']" tone="red"><x-icon name="close" /></x-stat>
        <x-stat label="Erreurs de transfert" :value="$summary['failed']" tone="rose"><x-icon name="alert" /></x-stat>
    </div>

    @include('partials.order-filters', [
        'resetUrl' => route('countries.validation.index', $country),
        'defaultStatus' => \App\Enums\OrderDisplayStatus::PendingValidation->value,
    ])

    @php
        $selectable = $orders->getCollection()->filter(fn ($order) => $order->canValidate())->map(fn ($order) => (string) $order->id)->values();
    @endphp

    <div x-data="validationBoard(@js($selectable))">
    <form method="POST" action="{{ route('countries.validation.bulk', $country) }}" @submit="if (selected.length === 0 || !confirm('Valider les ' + selected.length + ' commandes sélectionnées ?')) $event.preventDefault()">
        @csrf
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-slate-600"><span class="font-semibold text-slate-900" x-text="selected.length"></span> commande(s) sélectionnée(s)</p>
            <x-button x-bind:disabled="selected.length === 0">Valider les commandes sélectionnées</x-button>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">
                                <input type="checkbox" class="rounded border-slate-300" @change="toggleAll($event.target.checked)" :checked="ids.length > 0 && selected.length === ids.length">
                            </th>
                            <th class="px-4 py-3 font-medium">Référence</th>
                            <th class="px-4 py-3 font-medium">Client</th>
                            <th class="hidden px-4 py-3 font-medium md:table-cell">Téléphone</th>
                            <th class="hidden px-4 py-3 font-medium lg:table-cell">Ville</th>
                            <th class="px-4 py-3 font-medium">Montant</th>
                            <th class="hidden px-4 py-3 font-medium md:table-cell">Service</th>
                            <th class="hidden px-4 py-3 font-medium lg:table-cell">Réception</th>
                            <th class="px-4 py-3 font-medium">Statut</th>
                            <th class="px-4 py-3 font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($orders as $order)
                            <tr>
                                <td class="px-4 py-3">
                                    @if ($order->canValidate())
                                        <input type="checkbox" name="orders[]" value="{{ $order->id }}" class="rounded border-slate-300" x-model="selected">
                                    @endif
                                </td>
                                <td class="px-4 py-3 font-medium">{{ $order->reference }}</td>
                                <td class="px-4 py-3">{{ $order->customer_name }}</td>
                                <td class="hidden px-4 py-3 md:table-cell">{{ $order->phone ?: '—' }}</td>
                                <td class="hidden px-4 py-3 lg:table-cell">{{ $order->city ?: '—' }}</td>
                                <td class="px-4 py-3">{{ $order->formattedTotal() }}</td>
                                <td class="hidden px-4 py-3 md:table-cell">{{ $order->service->name }}</td>
                                <td class="hidden px-4 py-3 lg:table-cell">{{ $order->received_at?->translatedFormat('d M Y') }}</td>
                                <td class="px-4 py-3"><x-status-badge :status="$order->displayStatus()" /></td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <a class="text-sm font-medium text-blue-700" href="{{ route('countries.orders.show', [$country, $order]) }}">Détails</a>
                                        @if ($order->canValidate())
                                            <button form="validate-{{ $order->id }}" class="text-sm font-medium text-green-700" type="submit">Valider</button>
                                        @endif
                                        @if ($order->canReject())
                                            <button type="button" class="text-sm font-medium text-red-700" @click="openReject(@js(route('countries.validation.reject', [$country, $order])), @js($order->reference))">Rejeter</button>
                                        @endif
                                        @if ($order->canRetryTransfer() && ! $order->canValidate())
                                            <button form="retry-{{ $order->id }}" class="text-sm font-medium text-purple-700" type="submit">Réessayer</button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="px-4 py-10 text-center text-slate-500">Aucune commande à afficher.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($orders->hasPages())
                <div class="border-t border-slate-100 px-4 py-3">{{ $orders->links() }}</div>
            @endif
        </div>
    </form>

    <div x-cloak x-show="rejectOpen" class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 p-4 sm:items-center">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl" @click.outside="rejectOpen = false">
            <h2 class="text-lg font-semibold text-slate-900">Rejeter <span x-text="rejectReference"></span></h2>
            <p class="mt-1 text-sm text-slate-500">La commande ne sera pas envoyée vers la feuille de destination.</p>
            <form method="POST" :action="rejectAction" class="mt-4">
                @csrf
                <label class="block text-sm font-medium text-slate-700">Motif, facultatif</label>
                <textarea name="reason" class="mt-1 min-h-24 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></textarea>
                <div class="mt-4 flex justify-end gap-2">
                    <x-button type="button" variant="secondary" @click="rejectOpen = false">Annuler</x-button>
                    <x-button variant="danger">Rejeter</x-button>
                </div>
            </form>
        </div>
    </div>
    </div>

    @foreach ($orders as $order)
        @if ($order->canValidate())
            <form id="validate-{{ $order->id }}" method="POST" action="{{ route('countries.validation.validate', [$country, $order]) }}" @submit="if (!confirm('Valider ' + @js($order->reference) + ' et tenter le transfert ?')) $event.preventDefault()">
                @csrf
            </form>
        @endif
        @if ($order->canRetryTransfer() && ! $order->canValidate())
            <form id="retry-{{ $order->id }}" method="POST" action="{{ route('countries.orders.retry', [$country, $order]) }}">
                @csrf
            </form>
        @endif
    @endforeach
@endsection
