@if (session('success') || session('error') || session('warning'))
    @php
        $tone = session('error') ? 'red' : (session('warning') ? 'orange' : 'green');
        $styles = [
            'red' => 'border-red-200 bg-red-50 text-red-800',
            'orange' => 'border-orange-200 bg-orange-50 text-orange-800',
            'green' => 'border-green-200 bg-green-50 text-green-800',
        ];
    @endphp
    <div class="mb-5 rounded-2xl border px-4 py-3 text-sm {{ $styles[$tone] }}">
        {{ session('error') ?: session('warning') ?: session('success') }}
    </div>
@endif
