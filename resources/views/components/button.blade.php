@props(['variant' => 'primary', 'href' => null, 'type' => 'submit'])

@php
    $classes = [
        'primary' => 'bg-gradient-to-b from-indigo-500 to-indigo-600 text-white shadow-sm shadow-indigo-600/20 hover:from-indigo-600 hover:to-indigo-700',
        'secondary' => 'bg-white text-slate-700 shadow-sm ring-1 ring-slate-200 hover:bg-slate-50 hover:ring-slate-300',
        'danger' => 'bg-red-600 text-white shadow-sm hover:bg-red-700',
        'ghost' => 'text-slate-600 hover:bg-slate-100',
    ][$variant] ?? 'bg-blue-600 text-white';
    $class = 'inline-flex items-center justify-center gap-2 rounded-lg px-3.5 py-2 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 '.$classes;
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $class]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $class]) }}>{{ $slot }}</button>
@endif
