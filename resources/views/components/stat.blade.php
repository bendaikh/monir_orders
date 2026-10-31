@props(['label', 'value', 'tone' => 'blue'])

@php
    $tones = [
        'blue' => 'bg-blue-50 text-blue-700',
        'orange' => 'bg-orange-50 text-orange-700',
        'green' => 'bg-green-50 text-green-700',
        'red' => 'bg-red-50 text-red-700',
        'purple' => 'bg-purple-50 text-purple-700',
        'rose' => 'bg-rose-50 text-rose-800',
        'slate' => 'bg-slate-100 text-slate-700',
    ];
@endphp

<article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="text-sm font-medium text-slate-500">{{ $label }}</p>
            <p class="mt-2 text-2xl font-semibold tracking-tight text-slate-900">{{ $value }}</p>
        </div>
        <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl {{ $tones[$tone] ?? $tones['blue'] }}">
            {{ $slot }}
        </span>
    </div>
</article>
