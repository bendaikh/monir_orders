<section id="dashboard-filters" aria-label="Filtres du tableau de bord" class="w-full rounded-xl border border-slate-200/80 bg-white p-3 shadow-soft sm:p-4 xl:w-[36rem]">
    <form method="GET" action="{{ route('dashboard') }}" autocomplete="off">
        @if ($filters->period)
            <input type="hidden" name="period" value="{{ $filters->period }}">
        @endif
        @if ($filters->period === \App\Services\Orders\DashboardFilters::CUSTOM)
            @if ($filters->from)
                <input type="hidden" name="date_from" value="{{ $filters->from->toDateString() }}">
            @endif
            @if ($filters->to)
                <input type="hidden" name="date_to" value="{{ $filters->to->toDateString() }}">
            @endif
        @endif

        <x-select label="Service" name="service_id" onchange="this.form.submit()">
            <option value="" @selected($filters->serviceId() === null)>Tous les services</option>
            @foreach ($services as $service)
                <option value="{{ $service->id }}" @selected($filters->serviceId() === $service->id)>{{ trim(($service->country->flag ?? '').' '.$service->name.($service->country ? ' — '.$service->country->name : '')) }}{{ $service->is_active ? '' : ' (inactif)' }}</option>
            @endforeach
        </x-select>
        <button type="submit" class="sr-only">Appliquer le service</button>
    </form>

    <div class="mt-3">
        <p class="mb-1.5 text-sm font-medium text-slate-700">Période</p>
        <div class="flex flex-wrap gap-1.5" role="group" aria-label="Période">
            @foreach (\App\Services\Orders\DashboardFilters::periodOptions() as $value => $label)
                @php
                    $active = $value === 'all' ? $filters->period === null : $filters->period === $value;
                    $chip = 'inline-flex items-center rounded-lg px-2.5 py-1.5 text-xs font-semibold transition sm:text-sm '.($active
                        ? 'bg-gradient-to-b from-indigo-500 to-indigo-600 text-white shadow-sm shadow-indigo-600/20'
                        : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50');
                @endphp
                <a href="{{ $filters->url(['period' => $value === 'all' ? null : $value]) }}" data-period="{{ $value }}"{!! $active ? ' data-active="true" aria-current="true"' : '' !!} class="{{ $chip }}">{{ $label }}</a>
            @endforeach
        </div>
        @if ($filters->rangeLabel())
            <p class="mt-2 text-xs text-slate-500">{{ $filters->rangeLabel() }}</p>
        @endif
    </div>

    <form method="GET" action="{{ route('dashboard') }}" autocomplete="off" class="mt-3 border-t border-slate-100 pt-3">
        @if ($filters->serviceId())
            <input type="hidden" name="service_id" value="{{ $filters->serviceId() }}">
        @endif
        <input type="hidden" name="period" value="custom">
        <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:items-end">
            <div class="min-w-0 sm:w-40">
                <x-input label="Du" name="date_from" type="date" value="{{ $filters->period === \App\Services\Orders\DashboardFilters::CUSTOM ? $filters->from?->toDateString() : '' }}" />
            </div>
            <div class="min-w-0 sm:w-40">
                <x-input label="Au" name="date_to" type="date" value="{{ $filters->period === \App\Services\Orders\DashboardFilters::CUSTOM ? $filters->to?->toDateString() : '' }}" />
            </div>
            <div class="col-span-2 sm:w-auto">
                <x-button type="submit" variant="secondary" class="w-full sm:w-auto">Appliquer</x-button>
            </div>
        </div>
    </form>

    @unless ($filters->isDefault())
        <div class="mt-3 text-right">
            <a href="{{ route('dashboard') }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-700">Réinitialiser</a>
        </div>
    @endunless
</section>
