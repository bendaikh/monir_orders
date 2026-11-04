@props(['label', 'name', 'type' => 'text', 'value' => null, 'dot' => null])

@php $key = $dot ?? $name; @endphp

<label class="block">
    <span class="mb-1.5 block text-sm font-medium text-slate-700">{{ $label }}</span>
    <input
        type="{{ $type }}"
        name="{{ $name }}"
        id="{{ $key }}"
        value="{{ old($key, $value) }}"
        {{ $attributes->merge(['class' => 'w-full rounded-lg border border-slate-300/80 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/15']) }}
    >
    @error($key)
        <span class="mt-1 block text-sm text-red-600">{{ $message }}</span>
    @enderror
</label>
