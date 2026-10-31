<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PartnerService extends Model
{
    protected $table = 'services';

    protected $fillable = [
        'country_id',
        'name',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'service_id');
    }

    public function integrations(): HasMany
    {
        return $this->hasMany(SheetIntegration::class, 'service_id');
    }

    public function sourceIntegration(): HasOne
    {
        return $this->hasOne(SheetIntegration::class, 'service_id')->where('direction', 'source');
    }

    public function destinationIntegration(): HasOne
    {
        return $this->hasOne(SheetIntegration::class, 'service_id')->where('direction', 'destination');
    }

    public function columnMappings(): HasMany
    {
        return $this->hasMany(ColumnMapping::class, 'service_id');
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(SyncLog::class, 'service_id');
    }

    /**
     * @return array<string, string>
     */
    public function mappingFor(string $direction): array
    {
        return $this->columnMappings
            ->where('direction', $direction)
            ->pluck('column_name', 'order_field')
            ->all();
    }
}
