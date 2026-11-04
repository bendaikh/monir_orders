@extends('layouts.app')

@section('title', 'Historique des commandes')
@section('breadcrumbs')
    <a class="hover:text-slate-800" href="{{ route('countries.dashboard', $country) }}">{{ $country->name }}</a>
    <span>/</span>
    <span class="text-slate-700">Historique</span>
@endsection

@section('content')
    <div class="mb-5 flex gap-2">
        <a href="{{ route('countries.history.index', [$country, 'tab' => 'orders']) }}" class="rounded-lg px-3 py-2 text-sm font-semibold {{ $tab === 'orders' ? 'bg-blue-600 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-200' }}">Commandes</a>
        <a href="{{ route('countries.history.index', [$country, 'tab' => 'sync']) }}" class="rounded-lg px-3 py-2 text-sm font-semibold {{ $tab === 'sync' ? 'bg-blue-600 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-200' }}">Synchronisations</a>
    </div>

    @if ($tab === 'orders')
        @include('partials.order-filters', [
            'resetUrl' => route('countries.history.index', [$country, 'tab' => 'orders']),
        ])
        <div class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-soft">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-medium">Référence</th>
                            <th class="px-4 py-3 font-medium">Service</th>
                            <th class="hidden px-4 py-3 font-medium lg:table-cell">Réception</th>
                            <th class="hidden px-4 py-3 font-medium lg:table-cell">Validation</th>
                            <th class="hidden px-4 py-3 font-medium lg:table-cell">Transfert</th>
                            <th class="px-4 py-3 font-medium">Statut</th>
                            <th class="px-4 py-3 font-medium">Résultat</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($orders as $order)
                            @php $lastTransfer = $order->transferLogs->first(); @endphp
                            <tr>
                                <td class="px-4 py-3 font-medium">
                                    <a class="hover:text-blue-700" href="{{ route('countries.orders.show', [$country, $order]) }}">{{ $order->reference }}</a>
                                </td>
                                <td class="px-4 py-3">{{ $order->service->name }}</td>
                                <td class="hidden px-4 py-3 lg:table-cell">{{ $order->received_at?->translatedFormat('d M Y à H:i') ?: '—' }}</td>
                                <td class="hidden px-4 py-3 lg:table-cell">{{ $order->validated_at?->translatedFormat('d M Y à H:i') ?: ($order->rejected_at?->translatedFormat('d M Y à H:i') ?: '—') }}</td>
                                <td class="hidden px-4 py-3 lg:table-cell">{{ $order->transferred_at?->translatedFormat('d M Y à H:i') ?: '—' }}</td>
                                <td class="px-4 py-3"><x-status-badge :status="$order->displayStatus()" /></td>
                                <td class="px-4 py-3 text-slate-600">{{ $lastTransfer?->message ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">Aucun historique pour ces filtres.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($orders->hasPages())
                <div class="border-t border-slate-100 px-4 py-3">{{ $orders->links() }}</div>
            @endif
        </div>
    @else
        <form method="GET" class="mb-5 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-4">
            <input type="hidden" name="tab" value="sync">
            <x-select label="Service" name="service_id">
                <option value="">Tous</option>
                @foreach ($services as $service)
                    <option value="{{ $service->id }}" @selected((string) request('service_id') === (string) $service->id)>{{ $service->name }}</option>
                @endforeach
            </x-select>
            <x-select label="Résultat" name="sync_result">
                <option value="">Tous</option>
                @foreach (['success' => 'Réussi', 'partial' => 'Partiel', 'failed' => 'Échec', 'demo' => 'Démonstration'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('sync_result') === $value)>{{ $label }}</option>
                @endforeach
            </x-select>
            <x-input label="Du" name="date_from" type="date" value="{{ request('date_from') }}" />
            <x-input label="Au" name="date_to" type="date" value="{{ request('date_to') }}" />
            <div class="flex items-end gap-2 md:col-span-4">
                <x-button type="submit" variant="secondary">Filtrer</x-button>
                <x-button href="{{ route('countries.history.index', [$country, 'tab' => 'sync']) }}" variant="ghost">Réinitialiser</x-button>
            </div>
        </form>
        <div class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-soft">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-medium">Type</th>
                            <th class="px-4 py-3 font-medium">Service</th>
                            <th class="px-4 py-3 font-medium">Date</th>
                            <th class="px-4 py-3 font-medium">Récupérées</th>
                            <th class="px-4 py-3 font-medium">Envoyées</th>
                            <th class="px-4 py-3 font-medium">Erreurs</th>
                            <th class="px-4 py-3 font-medium">Résultat</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($syncs as $sync)
                            <tr>
                                <td class="px-4 py-3">{{ $sync->typeLabel() }}</td>
                                <td class="px-4 py-3">{{ $sync->service?->name ?: '—' }}</td>
                                <td class="px-4 py-3">{{ $sync->started_at?->translatedFormat('d M Y à H:i') }}</td>
                                <td class="px-4 py-3">{{ $sync->created_count }}</td>
                                <td class="px-4 py-3">{{ $sync->sent_count }}</td>
                                <td class="px-4 py-3">{{ $sync->error_count }}</td>
                                <td class="px-4 py-3">
                                    <x-badge :tone="$sync->resultTone()">{{ $sync->resultLabel() }}</x-badge>
                                    <p class="mt-1 max-w-xs text-xs text-slate-500">{{ $sync->message }}</p>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">Aucune synchronisation.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($syncs->hasPages())
                <div class="border-t border-slate-100 px-4 py-3">{{ $syncs->links() }}</div>
            @endif
        </div>
    @endif
@endsection
