@if (session('success') || session('error') || session('warning'))
    @php
        $tone = session('error') ? 'red' : (session('warning') ? 'orange' : 'green');
        $styles = [
            'red' => ['border-red-200 bg-red-50 text-red-800', 'bg-red-100 text-red-600', 'alert'],
            'orange' => ['border-orange-200 bg-orange-50 text-orange-800', 'bg-orange-100 text-orange-600', 'alert'],
            'green' => ['border-green-200 bg-green-50 text-green-800', 'bg-green-100 text-green-600', 'check'],
        ];
        [$box, $chip, $icon] = $styles[$tone];
    @endphp
    <div x-data="{ show: true }" x-show="show" x-transition.opacity class="mb-5 flex items-start gap-3 rounded-xl border px-4 py-3 text-sm shadow-soft {{ $box }}" role="status">
        <span class="mt-0.5 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg {{ $chip }}"><x-icon name="{{ $icon }}" class="h-4 w-4" /></span>
        <p class="flex-1 pt-1">{{ session('error') ?: session('warning') ?: session('success') }}</p>
        <button type="button" class="rounded-md p-1 opacity-60 hover:opacity-100" @click="show = false" aria-label="Fermer"><x-icon name="close" class="h-4 w-4" /></button>
    </div>
@endif
