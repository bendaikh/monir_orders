@php
    $company = $appSettings->company_name ?? config('app.name');
    $country = $currentCountry instanceof \App\Models\Country ? $currentCountry : null;
    $link = 'group relative flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-400 transition hover:bg-white/5 hover:text-white';
    $active = 'relative flex items-center gap-3 rounded-lg bg-gradient-to-r from-indigo-500/25 to-indigo-500/5 px-3 py-2.5 text-sm font-semibold text-white ring-1 ring-inset ring-indigo-400/20 before:absolute before:-left-3 before:top-1.5 before:bottom-1.5 before:w-1 before:rounded-r-full before:bg-indigo-400';
@endphp

<aside
    class="fixed inset-y-0 left-0 z-40 flex w-72 flex-col bg-gradient-to-b from-slate-950 via-slate-900 to-slate-950 text-slate-300 shadow-2xl shadow-slate-950/30 transition-transform duration-300 ease-out lg:translate-x-0 lg:shadow-none"
    :class="sidebar ? 'translate-x-0' : '-translate-x-full'"
>
    <div class="flex h-16 items-center gap-3 border-b border-white/10 px-5">
        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-400 to-violet-600 text-sm font-bold text-white shadow-lg shadow-indigo-500/30">MO</span>
        <div class="min-w-0">
            <p class="truncate text-sm font-semibold text-white">{{ $company }}</p>
            <p class="text-xs text-slate-400">Gestion des commandes</p>
        </div>
    </div>

    @if ($country)
        <div class="mx-3 mt-4 rounded-xl border border-white/10 bg-white/5 px-4 py-3">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Pays sélectionné</p>
            <p class="mt-1 flex items-center gap-2 text-sm font-semibold text-white">
                <span class="text-lg">{{ $country->flag }}</span>
                {{ $country->name }}
                <span class="text-slate-400">{{ $country->code }}</span>
            </p>
        </div>
    @endif

    <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-5">
        @if ($country)
            <div>
                <p class="px-3 pb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Tableau de bord</p>
                <a href="{{ route('countries.dashboard', $country) }}" @click="sidebar = false" class="{{ request()->routeIs('countries.dashboard') ? $active : $link }}">
                    <x-icon name="dashboard" class="h-5 w-5" /> Tableau de bord
                </a>
            </div>
            <div>
                <p class="px-3 pb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Services</p>
                <div class="space-y-1">
                    <a href="{{ route('countries.services.index', $country) }}" @click="sidebar = false" class="{{ request()->routeIs('countries.services.*') ? $active : $link }}">
                        <x-icon name="services" /> Gestion des services
                    </a>
                    <a href="{{ route('countries.sheets.index', $country) }}" @click="sidebar = false" class="{{ request()->routeIs('countries.sheets.*') ? $active : $link }}">
                        <x-icon name="sheet" /> Intégration Google Sheets
                    </a>
                </div>
            </div>
            <div>
                <p class="px-3 pb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Navigation</p>
                <a href="{{ route('countries.index') }}" @click="sidebar = false" class="{{ $link }}">
                    <x-icon name="back" /> Retour aux pays
                </a>
            </div>
        @else
            <div class="space-y-1">
                <a href="{{ route('dashboard') }}" @click="sidebar = false" class="{{ request()->routeIs('dashboard') ? $active : $link }}">
                    <x-icon name="dashboard" /> Tableau de bord
                </a>
                <a href="{{ route('countries.index') }}" @click="sidebar = false" class="{{ request()->routeIs('countries.*') ? $active : $link }}">
                    <x-icon name="globe" /> Gestion des pays
                </a>
                <a href="{{ route('settings.index') }}" @click="sidebar = false" class="{{ request()->routeIs('settings.*') ? $active : $link }}">
                    <x-icon name="settings" /> Paramètres
                </a>
                <a href="{{ route('profile.edit') }}" @click="sidebar = false" class="{{ request()->routeIs('profile.*') ? $active : $link }}">
                    <x-icon name="user" /> Mon profil
                </a>
            </div>
        @endif
    </nav>

    <div class="border-t border-white/10 p-3">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="{{ $link }} w-full">
                <x-icon name="logout" /> Déconnexion
            </button>
        </form>
    </div>
</aside>
