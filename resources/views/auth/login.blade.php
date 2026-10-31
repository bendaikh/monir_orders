@extends('layouts.guest')

@section('title', 'Connexion')

@section('content')
    <div class="flex min-h-screen items-center justify-center px-4 py-12">
        <div class="w-full max-w-md">
            <div class="mb-6 text-center">
                <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-600 text-base font-bold text-white">MO</span>
                <h1 class="mt-4 text-2xl font-semibold text-slate-900">Monir Orders</h1>
                <p class="mt-1 text-sm text-slate-500">Espace Super Admin</p>
            </div>

            <form method="POST" action="{{ route('login') }}" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" x-data="{ show: false, loading: false }" @submit="loading = true">
                @csrf
                <div class="space-y-4">
                    <x-input label="Adresse e-mail" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required />

                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-slate-700">Mot de passe</span>
                        <span class="relative block">
                            <input :type="show ? 'text' : 'password'" name="password" required autocomplete="current-password" class="w-full rounded-lg border border-slate-200 px-3 py-2 pr-16 text-sm shadow-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
                            <button type="button" class="absolute inset-y-0 right-2 text-xs font-medium text-slate-500" @click="show = !show" x-text="show ? 'Masquer' : 'Afficher'"></button>
                        </span>
                        @error('password')
                            <span class="mt-1 block text-sm text-red-600">{{ $message }}</span>
                        @enderror
                    </label>

                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" name="remember" value="1" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" @checked(old('remember'))>
                        Se souvenir de moi
                    </label>
                </div>

                <button type="submit" class="mt-6 inline-flex w-full items-center justify-center rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60" :disabled="loading">
                    <span x-show="!loading">Se connecter</span>
                    <span x-cloak x-show="loading">Connexion...</span>
                </button>
            </form>

            <p class="mt-4 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-center text-xs text-slate-500">
                Compte de démonstration : admin@monir-orders.local · mot de passe : password
            </p>
        </div>
    </div>
@endsection
