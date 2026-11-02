@props(['service', 'block' => false])
@if ($service->usesApi())
    <x-badge :tone="$service->apiStatusTone()">Simon Express (API) · {{ $service->apiStatusLabel() }}</x-badge>
    <p class="mt-1 text-xs text-slate-500">{{ $service->destinationIntegration?->last_tested_at ? 'Testé le '.$service->destinationIntegration->last_tested_at->format('d/m/Y H:i') : 'Système (API)' }}</p>
@else
    <x-badge :tone="$service->destinationIntegration?->statusTone() ?? 'slate'">{{ $service->destinationIntegration?->statusLabel() ?? 'Non configuré' }}</x-badge>
    <p class="mt-1 text-xs text-slate-500">{{ $service->destinationIntegration?->worksheet_name ?: 'Onglet non défini' }}</p>
@endif
