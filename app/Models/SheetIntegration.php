<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SheetIntegration extends Model
{
    protected $fillable = [
        'service_id',
        'direction',
        'spreadsheet_url',
        'spreadsheet_id',
        'worksheet_name',
        'connection_status',
        'last_tested_at',
        'last_synced_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'last_tested_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(PartnerService::class, 'service_id');
    }

    public function isReady(): bool
    {
        return filled($this->spreadsheet_id) && filled($this->worksheet_name);
    }

    public function statusLabel(): string
    {
        return match ($this->connection_status) {
            'connected' => 'Connecté',
            'error' => 'Erreur',
            default => 'Non configuré',
        };
    }

    public function statusTone(): string
    {
        return match ($this->connection_status) {
            'connected' => 'green',
            'error' => 'red',
            default => 'slate',
        };
    }

    public function directionLabel(): string
    {
        return $this->direction === 'source' ? 'Source' : 'Destination';
    }
}
