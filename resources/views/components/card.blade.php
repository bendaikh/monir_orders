@props(['title' => null, 'description' => null])

<section {{ $attributes->merge(['class' => 'rounded-2xl border border-slate-200 bg-white shadow-sm']) }}>
    @if ($title || isset($action))
        <header class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <div>
                @if ($title)
                    <h2 class="text-base font-semibold text-slate-900">{{ $title }}</h2>
                @endif
                @if ($description)
                    <p class="mt-1 text-sm text-slate-500">{{ $description }}</p>
                @endif
            </div>
            @isset($action)
                <div class="shrink-0">{{ $action }}</div>
            @endisset
        </header>
    @endif
    <div class="{{ $title || isset($action) ? 'p-5' : 'p-5' }}">
        {{ $slot }}
    </div>
</section>
