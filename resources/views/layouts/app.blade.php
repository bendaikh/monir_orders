<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Tableau de bord') — {{ $appSettings->company_name ?? config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body
    class="min-h-screen bg-slate-100 font-sans text-slate-800 antialiased"
    x-data="{ sidebar: false, notifications: false, profile: false }"
    @keydown.escape.window="sidebar = false; notifications = false; profile = false"
>
    <div x-cloak x-show="sidebar" class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden" @click="sidebar = false"></div>
    @include('partials.sidebar')
    <div class="lg:pl-72">
        @include('partials.topbar')
        <main class="px-4 py-6 sm:px-6 lg:px-8">
            @if ($currentCountry instanceof \App\Models\Country && ! $currentCountry->is_active)
                <div class="mb-5 rounded-2xl border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-800">
                    {{ $currentCountry->name }} est inactif. Les synchronisations planifiées sont suspendues pour ce pays.
                </div>
            @endif
            @include('partials.flash')
            @yield('content')
        </main>
    </div>
</body>
</html>
