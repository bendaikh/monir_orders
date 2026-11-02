@extends('layouts.app')

@section('title', 'Intégration Google Sheets')
@section('breadcrumbs')
    <a class="hover:text-slate-800" href="{{ route('countries.dashboard', $country) }}">{{ $country->name }}</a>
    <span>/</span>
    <span class="text-slate-700">Google Sheets</span>
@endsection

@section('content')
    @if (! $sheetsReady)
        <div class="mb-5">
            <x-sheets-setup :message="$sheetsError" :email="$accountEmail" />
        </div>
    @else
        <div class="mb-5 rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            Compte de service prêt{{ $accountEmail ? ' : '.$accountEmail : '' }}. Partagez chaque tableur avec cette adresse.
        </div>
    @endif

    <div class="space-y-4">
        @forelse ($services as $service)
            <x-card :title="$service->name" :description="$service->description">
                <x-slot:action>
                    <x-button href="{{ route('countries.services.edit', [$country, $service]) }}" variant="secondary">Configurer</x-button>
                </x-slot:action>
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="rounded-xl border border-slate-200 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Source — commandes entrantes</p>
                        <p class="mt-2 text-sm text-slate-700">{{ $service->sourceIntegration?->worksheet_name ?: 'Onglet non défini' }}</p>
                        <div class="mt-2"><x-badge :tone="$service->sourceIntegration?->statusTone() ?? 'slate'">{{ $service->sourceIntegration?->statusLabel() ?? 'Non configuré' }}</x-badge></div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('countries.services.test', [$country, $service, 'source']) }}">@csrf<x-button variant="secondary">Tester la connexion</x-button></form>
                            <form method="POST" action="{{ route('countries.services.import', [$country, $service]) }}">@csrf<x-button>Importer les commandes</x-button></form>
                        </div>
                    </div>
                    <div class="rounded-xl border border-slate-200 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Destination — commandes validées</p>
                        <div class="mt-2"><x-destination-status :service="$service" /></div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('countries.services.test', [$country, $service, 'destination']) }}">@csrf<x-button variant="secondary">Tester la connexion</x-button></form>
                            <form method="POST" action="{{ route('countries.services.test-send', [$country, $service]) }}">@csrf<x-button variant="secondary">Tester l'envoi</x-button></form>
                        </div>
                    </div>
                </div>
            </x-card>
        @empty
            <x-empty title="Aucun service" description="Ajoutez un service partenaire avant de configurer Google Sheets.">
                <x-button href="{{ route('countries.services.create', $country) }}">Ajouter un service</x-button>
            </x-empty>
        @endforelse
    </div>
@endsection
