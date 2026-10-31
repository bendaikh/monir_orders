@extends('layouts.app')

@section('title', 'Mon profil')
@section('breadcrumbs')
    <a class="hover:text-slate-800" href="{{ route('dashboard') }}">Tableau de bord</a>
    <span>/</span>
    <span class="text-slate-700">Profil</span>
@endsection

@section('content')
    <div class="grid gap-4 lg:grid-cols-2">
        <x-card title="Informations" description="Ces informations apparaissent dans l'historique des validations.">
            <form method="POST" action="{{ route('profile.update') }}" class="grid gap-4">
                @csrf
                @method('PUT')
                <x-input label="Nom" name="name" :value="auth()->user()->name" required />
                <x-input label="Adresse e-mail" name="email" type="email" :value="auth()->user()->email" required />
                <x-input label="Téléphone" name="phone" :value="auth()->user()->phone" />
                <div><x-button>Enregistrer</x-button></div>
            </form>
        </x-card>

        <x-card title="Mot de passe">
            <form method="POST" action="{{ route('profile.password') }}" class="grid gap-4">
                @csrf
                @method('PUT')
                <x-input label="Mot de passe actuel" name="current_password" type="password" required />
                <x-input label="Nouveau mot de passe" name="password" type="password" required />
                <x-input label="Confirmation" name="password_confirmation" type="password" required />
                <div><x-button>Modifier le mot de passe</x-button></div>
            </form>
        </x-card>
    </div>
@endsection
