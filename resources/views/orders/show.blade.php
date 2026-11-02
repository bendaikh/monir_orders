@extends('layouts.app')

@section('title', $order->reference)
@section('breadcrumbs')
    <a class="hover:text-slate-800" href="{{ route('countries.orders.index', $country) }}">Commandes</a>
    <span>/</span>
    <span class="text-slate-700">{{ $order->reference }}</span>
@endsection

@section('content')
    @if ($order->is_demo)
        <x-demo-banner />
    @endif

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2">
            <x-status-badge :status="$order->displayStatus()" />
            @if ($order->is_demo)
                <x-badge tone="blue">Démonstration</x-badge>
            @endif
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($order->canValidate())
                <form method="POST" action="{{ route('countries.validation.validate', [$country, $order]) }}" @submit="if (!confirm('Valider cette commande et l\'envoyer vers la feuille de destination ?')) $event.preventDefault()">
                    @csrf
                    <x-button>Valider</x-button>
                </form>
            @endif
            @if ($order->canRetryTransfer() && ! $order->canValidate())
                <form method="POST" action="{{ route('countries.orders.retry', [$country, $order]) }}">
                    @csrf
                    <x-button>Réessayer le transfert</x-button>
                </form>
            @endif
            <x-button href="{{ route('countries.history.index', [$country, 'reference' => $order->reference]) }}" variant="secondary">Historique</x-button>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card title="Client">
            @if ($order->canEdit())
                <form method="POST" action="{{ route('countries.orders.update', [$country, $order]) }}" class="grid gap-4">
                    @csrf
                    @method('PUT')
                    <x-input label="Nom du client" name="customer_name" :value="$order->customer_name" required />
                    <x-input label="Téléphone" name="phone" :value="$order->phone" />
                    <x-input label="Ville" name="city" :value="$order->city" />
                    <x-textarea label="Adresse" name="address" :value="$order->address" />
                    <x-input label="Montant total" name="total_amount" :value="$order->total_amount" />
                    <x-input label="Date de commande" name="ordered_at" type="datetime-local" :value="$order->ordered_at?->format('Y-m-d\TH:i')" />
                    <div><x-button>Enregistrer</x-button></div>
                </form>
            @else
                <dl class="space-y-3 text-sm">
                    <div><dt class="text-slate-500">Nom</dt><dd class="font-medium">{{ $order->customer_name }}</dd></div>
                    <div><dt class="text-slate-500">Téléphone</dt><dd class="font-medium">{{ $order->phone ?: '—' }}</dd></div>
                    <div><dt class="text-slate-500">Ville</dt><dd class="font-medium">{{ $order->city ?: '—' }}</dd></div>
                    <div><dt class="text-slate-500">Adresse</dt><dd class="font-medium">{{ $order->address ?: '—' }}</dd></div>
                    <div><dt class="text-slate-500">Montant</dt><dd class="font-medium">{{ $order->formattedTotal() }}</dd></div>
                </dl>
            @endif
        </x-card>

        <x-card title="Contexte">
            <dl class="space-y-3 text-sm">
                <div><dt class="text-slate-500">Pays</dt><dd class="font-medium">{{ $country->flag }} {{ $country->name }}</dd></div>
                <div><dt class="text-slate-500">Service partenaire</dt><dd class="font-medium">{{ $order->service->name }}</dd></div>
                <div><dt class="text-slate-500">Référence</dt><dd class="font-medium">{{ $order->reference }}</dd></div>
                <div><dt class="text-slate-500">Reçue le</dt><dd class="font-medium">{{ $order->received_at?->translatedFormat('d M Y à H:i') ?: '—' }}</dd></div>
                <div><dt class="text-slate-500">Décidée par</dt><dd class="font-medium">{{ $order->decidedBy?->name ?: '—' }}</dd></div>
                @if ($order->rejection_reason)
                    <div><dt class="text-slate-500">Motif de rejet</dt><dd class="font-medium text-red-700">{{ $order->rejection_reason }}</dd></div>
                @endif
            </dl>
        </x-card>

        <x-card title="Feuille source">
            <dl class="space-y-3 text-sm">
                <div><dt class="text-slate-500">Onglet</dt><dd class="font-medium">{{ $order->service->sourceIntegration?->worksheet_name ?: '—' }}</dd></div>
                <div><dt class="text-slate-500">Identifiant</dt><dd class="break-all font-medium">{{ $order->service->sourceIntegration?->spreadsheet_id ?: '—' }}</dd></div>
                <div><dt class="text-slate-500">Ligne</dt><dd class="font-medium">{{ $order->source_row_number ?: '—' }}</dd></div>
                <div><dt class="text-slate-500">Connexion</dt><dd class="mt-1"><x-badge :tone="$order->service->sourceIntegration?->statusTone() ?? 'slate'">{{ $order->service->sourceIntegration?->statusLabel() ?? 'Non configuré' }}</x-badge></dd></div>
            </dl>
        </x-card>

        <x-card :title="$order->service->usesApi() ? 'Destination : Simon Express (API)' : 'Feuille de destination'">
            <dl class="space-y-3 text-sm">
                @if ($order->service->usesApi())
                    <div><dt class="text-slate-500">Code colis</dt><dd class="font-medium">{{ $order->parcel_code ?: '—' }}</dd></div>
                    @if ($order->api_error)
                        <div><dt class="text-slate-500">Erreur</dt><dd class="font-medium text-red-600">{{ $order->api_error }}</dd></div>
                    @endif
                @else
                <div><dt class="text-slate-500">Onglet</dt><dd class="font-medium">{{ $order->service->destinationIntegration?->worksheet_name ?: '—' }}</dd></div>
                <div><dt class="text-slate-500">Identifiant</dt><dd class="break-all font-medium">{{ $order->service->destinationIntegration?->spreadsheet_id ?: '—' }}</dd></div>
                @endif
                <div><dt class="text-slate-500">Transférée le</dt><dd class="font-medium">{{ $order->transferred_at?->translatedFormat('d M Y à H:i') ?: '—' }}</dd></div>
            </dl>
        </x-card>
    </div>

    <x-card class="mt-4" title="Articles">
        @if ($order->items->isEmpty())
            <p class="text-sm text-slate-500">Aucun article détaillé pour cette commande.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="pb-3 pr-4 font-medium">Article</th>
                            <th class="pb-3 pr-4 font-medium">Quantité</th>
                            <th class="pb-3 pr-4 font-medium">Prix unitaire</th>
                            <th class="pb-3 font-medium">Total ligne</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($order->items as $item)
                            <tr>
                                <td class="py-3 pr-4">{{ $item->name }}</td>
                                <td class="py-3 pr-4">{{ $item->quantity }}</td>
                                <td class="py-3 pr-4">{{ $item->formattedUnitPrice() }}</td>
                                <td class="py-3">{{ $item->formattedLineTotal() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-4 text-sm font-semibold text-slate-900">Total : {{ $order->formattedTotal() }}</p>
        @endif
    </x-card>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <x-card title="Historique de validation">
            <div class="space-y-3">
                @forelse ($order->validationLogs as $log)
                    <article class="rounded-xl border border-slate-100 px-4 py-3 text-sm">
                        <div class="flex items-center justify-between">
                            <x-badge :tone="$log->decision === 'validated' ? 'blue' : 'red'">{{ $log->decisionLabel() }}</x-badge>
                            <span class="text-slate-500">{{ $log->created_at->translatedFormat('d M Y à H:i') }}</span>
                        </div>
                        <p class="mt-2 text-slate-700">{{ $log->user?->name ?: 'Système' }}</p>
                        @if ($log->reason)
                            <p class="mt-1 text-slate-500">{{ $log->reason }}</p>
                        @endif
                    </article>
                @empty
                    <p class="text-sm text-slate-500">Aucune décision enregistrée.</p>
                @endforelse
            </div>
        </x-card>
        <x-card title="Historique des transferts">
            <div class="space-y-3">
                @forelse ($order->transferLogs as $log)
                    <article class="rounded-xl border border-slate-100 px-4 py-3 text-sm">
                        <div class="flex items-center justify-between gap-2">
                            <x-badge :tone="$log->statusTone()">{{ $log->statusLabel() }} · tentative {{ $log->attempt }}</x-badge>
                            <span class="text-slate-500">{{ $log->created_at->translatedFormat('d M à H:i') }}</span>
                        </div>
                        <p class="mt-2 text-slate-700">{{ $log->message }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $log->destination_worksheet ?: 'Destination non définie' }}</p>
                    </article>
                @empty
                    <p class="text-sm text-slate-500">Aucun transfert tenté.</p>
                @endforelse
            </div>
        </x-card>
    </div>
@endsection
