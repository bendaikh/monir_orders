<?php

namespace App\Models;

use App\Support\OrderNormalizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class PartnerService extends Model
{
    protected $table = 'services';

    protected $fillable = [
        'country_id',
        'name',
        'description',
        'is_active',
        'duplicate_key',
        'phone_digits',
        'date_format',
        'decimal_format',
        'exclude_products',
        'reference_prefix',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'phone_digits' => 'integer',
            'reference_counter' => 'integer',
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
            ->reject(fn ($mapping) => OrderNormalizer::isPlaceholder($mapping->column_name))
            ->pluck('column_name', 'order_field')
            ->all();
    }

    public function generatesReferences(): bool
    {
        return filled($this->reference_prefix);
    }

    public function duplicateMode(): string
    {
        if (filled($this->duplicate_key) && array_key_exists($this->duplicate_key, OrderNormalizer::DUPLICATE_MODES)) {
            return $this->duplicate_key;
        }

        $sourceReference = $this->mappingFor('source')['reference'] ?? null;

        return $sourceReference !== null && ! $this->generatesReferences() ? 'reference' : 'phone_product_day';
    }

    public function nextReference(): string
    {
        return DB::transaction(function () {
            $row = DB::table('services')->where('id', $this->id)->lockForUpdate()->first(['reference_counter']);
            $next = ((int) ($row->reference_counter ?? 0)) + 1;
            DB::table('services')->where('id', $this->id)->update(['reference_counter' => $next]);
            $this->reference_counter = $next;

            return $this->reference_prefix.$next;
        });
    }
}
