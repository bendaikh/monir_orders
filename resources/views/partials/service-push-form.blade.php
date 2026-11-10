{{-- Same « Valider et push » control as the services table: same route, confirmation and status polling. --}}
@php
    $pendingN = $pendingCount;
@endphp
<div x-data="{
    active: @js((bool) $pushActive),
    init() { if (this.active) this.poll(); },
    poll() {
        const t = setInterval(async () => {
            try {
                const r = await fetch(@js(route('countries.services.push-status', [$country, $service])), { headers: { 'Accept': 'application/json' } });
                const d = await r.json();
                if (!d.run || !d.run.active) { clearInterval(t); window.location.reload(); }
            } catch (e) {}
        }, 4000);
    },
}">
    <template x-if="active">
        <span class="inline-flex h-8 items-center gap-1 rounded-lg bg-blue-50 px-2 text-xs font-medium text-blue-700" title="Envoi en cours en arrière-plan"><x-icon name="refresh" class="h-4 w-4 animate-spin" /> Envoi…</span>
    </template>
    <form x-show="!active" method="POST" action="{{ route('countries.services.validate-all', [$country, $service]) }}" @submit="if (!confirm(@js('Valider et envoyer les '.($pendingN ?? '').' commande(s) en attente de '.$service->name.' ? Les doublons seront supprimés et non envoyés.'))) $event.preventDefault()">
        @csrf
        <button type="submit" @disabled($pendingN === 0) class="inline-flex h-8 items-center gap-1 rounded-lg bg-green-600 px-2.5 text-xs font-semibold text-white shadow-sm hover:bg-green-700 disabled:cursor-not-allowed disabled:bg-slate-200 disabled:text-slate-400" title="Valider et push{{ $pendingN !== null ? ' ('.$pendingN.' en attente)' : '' }}" aria-label="Valider et push"><x-icon name="check-circle" class="h-4 w-4" /> Push</button>
    </form>
</div>
