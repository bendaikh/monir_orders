@extends('layouts.app')

@section('title', 'Tableau de bord')
@section('breadcrumbs')
    <span class="text-slate-700">Vue globale</span>
@endsection

@section('content')
    @if ($hasDemo)
        <x-demo-banner />
    @endif

    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-slate-500">Synthèse de tous les pays, services et commandes.</p>
        <div class="flex flex-wrap gap-2">
            <x-button href="{{ route('countries.index') }}" variant="secondary">Voir les pays</x-button>
            <x-button href="{{ route('orders.global') }}">Voir les commandes</x-button>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat label="Nombre total de pays" :value="$countryCount" tone="slate"><x-icon name="globe" /></x-stat>
        <x-stat label="Nombre total de services" :value="$serviceCount" tone="blue"><x-icon name="services" /></x-stat>
        <x-stat label="Nombre total de commandes" :value="$summary['total']" tone="blue"><x-icon name="orders" /></x-stat>
        <x-stat label="Commandes en attente" :value="$summary['pending']" tone="orange"><x-icon name="alert" /></x-stat>
        <x-stat label="Commandes validées" :value="$summary['validated']" tone="blue"><x-icon name="check" /></x-stat>
        <x-stat label="Transfert en attente" :value="$summary['transfer_pending']" tone="purple"><x-icon name="refresh" /></x-stat>
        <x-stat label="Commandes transférées" :value="$summary['transferred']" tone="green"><x-icon name="check" /></x-stat>
        <x-stat label="Commandes rejetées" :value="$summary['rejected']" tone="red"><x-icon name="close" /></x-stat>
        <x-stat label="Erreurs de transfert" :value="$summary['failed']" tone="rose"><x-icon name="alert" /></x-stat>
    </div>

    <div class="mt-6">
        <x-status-legend />
    </div>

    <div class="mt-6 grid gap-4 xl:grid-cols-5">
        <x-card title="Commandes par pays" class="xl:col-span-3">
            @if (collect($ordersByCountry['values'])->sum() === 0)
                <x-empty title="Aucune commande" description="Les graphiques apparaîtront dès qu'un pays aura des commandes." />
            @else
                @php
                    $countryChart = [
                        'type' => 'bar',
                        'data' => [
                            'labels' => $ordersByCountry['labels'],
                            'datasets' => [[
                                'label' => 'Commandes',
                                'data' => $ordersByCountry['values'],
                                'backgroundColor' => '#2563eb',
                                'borderRadius' => 8,
                            ]],
                        ],
                        'options' => [
                            'plugins' => ['legend' => ['display' => false]],
                            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
                        ],
                    ];
                @endphp
                <canvas class="max-h-72" data-chart='@json($countryChart)'></canvas>
            @endif
        </x-card>
        <x-card title="Répartition des statuts" class="xl:col-span-2">
            @if ($summary['total'] === 0)
                <x-empty title="Aucune donnée" />
            @else
                @php
                    $statusDistribution = [
                        'type' => 'doughnut',
                        'data' => [
                            'labels' => $statusChart['labels'],
                            'datasets' => [[
                                'data' => $statusChart['values'],
                                'backgroundColor' => ['#f97316', '#2563eb', '#7c3aed', '#16a34a', '#dc2626', '#9f1239'],
                            ]],
                        ],
                        'options' => ['plugins' => ['legend' => ['position' => 'bottom']]],
                    ];
                @endphp
                <canvas class="max-h-72" data-chart='@json($statusDistribution)'></canvas>
            @endif
        </x-card>
    </div>

    <div class="mt-6 grid gap-4 xl:grid-cols-2">
        <x-card title="Commandes récentes" description="Dernières commandes reçues, tous pays confondus.">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="pb-3 pr-4 font-medium">Référence</th>
                            <th class="pb-3 pr-4 font-medium">Client</th>
                            <th class="pb-3 pr-4 font-medium">Pays</th>
                            <th class="pb-3 font-medium">Statut</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($recentOrders as $order)
                            <tr>
                                <td class="py-3 pr-4 font-medium text-slate-900">
                                    <a class="hover:text-blue-700" href="{{ route('countries.orders.show', [$order->service->country, $order]) }}">{{ $order->reference }}</a>
                                </td>
                                <td class="py-3 pr-4">{{ $order->customer_name }}</td>
                                <td class="py-3 pr-4">{{ $order->service->country->flag }} {{ $order->service->country->name }}</td>
                                <td class="py-3"><x-status-badge :status="$order->displayStatus()" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-6 text-slate-500">Aucune commande pour le moment.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        <x-card title="Performance par pays">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="pb-3 pr-4 font-medium">Pays</th>
                            <th class="pb-3 pr-4 font-medium">Commandes</th>
                            <th class="pb-3 pr-4 font-medium">En attente</th>
                            <th class="pb-3 font-medium">Transférées</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($countries as $country)
                            <tr>
                                <td class="py-3 pr-4">
                                    <a class="font-medium text-slate-900 hover:text-blue-700" href="{{ route('countries.dashboard', $country) }}">{{ $country->flag }} {{ $country->name }}</a>
                                </td>
                                <td class="py-3 pr-4">{{ $country->orders_count }}</td>
                                <td class="py-3 pr-4">{{ $country->pending_orders_count }}</td>
                                <td class="py-3">{{ $country->transferred_orders_count }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>

    <x-card class="mt-4" title="Activité de synchronisation récente">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="pb-3 pr-4 font-medium">Type</th>
                        <th class="pb-3 pr-4 font-medium">Service</th>
                        <th class="pb-3 pr-4 font-medium">Date</th>
                        <th class="pb-3 font-medium">Résultat</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($recentSyncs as $sync)
                        <tr>
                            <td class="py-3 pr-4">{{ $sync->typeLabel() }}</td>
                            <td class="py-3 pr-4">{{ $sync->service?->name ?? '—' }}</td>
                            <td class="py-3 pr-4">{{ $sync->started_at?->translatedFormat('d M Y à H:i') }}</td>
                            <td class="py-3"><x-badge :tone="$sync->resultTone()">{{ $sync->resultLabel() }}</x-badge></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-6 text-slate-500">Aucune synchronisation enregistrée.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
@endsection
