@props(['label', 'name', 'value' => null])

<label class="block">
    <span class="mb-1.5 block text-sm font-medium text-slate-700">{{ $label }}</span>
    <textarea
        name="{{ $name }}"
        id="{{ $name }}"
        {{ $attributes->merge(['class' => 'min-h-28 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20']) }}
    >{{ old($name, $value) }}</textarea>
    @error($name)
        <span class="mt-1 block text-sm text-red-600">{{ $message }}</span>
    @enderror
</label>
