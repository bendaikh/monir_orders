@props(['title', 'description' => null])

<div class="rounded-xl border border-dashed border-slate-300 bg-gradient-to-b from-white to-slate-50 px-6 py-12 text-center">
    <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-500 ring-1 ring-indigo-100">
        <x-icon name="orders" class="h-6 w-6" />
    </span>
    <p class="mt-4 text-base font-semibold text-slate-800">{{ $title }}</p>
    @if ($description)
        <p class="mx-auto mt-2 max-w-md text-sm text-slate-500">{{ $description }}</p>
    @endif
    @if (trim($slot))
        <div class="mt-5">{{ $slot }}</div>
    @endif
</div>
