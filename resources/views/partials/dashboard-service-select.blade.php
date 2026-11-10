@php
    $selectedService = $filters->service;
    $selectedLabel = $selectedService
        ? trim(($selectedService->country->flag ?? '').' '.$selectedService->name.($selectedService->country ? ' — '.$selectedService->country->name : ''))
        : 'Tous les services';
@endphp

<form id="dashboard-service-form" method="GET" action="{{ route('dashboard') }}" autocomplete="off" class="min-w-0 w-[7.25rem] sm:w-52 lg:w-64">
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

    <label for="dashboard-service" class="sr-only">Service</label>
    <select
        id="dashboard-service"
        name="service_id"
        title="{{ $selectedLabel }}"
        onchange="this.form.submit()"
        class="w-full truncate rounded-lg border border-slate-300/80 bg-white py-1.5 pl-2.5 pr-7 text-sm text-slate-900 shadow-sm outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/15"
    >
        <option value="" @selected($filters->serviceId() === null)>Tous les services</option>
        @foreach ($services as $service)
            <option value="{{ $service->id }}" @selected($filters->serviceId() === $service->id)>{{ trim(($service->country->flag ?? '').' '.$service->name.($service->country ? ' — '.$service->country->name : '')) }}{{ $service->is_active ? '' : ' (inactif)' }}</option>
        @endforeach
    </select>
    <button type="submit" class="sr-only">Appliquer le service</button>
</form>
