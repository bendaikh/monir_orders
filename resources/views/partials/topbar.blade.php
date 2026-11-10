<header class="sticky top-0 z-20 flex h-16 items-center justify-between gap-2 border-b border-slate-200/70 bg-white/75 px-4 backdrop-blur-xl sm:gap-4 sm:px-6 lg:px-8">
    <div class="flex min-w-0 flex-1 items-center gap-2 sm:gap-3">
        <button type="button" class="shrink-0 rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden" @click="sidebar = true" aria-label="Ouvrir le menu">
            <x-icon name="menu" />
        </button>
        <div class="min-w-0">
            <nav class="flex items-center gap-1.5 truncate text-xs text-slate-500">
                @yield('breadcrumbs')
            </nav>
            <h1 class="truncate text-base font-bold tracking-tight text-slate-900 sm:text-xl">@yield('title', 'Tableau de bord')</h1>
        </div>
    </div>

    <div class="flex min-w-0 items-center gap-1.5 sm:gap-2">
        @if (request()->routeIs('dashboard') && isset($filters, $services))
            @include('partials.dashboard-service-select')
        @endif

        <div class="relative shrink-0">
            <button type="button" class="relative rounded-lg p-2 text-slate-600 hover:bg-slate-100" @click="notifications = !notifications; profile = false" aria-label="Notifications">
                <x-icon name="bell" />
                @if (($notificationItems ?? collect())->isNotEmpty())
                    <span class="absolute right-1.5 top-1.5 h-2 w-2 rounded-full bg-red-500"></span>
                @endif
            </button>
            <div x-cloak x-show="notifications" @click.outside="notifications = false" class="absolute right-0 mt-2 w-80 overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-soft shadow-lg">
                <div class="border-b border-slate-100 px-4 py-3 text-sm font-semibold text-slate-900">Notifications</div>
                @forelse ($notificationItems ?? [] as $item)
                    <a href="{{ $item['url'] }}" class="block border-b border-slate-100 px-4 py-3 hover:bg-slate-50">
                        <p class="text-sm font-medium text-slate-800">{{ $item['title'] }}</p>
                        <p class="mt-1 line-clamp-2 text-xs text-slate-500">{{ $item['meta'] }}</p>
                        <p class="mt-1 text-xs text-slate-400">{{ $item['time']?->translatedFormat('d M à H:i') }}</p>
                    </a>
                @empty
                    <p class="px-4 py-6 text-sm text-slate-500">Aucune alerte de transfert ou de synchronisation.</p>
                @endforelse
            </div>
        </div>

        <div class="relative shrink-0">
            <button type="button" class="flex items-center gap-2 rounded-lg py-1 pl-1 pr-2 hover:bg-slate-100" @click="profile = !profile; notifications = false">
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-violet-600 text-xs font-semibold text-white ring-2 ring-white shadow">
                    {{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}
                </span>
                <span class="hidden text-left sm:block">
                    <span class="block text-sm font-medium text-slate-800">{{ auth()->user()->name }}</span>
                    <span class="block text-xs text-slate-500">Super admin</span>
                </span>
            </button>
            <div x-cloak x-show="profile" @click.outside="profile = false" class="absolute right-0 mt-2 w-56 rounded-xl border border-slate-200/80 bg-white py-1 shadow-xl shadow-slate-900/10">
                <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Mon profil</a>
                <a href="{{ route('settings.index') }}" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Paramètres</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-slate-700 hover:bg-slate-50">Déconnexion</button>
                </form>
            </div>
        </div>
    </div>
</header>
