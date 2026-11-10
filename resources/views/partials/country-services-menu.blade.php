<div x-data="countryServicesMenu()" class="inline-flex">
    <button
        type="button"
        x-ref="btn"
        @click.stop="toggle()"
        :aria-expanded="open.toString()"
        aria-haspopup="dialog"
        aria-controls="country-services-{{ $country->id }}"
        class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-700 ring-1 ring-slate-200 hover:bg-slate-100"
        title="Services"
        aria-label="Voir les services de {{ $country->name }}"
    >
        <x-icon name="eye" class="h-5 w-5" />
    </button>
    <template x-teleport="body">
        <div
            x-show="open"
            x-cloak
            x-ref="panel"
            id="country-services-{{ $country->id }}"
            role="dialog"
            aria-label="Services de {{ $country->name }}"
            @click.stop
            :style="style"
            class="fixed z-40 max-h-[min(24rem,calc(100vh-1rem))] overflow-y-auto rounded-xl border border-slate-200/80 bg-white p-2 shadow-xl shadow-slate-900/10"
        >
            <p class="px-2 py-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Services · {{ $country->name }}</p>
            @forelse ($country->services as $service)
                @php [$live, $liveError] = $liveCounts[$service->id] ?? [null, null]; @endphp
                <div class="rounded-lg px-2 py-2 hover:bg-slate-50">
                    <div class="flex items-start justify-between gap-3">
                        <a class="min-w-0 font-medium text-slate-900 hover:text-blue-700" href="{{ route('countries.services.show', [$country, $service]) }}">{{ $service->name }}</a>
                        <a class="shrink-0 text-xs font-semibold text-indigo-600 hover:text-indigo-700" href="{{ route('countries.services.show', [$country, $service]) }}">Détails</a>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">{{ $service->destinationTypeLabel() }}</p>
                    <div class="mt-2 flex items-center justify-between gap-2">
                        <p class="text-xs text-slate-600">
                            En attente d'envoi
                            @if ($live)
                                <span class="{{ $live['pending'] > 0 ? 'font-semibold text-orange-700' : 'font-semibold text-green-700' }}">{{ $live['pending'] }}</span>
                            @else
                                <span class="text-slate-400" title="{{ $liveError }}">—</span>
                            @endif
                        </p>
                        @include('partials.service-push-form', [
                            'country' => $country,
                            'service' => $service,
                            'pendingCount' => is_array($live) ? $live['pending'] : null,
                            'pushActive' => (bool) ($activeRuns[$service->id] ?? false),
                        ])
                    </div>
                </div>
            @empty
                <p class="px-2 py-3 text-sm text-slate-500">Aucun service</p>
            @endforelse
        </div>
    </template>
</div>
