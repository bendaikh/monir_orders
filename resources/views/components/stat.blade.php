@props(['label', 'value', 'tone' => 'blue'])

@php
    $tones = [
        'blue' => ['bg-indigo-50 text-indigo-600 ring-indigo-100', 'from-indigo-500 to-violet-500'],
        'orange' => ['bg-amber-50 text-amber-600 ring-amber-100', 'from-amber-400 to-orange-500'],
        'green' => ['bg-emerald-50 text-emerald-600 ring-emerald-100', 'from-emerald-400 to-teal-500'],
        'red' => ['bg-red-50 text-red-600 ring-red-100', 'from-red-400 to-rose-500'],
        'purple' => ['bg-violet-50 text-violet-600 ring-violet-100', 'from-violet-500 to-fuchsia-500'],
        'rose' => ['bg-rose-50 text-rose-700 ring-rose-100', 'from-rose-500 to-pink-600'],
        'slate' => ['bg-slate-100 text-slate-600 ring-slate-200', 'from-slate-400 to-slate-500'],
    ];
    [$chip, $bar] = $tones[$tone] ?? $tones['blue'];
@endphp

<article class="group relative overflow-hidden rounded-xl border border-slate-200/80 bg-white p-5 shadow-soft transition hover:-translate-y-0.5 hover:shadow-md">
    <span class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r {{ $bar }} opacity-80"></span>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</p>
            <p class="mt-2 text-3xl font-bold tracking-tight text-slate-900 tabular-nums">{{ $value }}</p>
        </div>
        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl ring-1 {{ $chip }}">
            {{ $slot }}
        </span>
    </div>
</article>
