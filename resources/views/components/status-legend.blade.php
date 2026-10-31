<div class="flex flex-wrap gap-2">
    @foreach (\App\Enums\OrderDisplayStatus::cases() as $status)
        <x-status-badge :status="$status" />
    @endforeach
</div>
