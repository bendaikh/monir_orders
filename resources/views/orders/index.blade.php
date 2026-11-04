@extends('layouts.app')

@section('title', $country ? 'Gestion des commandes' : 'Toutes les commandes')
@section('breadcrumbs')
    @if ($country)
        <a class="hover:text-slate-800" href="{{ route('countries.dashboard', $country) }}">{{ $country->name }}</a>
        <span>/</span>
        <span class="text-slate-700">Commandes</span>
    @else
        <a class="hover:text-slate-800" href="{{ route('dashboard') }}">Tableau de bord</a>
        <span>/</span>
        <span class="text-slate-700">Commandes</span>
    @endif
@endsection

@section('content')
    @if ($hasDemo)
        <x-demo-banner />
    @endif

    @include('partials.order-filters', [
        'resetUrl' => $country ? route('countries.orders.index', $country) : route('orders.global'),
    ])

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">Référence</th>
                        <th class="px-4 py-3 font-medium">Client</th>
                        <th class="hidden px-4 py-3 font-medium md:table-cell">Téléphone</th>
                        <th class="hidden px-4 py-3 font-medium lg:table-cell">Ville</th>
                        <th class="hidden px-4 py-3 font-medium xl:table-cell">Adresse</th>
                        <th class="px-4 py-3 font-medium">Montant</th>
                        <th class="hidden px-4 py-3 font-medium md:table-cell">Service</th>
                        <th class="hidden px-4 py-3 font-medium lg:table-cell">Date</th>
                        <th class="px-4 py-3 font-medium">Statut</th>
                        <th class="px-4 py-3 font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($orders as $order)
                        @php $orderCountry = $country ?? $order->service->country; @endphp
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-900">
                                <a class="hover:text-blue-700" href="{{ route('countries.orders.show', [$orderCountry, $order]) }}">{{ $order->reference }}</a>
                                @if ($order->is_demo)
                                    <span class="mt-1 block text-xs text-blue-600">Démo</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $order->customer_name }}</td>
                            <td class="hidden px-4 py-3 md:table-cell">{{ $order->phone ?: '—' }}</td>
                            <td class="hidden px-4 py-3 lg:table-cell">{{ $order->city ?: '—' }}</td>
                            <td class="hidden max-w-xs truncate px-4 py-3 xl:table-cell">{{ $order->address ?: '—' }}</td>
                            <td class="px-4 py-3">{{ $order->formattedTotal() }}</td>
                            <td class="hidden px-4 py-3 md:table-cell">{{ $order->service->name }}</td>
                            <td class="hidden px-4 py-3 lg:table-cell">{{ $order->ordered_at?->translatedFormat('d M Y') ?: '—' }}</td>
                            <td class="px-4 py-3"><x-status-badge :status="$order->displayStatus()" /></td>
                            <td class="px-4 py-3">
                                <a class="inline-flex h-8 w-8 items-center justify-center rounded-lg hover:bg-slate-100 text-blue-700" title="Consulter" aria-label="Consulter" href="{{ route('countries.orders.show', [$orderCountry, $order]) }}"><x-icon name="eye" class="h-5 w-5" /></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="px-4 py-10 text-center text-slate-500">Aucune commande ne correspond aux filtres.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($orders->hasPages())
            <div class="border-t border-slate-100 px-4 py-3">{{ $orders->links() }}</div>
        @endif
    </div>
@endsection
