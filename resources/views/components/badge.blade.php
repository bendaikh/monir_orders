@props(['tone' => 'slate'])

@php
    $tones = [
        'green' => 'bg-green-50 text-green-700 ring-green-600/15',
        'orange' => 'bg-orange-50 text-orange-700 ring-orange-600/15',
        'red' => 'bg-red-50 text-red-700 ring-red-600/15',
        'blue' => 'bg-blue-50 text-blue-700 ring-blue-600/15',
        'purple' => 'bg-purple-50 text-purple-700 ring-purple-600/15',
        'rose' => 'bg-rose-900 text-rose-50 ring-rose-950',
        'slate' => 'bg-slate-100 text-slate-600 ring-slate-500/10',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset '.($tones[$tone] ?? $tones['slate'])]) }}>
    {{ $slot }}
</span>
