@extends('layouts.app')

@section('title', 'Paramètres')
@section('breadcrumbs')
    <a class="hover:text-slate-800" href="{{ route('dashboard') }}">Tableau de bord</a>
    <span>/</span>
    <span class="text-slate-700">Paramètres</span>
@endsection

@section('content')
    <div class="grid gap-4 xl:grid-cols-3">
        <x-card class="xl:col-span-2" title="Configuration générale" description="Informations de l'application et préférences communes.">
            <form method="POST" action="{{ route('settings.update') }}" class="grid gap-4 md:grid-cols-2">
                @csrf
                @method('PUT')
                <x-input label="Nom de l'application" name="company_name" :value="$settings->company_name" required />
                <x-input label="E-mail de contact" name="support_email" type="email" :value="$settings->support_email" />
                <x-input label="Devise par défaut" name="default_currency" :value="$settings->default_currency" maxlength="3" required />
                <x-input label="Intervalle de synchronisation (minutes)" name="sync_interval_minutes" type="number" min="5" max="1440" :value="$settings->sync_interval_minutes" required />
                <x-select label="Lignes par page" name="rows_per_page">
                    @foreach ([10, 15, 25, 50] as $size)
                        <option value="{{ $size }}" @selected((int) old('rows_per_page', auth()->user()->rows_per_page) === $size)>{{ $size }}</option>
                    @endforeach
                </x-select>
                <div class="md:col-span-2">
                    <x-button>Enregistrer</x-button>
                </div>
            </form>
        </x-card>

        <div class="space-y-4">
            <x-card title="Application">
                <dl class="space-y-3 text-sm">
                    <div><dt class="text-slate-500">Environnement</dt><dd class="font-medium">{{ config('app.env') }}</dd></div>
                    <div><dt class="text-slate-500">Laravel</dt><dd class="font-medium">{{ app()->version() }}</dd></div>
                    <div><dt class="text-slate-500">PHP</dt><dd class="font-medium">{{ PHP_VERSION }}</dd></div>
                    <div><dt class="text-slate-500">Fuseau</dt><dd class="font-medium">{{ config('app.timezone') }}</dd></div>
                </dl>
            </x-card>
            <x-card title="Profil Super Admin">
                <p class="text-sm text-slate-600">{{ auth()->user()->name }}</p>
                <p class="text-sm text-slate-500">{{ auth()->user()->email }}</p>
                <x-button class="mt-4" href="{{ route('profile.edit') }}" variant="secondary">Modifier mon profil</x-button>
            </x-card>
        </div>
    </div>

    <div class="mt-4">
        @if ($sheetsReady)
            <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                Google Sheets est configuré{{ $accountEmail ? ' pour '.$accountEmail : '' }}.
            </div>
        @else
            <x-sheets-setup :message="$sheetsError" :email="$accountEmail" />
        @endif
    </div>
@endsection
