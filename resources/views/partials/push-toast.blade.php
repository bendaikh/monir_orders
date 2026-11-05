@if ($report = session('push_report'))
    @php $ok = ($report['status'] ?? '') !== 'failed' && ($report['failed'] ?? 0) === 0 && ($report['tone'] ?? '') !== 'error'; @endphp
    <div
        x-data="{ open: false }"
        x-init="setTimeout(() => open = true, 150)"
        x-show="open"
        x-cloak
        x-transition:enter="transform transition ease-out duration-500"
        x-transition:enter-start="translate-x-full opacity-0"
        x-transition:enter-end="translate-x-0 opacity-100"
        x-transition:leave="transform transition ease-in duration-300"
        x-transition:leave-start="translate-x-0 opacity-100"
        x-transition:leave-end="translate-x-full opacity-0"
        class="fixed right-4 top-4 z-50 w-full max-w-sm rounded-xl border p-4 text-sm shadow-2xl shadow-slate-900/15 backdrop-blur {{ $ok ? 'border-green-300 bg-green-50 text-green-900' : 'border-red-300 bg-red-50 text-red-900' }}"
        role="status"
    >
        <div class="flex items-start justify-between gap-3">
            <div class="font-semibold">
                {{ $ok ? '✅ Envoi terminé' : '⚠️ Envoi terminé avec des erreurs' }}
                @isset($report['service'])<span class="font-normal">— {{ $report['service'] }}</span>@endisset
            </div>
            <button type="button" class="text-lg leading-none opacity-60 hover:opacity-100" @click="open = false" aria-label="Fermer">&times;</button>
        </div>
        <dl class="mt-3 grid grid-cols-2 gap-2">
            <div><dt class="text-xs opacity-70">Commandes traitées</dt><dd class="text-lg font-bold">{{ $report['today_count'] ?? $report['total'] ?? 0 }}</dd></div>
            <div><dt class="text-xs opacity-70">Envoyées avec succès</dt><dd class="text-lg font-bold">{{ $report['transferred'] ?? 0 }}</dd></div>
            <div><dt class="text-xs opacity-70">Doublons supprimés</dt><dd class="text-lg font-bold">{{ $report['duplicates'] ?? 0 }}</dd></div>
            <div><dt class="text-xs opacity-70">Échecs</dt><dd class="text-lg font-bold">{{ $report['failed'] ?? 0 }}</dd></div>
        </dl>
        @if (! empty($report['failures']))
            <ul class="mt-3 max-h-32 list-disc overflow-y-auto pl-5 text-xs">
                @foreach ($report['failures'] as $f)
                    <li>{{ $f['ref'] }} {{ $f['name'] }} : {{ $f['reason'] }}</li>
                @endforeach
            </ul>
        @elseif (($report['status'] ?? '') === 'failed' && ! empty($report['error']))
            <p class="mt-3 text-xs">{{ $report['error'] }}</p>
        @endif
        <p class="mt-3 text-xs opacity-70">{{ $report['finished_at'] ?? $report['created_at'] ?? '' }}</p>
    </div>
@endif
