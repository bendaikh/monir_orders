<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncLog extends Model
{
    protected $fillable = [
        'service_id',
        'user_id',
        'type',
        'result',
        'fetched_count',
        'created_count',
        'sent_count',
        'error_count',
        'skipped_count',
        'message',
        'details',
        'is_demo',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'is_demo' => 'boolean',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(PartnerService::class, 'service_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'manual' => 'Import manuel',
            'scheduled' => 'Import planifié',
            'connection_test' => 'Test de connexion',
            'test_send' => 'Test d\'envoi',
            'transfer' => 'Transfert',
            'demo' => 'Démonstration',
            default => $this->type,
        };
    }

    public function resultLabel(): string
    {
        return match ($this->result) {
            'success' => 'Réussi',
            'partial' => 'Partiel',
            'failed' => 'Échec',
            'running' => 'En cours',
            'demo' => 'Démonstration',
            default => $this->result,
        };
    }

    public function resultTone(): string
    {
        return match ($this->result) {
            'success' => 'green',
            'partial' => 'orange',
            'failed' => 'red',
            'running' => 'purple',
            'demo' => 'slate',
            default => 'slate',
        };
    }
}
