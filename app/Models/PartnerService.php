<?php

namespace App\Models;

use App\Support\OrderNormalizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class PartnerService extends Model
{
    use SoftDeletes;

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
        'destination_type',
        'api_config',
        'api_price_mode',
        'api_city_fallback',
    ];

    protected $hidden = ['api_config'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'phone_digits' => 'integer',
            'reference_counter' => 'integer',
            'api_config' => 'encrypted:array',
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

    public function productMappings(): HasMany
    {
        return $this->hasMany(ServiceProductMapping::class, 'service_id');
    }

    public function cityMappings(): HasMany
    {
        return $this->hasMany(ServiceCityMapping::class, 'service_id');
    }

    public function usesApi(): bool
    {
        return $this->destination_type === 'api';
    }

    public function hasApiCredentials(): bool
    {
        $config = $this->api_config ?? [];

        return ($config['auth_mode'] ?? 'bearer') === 'basic'
            ? filled($config['consumer_key'] ?? null) && filled($config['consumer_secret'] ?? null)
            : filled($config['token'] ?? null);
    }

    /** API test results are kept on the (otherwise unused) destination integration row. */
    public function apiStatusLabel(): string
    {
        if (! $this->hasApiCredentials()) {
            return 'Clé non renseignée';
        }

        return match ($this->destinationIntegration?->connection_status) {
            'connected' => 'Connecté',
            'error' => 'Erreur',
            default => 'Clé enregistrée · non testée',
        };
    }

    public function apiStatusTone(): string
    {
        if (! $this->hasApiCredentials()) {
            return 'slate';
        }

        return match ($this->destinationIntegration?->connection_status) {
            'connected' => 'green',
            'error' => 'red',
            default => 'orange',
        };
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

    /**
     * First day whose source rows can be pushed: the day the service was added to the app.
     * Older leads already in the sheet are ignored; anything from that day on that was never
     * pushed stays "en attente" until a click on « Valider et push », whatever the day.
     */
    public function pushSince(): \Carbon\CarbonInterface
    {
        return ($this->created_at ?? now())->copy()->timezone(config('app.timezone'))->startOfDay();
    }
}
