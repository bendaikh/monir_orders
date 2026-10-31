<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferLog extends Model
{
    protected $fillable = [
        'order_id',
        'service_id',
        'user_id',
        'status',
        'attempt',
        'destination_spreadsheet_id',
        'destination_worksheet',
        'message',
        'idempotency_key',
        'response_payload',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'response_payload' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(PartnerService::class, 'service_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'success' => 'Réussi',
            'failed' => 'Échec',
            'pending' => 'En cours',
            default => 'Inconnu',
        };
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            'success' => 'green',
            'failed' => 'rose',
            'pending' => 'purple',
            default => 'slate',
        };
    }
}
