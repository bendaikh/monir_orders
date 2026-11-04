@extends('layouts.guest')

@section('title', 'Connexion')

@section('content')
    <div class="grid min-h-screen lg:grid-cols-2">
        <div class="relative hidden overflow-hidden bg-gradient-to-br from-slate-950 via-indigo-950 to-slate-900 p-12 text-white lg:flex lg:flex-col lg:justify-between">
            <div class="pointer-events-none absolute -right-24 -top-24 h-96 w-96 rounded-full bg-indigo-500/30 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-32 -left-16 h-96 w-96 rounded-full bg-violet-600/20 blur-3xl"></div>
            <div class="relative flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-400 to-violet-600 text-sm font-bold shadow-lg shadow-indigo-500/30">MO</span>
                <span class="text-lg font-semibold">Monir Orders</span>
            </div>
            <div class="relative max-w-md">
                <h2 class="text-4xl font-bold leading-tight tracking-tight">Validez et envoyez vos commandes, pays par pays.</h2>
                <p class="mt-4 text-base text-indigo-200/80">Import depuis Google Sheets, suppression des doublons et envoi vers vos partenaires de livraison, en arrière-plan.</p>
                <div class="mt-8 grid grid-cols-3 gap-3 text-sm">
                    <div class="rounded-xl border border-white/10 bg-white/5 p-3 backdrop-blur"><x-icon name="sheet" class="h-5 w-5 text-indigo-300" /><p class="mt-2 font-medium">Google Sheets</p></div>
                    <div class="rounded-xl border border-white/10 bg-white/5 p-3 backdrop-blur"><x-icon name="check" class="h-5 w-5 text-emerald-300" /><p class="mt-2 font-medium">Validation</p></div>
                    <div class="rounded-xl border border-white/10 bg-white/5 p-3 backdrop-blur"><x-icon name="arrow" class="h-5 w-5 text-violet-300" /><p class="mt-2 font-medium">Envoi</p></div>
                </div>
            </div>
            <p class="relative text-xs text-indigo-200/60">&copy; {{ date('Y') }} Monir Orders</p>
        </div>
        <div class="flex items-center justify-center px-4 py-12">
        <div class="w-full max-w-md">
            <div class="mb-8 text-center lg:text-left">
                <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 text-base font-bold text-white shadow-lg shadow-indigo-500/30 lg:hidden">MO</span>
                <h1 class="mt-4 text-3xl font-bold tracking-tight text-slate-900 lg:mt-0">Monir Orders</h1>
                <p class="mt-1 text-sm text-slate-500">Espace Super Admin</p>
            </div>

            <form method="POST" action="{{ route('login') }}" class="rounded-xl border border-slate-200/80 bg-white p-6 shadow-soft" x-data="{ show: false, loading: false }" @submit="loading = true">
                @csrf
                <div class="space-y-4">
                    <x-input label="Adresse e-mail" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required />

                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-slate-700">Mot de passe</span>
                        <span class="relative block">
                            <input :type="show ? 'text' : 'password'" name="password" required autocomplete="current-password" class="w-full rounded-lg border border-slate-300/80 px-3 py-2 pr-16 text-sm shadow-sm outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/15">
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

                <button type="submit" class="mt-6 inline-flex w-full items-center justify-center rounded-lg bg-gradient-to-b from-indigo-500 to-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-md shadow-indigo-600/25 transition hover:from-indigo-600 hover:to-indigo-700 disabled:opacity-60" :disabled="loading">
                    <span x-show="!loading">Se connecter</span>
                    <span x-cloak x-show="loading">Connexion...</span>
                </button>
            </form>

            <p class="mt-4 rounded-xl border border-slate-200/80 bg-white/70 px-4 py-3 text-center text-xs text-slate-500">
                Compte de démonstration : admin@monir-orders.local · mot de passe : password
            </p>
        </div>
        </div>
    </div>
@endsection
