@props(['title', 'description' => null])

<div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-12 text-center">
    <p class="text-base font-semibold text-slate-800">{{ $title }}</p>
    @if ($description)
        <p class="mx-auto mt-2 max-w-md text-sm text-slate-500">{{ $description }}</p>
    @endif
    @if (trim($slot))
        <div class="mt-4">{{ $slot }}</div>
    @endif
</div>
