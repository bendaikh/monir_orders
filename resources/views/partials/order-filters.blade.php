<form method="GET" class="mb-5 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-3 xl:grid-cols-4">
    @if (! empty($preserveStatus) && request()->exists('status') === false)
        <input type="hidden" name="status" value="pending_validation">
    @endif
    <x-input label="Référence" name="reference" value="{{ request('reference') }}" />
    <x-input label="Nom du client" name="customer" value="{{ request('customer') }}" />
    <x-input label="Téléphone" name="phone" value="{{ request('phone') }}" />
    <x-select label="Service" name="service_id">
        <option value="">Tous les services</option>
        @foreach ($services as $service)
            <option value="{{ $service->id }}" @selected((string) request('service_id') === (string) $service->id)>
                {{ $service->name }}{{ (! isset($country) || $country === null) ? ' — '.($service->country->name ?? '') : '' }}
            </option>
        @endforeach
    </x-select>
    <x-select label="Statut" name="status">
        <option value="" @selected(request()->exists('status') && request('status') === '')>Tous les statuts</option>
        @foreach (\App\Enums\OrderDisplayStatus::cases() as $status)
            <option value="{{ $status->value }}" @selected((request()->exists('status') ? request('status') : ($defaultStatus ?? '')) === $status->value)>{{ $status->label() }}</option>
        @endforeach
    </x-select>
    <x-select label="Ville" name="city">
        <option value="">Toutes les villes</option>
        @foreach (($cities ?? []) as $city)
            <option value="{{ $city }}" @selected(request('city') === $city)>{{ $city }}</option>
        @endforeach
    </x-select>
    @if (! isset($country) || $country === null)
        <x-select label="Pays" name="country">
            <option value="">Tous les pays</option>
            @foreach ($countries as $item)
                <option value="{{ $item->code }}" @selected(request('country') === $item->code)>{{ $item->flag }} {{ $item->name }}</option>
            @endforeach
        </x-select>
    @endif
    <x-input label="Du" name="date_from" type="date" value="{{ request('date_from') }}" />
    <x-input label="Au" name="date_to" type="date" value="{{ request('date_to') }}" />
    <div class="flex items-end gap-2">
        <x-button type="submit" variant="secondary">Filtrer</x-button>
        <x-button href="{{ $resetUrl }}" variant="ghost">Réinitialiser</x-button>
    </div>
</form>
