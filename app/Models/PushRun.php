<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushRun extends Model
{
    public const PENDING = 'pending';
    public const RUNNING = 'running';
    public const DONE = 'done';
    public const FAILED = 'failed';

    /** A pending/running run older than this is considered dead (worker killed) and no longer blocks a new push. */
    public const STALE_AFTER_MINUTES = 30;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime', 'reported_at' => 'datetime', 'failures' => 'array'];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(PartnerService::class, 'service_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::PENDING, self::RUNNING], true)
            && $this->created_at?->gt(now()->subMinutes(self::STALE_AFTER_MINUTES));
    }

    public static function activeFor(int $serviceId): ?self
    {
        return self::query()
            ->where('service_id', $serviceId)
            ->whereIn('status', [self::PENDING, self::RUNNING])
            ->where('created_at', '>', now()->subMinutes(self::STALE_AFTER_MINUTES))
            ->latest('id')
            ->first();
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::PENDING => 'En file d\'attente',
            self::RUNNING => 'Envoi en cours',
            self::DONE => 'Terminé',
            self::FAILED => 'Échec',
            default => $this->status,
        };
    }

    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'label' => $this->statusLabel(),
            'active' => $this->isActive(),
            'tone' => $this->tone,
            'total' => $this->total,
            'today_count' => $this->today_count,
            'failures' => $this->failures ?? [],
            'transferred' => $this->transferred,
            'duplicates' => $this->duplicates,
            'failed' => $this->failed,
            'message' => $this->message,
            'error' => $this->error,
            'created_at' => $this->created_at?->format('d/m/Y H:i:s'),
            'finished_at' => $this->finished_at?->format('d/m/Y H:i:s'),
        ];
    }
}
