@props(['title' => null, 'description' => null])

<section {{ $attributes->merge(['class' => 'rounded-xl border border-slate-200/80 bg-white shadow-soft']) }}>
    @if ($title || isset($action))
        <header class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <div class="min-w-0">
                @if ($title)
                    <h2 class="flex items-center gap-2 text-[15px] font-semibold text-slate-900">
                        <span class="h-4 w-1 rounded-full bg-gradient-to-b from-indigo-500 to-violet-500"></span>
                        {{ $title }}
                    </h2>
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
    <div class="p-5">
        {{ $slot }}
    </div>
</section>
