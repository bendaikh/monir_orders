@props(['variant' => 'primary', 'href' => null, 'type' => 'submit'])

@php
    $classes = [
        'primary' => 'bg-blue-600 text-white shadow-sm hover:bg-blue-700',
        'secondary' => 'bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50',
        'danger' => 'bg-red-600 text-white shadow-sm hover:bg-red-700',
        'ghost' => 'text-slate-600 hover:bg-slate-100',
    ][$variant] ?? 'bg-blue-600 text-white';
    $class = 'inline-flex items-center justify-center gap-2 rounded-lg px-3.5 py-2 text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 '.$classes;
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $class]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $class]) }}>{{ $slot }}</button>
@endif
