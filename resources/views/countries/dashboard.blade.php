@extends('layouts.app')

@section('title', $country->name)
@section('breadcrumbs')
    <a class="hover:text-slate-800" href="{{ route('countries.index') }}">Pays</a>
    <span>/</span>
    <span class="text-slate-700">{{ $country->flag }} {{ $country->name }}</span>
@endsection

@section('content')
    @if ($hasDemo)
        <x-demo-banner />
    @endif

    <p class="mb-6 text-sm text-slate-500">Espace de gestion dédié à {{ $country->name }} ({{ $country->currency }}).</p>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <x-stat label="Total des commandes" :value="$summary['total']" tone="blue"><x-icon name="orders" /></x-stat>
        <x-stat label="En attente de validation" :value="$summary['pending']" tone="orange"><x-icon name="alert" /></x-stat>
        <x-stat label="Commandes validées" :value="$summary['validated']" tone="blue"><x-icon name="check" /></x-stat>
        <x-stat label="Commandes transférées" :value="$summary['transferred']" tone="green"><x-icon name="check" /></x-stat>
        <x-stat label="Commandes rejetées" :value="$summary['rejected']" tone="red"><x-icon name="close" /></x-stat>
        <x-stat label="Services actifs" :value="$activeServices" tone="slate"><x-icon name="services" /></x-stat>
    </div>

    <div class="mt-6 grid gap-4 xl:grid-cols-2">
        <x-card title="Dernières commandes reçues">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="pb-3 pr-4 font-medium">Référence</th>
                            <th class="pb-3 pr-4 font-medium">Client</th>
                            <th class="pb-3 pr-4 font-medium">Service</th>
                            <th class="pb-3 font-medium">Statut</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($recentOrders as $order)
                            <tr>
                                <td class="py-3 pr-4 font-medium"><a class="hover:text-blue-700" href="{{ route('countries.orders.show', [$country, $order]) }}">{{ $order->reference }}</a></td>
                                <td class="py-3 pr-4">{{ $order->customer_name }}</td>
                                <td class="py-3 pr-4">{{ $order->service->name }}</td>
                                <td class="py-3"><x-status-badge :status="$order->displayStatus()" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-6 text-slate-500">Aucune commande dans ce pays.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        <x-card title="Activité récente des services">
            <div class="space-y-3">
                @forelse ($recentSyncs as $sync)
                    <article class="rounded-xl border border-slate-100 px-4 py-3">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-medium text-slate-900">{{ $sync->service?->name }}</p>
                            <x-badge :tone="$sync->resultTone()">{{ $sync->resultLabel() }}</x-badge>
                        </div>
                        <p class="mt-1 text-sm text-slate-500">{{ $sync->typeLabel() }} · {{ $sync->started_at?->translatedFormat('d M à H:i') }}</p>
                        <p class="mt-1 text-sm text-slate-600">{{ $sync->message }}</p>
                    </article>
                @empty
                    <x-empty title="Aucune activité" description="Les imports et tests de connexion apparaîtront ici." />
                @endforelse
            </div>
        </x-card>
    </div>
@endsection
