<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\PartnerService;
use App\Models\SyncLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Service and period filters for the global dashboard.
 *
 * No service and no period means the historical dashboard: every order, with no
 * date window. Dates apply to received_at (when the order entered the app), the
 * same column the order list already filters on, so the bounds can use the
 * received_at index instead of wrapping the column in DATE().
 */
class DashboardFilters
{
    public const PRESETS = ['today', 'yesterday', '7d', '30d', 'month', 'last_month'];

    public const CUSTOM = 'custom';

    public function __construct(
        public readonly ?PartnerService $service,
        public readonly ?string $period,
        public readonly ?Carbon $from,
        public readonly ?Carbon $to,
    ) {}

    public static function fromRequest(Request $request, Collection $services): self
    {
        $service = self::resolveService($request, $services);
        $period = self::resolvePeriod($request);
        [$from, $to] = self::resolveBounds($request, $period);

        if ($period === self::CUSTOM && $from === null && $to === null) {
            $period = null;
        }

        return new self($service, $period, $from, $to);
    }

    public function isDefault(): bool
    {
        return $this->service === null && $this->period === null && $this->from === null && $this->to === null;
    }

    public function serviceId(): ?int
    {
        return $this->service?->id;
    }

    /**
     * @return array<string, string>
     */
    public static function periodOptions(): array
    {
        return [
            'all' => 'Toute la période',
            'today' => "Aujourd'hui",
            'yesterday' => 'Hier',
            '7d' => '7 derniers jours',
            '30d' => '30 derniers jours',
            'month' => 'Ce mois',
            'last_month' => 'Mois dernier',
        ];
    }

    public function periodLabel(): string
    {
        return match ($this->period) {
            'today' => "Aujourd'hui",
            'yesterday' => 'Hier',
            '7d' => '7 derniers jours',
            '30d' => '30 derniers jours',
            'month' => 'Ce mois',
            'last_month' => 'Mois dernier',
            self::CUSTOM => 'Période personnalisée',
            default => 'Toute la période',
        };
    }

    public function summaryLine(): string
    {
        $service = $this->service?->name ?? 'Tous les services';

        return 'Synthèse filtrée · '.$service.' · '.$this->periodLabel().'.';
    }

    public function recentOrdersDescription(): string
    {
        return match (true) {
            $this->serviceId() !== null && $this->period !== null => 'Dernières commandes reçues pour ce service, sur la période sélectionnée.',
            $this->serviceId() !== null => 'Dernières commandes reçues pour ce service.',
            $this->period !== null => 'Dernières commandes reçues sur la période sélectionnée.',
            default => 'Dernières commandes reçues, tous pays confondus.',
        };
    }

    public function rangeLabel(): ?string
    {
        if ($this->from === null && $this->to === null) {
            return null;
        }

        if ($this->from !== null && $this->to !== null) {
            if ($this->from->isSameDay($this->to)) {
                return $this->from->translatedFormat('d M Y');
            }

            return $this->from->translatedFormat('d M Y').' – '.$this->to->translatedFormat('d M Y');
        }

        if ($this->from !== null) {
            return 'À partir du '.$this->from->translatedFormat('d M Y');
        }

        return 'Jusqu\'au '.$this->to->translatedFormat('d M Y');
    }

    /**
     * Query string that reproduces this filter. Presets omit the computed dates
     * so a bookmark stays on the preset (today moves forward each day).
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, int|string>
     */
    public function parameters(array $overrides = []): array
    {
        $serviceId = array_key_exists('service_id', $overrides) ? $overrides['service_id'] : $this->serviceId();
        $period = array_key_exists('period', $overrides) ? $overrides['period'] : $this->period;
        $from = array_key_exists('date_from', $overrides)
            ? $overrides['date_from']
            : ($period === self::CUSTOM ? $this->from?->toDateString() : null);
        $to = array_key_exists('date_to', $overrides)
            ? $overrides['date_to']
            : ($period === self::CUSTOM ? $this->to?->toDateString() : null);

        $query = [];

        if ($serviceId) {
            $query['service_id'] = $serviceId;
        }

        if ($period) {
            $query['period'] = $period;
        }

        if ($period === self::CUSTOM) {
            if ($from) {
                $query['date_from'] = $from;
            }

            if ($to) {
                $query['date_to'] = $to;
            }
        }

        return $query;
    }

    public function url(array $overrides = []): string
    {
        return route('dashboard', $this->parameters($overrides));
    }

    /**
     * @return array<string, string>
     */
    public function canonicalQuery(): array
    {
        $query = [];

        if ($this->serviceId() !== null) {
            $query['service_id'] = (string) $this->serviceId();
        }

        if ($this->period !== null) {
            $query['period'] = $this->period;
        }

        if ($this->period === self::CUSTOM) {
            if ($this->from !== null) {
                $query['date_from'] = $this->from->toDateString();
            }

            if ($this->to !== null) {
                $query['date_to'] = $this->to->toDateString();
            }
        }

        return $query;
    }

    public function differsFromRequest(Request $request): bool
    {
        $actual = [];

        foreach (['service_id', 'period', 'date_from', 'date_to'] as $key) {
            if (! $request->query->has($key)) {
                continue;
            }

            $value = $request->query($key);
            // Empty or non-scalar values are kept so the canonical redirect strips them.
            $actual[$key] = is_string($value) ? $value : '__invalid__';
        }

        return $actual !== $this->canonicalQuery();
    }

    public function orderQuery(): Builder
    {
        return $this->apply(Order::query());
    }

    public function syncQuery(): Builder
    {
        return $this->apply(SyncLog::query(), 'started_at');
    }

    /**
     * One grouped aggregate for the "commandes par pays" chart and table.
     * Soft-deleted services are excluded, matching the previous withCount
     * through the service relation.
     */
    public function countsByCountry(): Collection
    {
        return $this->orderQuery()
            ->join('services', 'services.id', '=', 'orders.service_id')
            ->whereNull('services.deleted_at')
            ->select('services.country_id')
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw(
                'SUM(CASE WHEN orders.validation_status = ? THEN 1 ELSE 0 END) as pending_orders_count',
                ['pending'],
            )
            ->selectRaw(
                'SUM(CASE WHEN orders.transfer_status = ? THEN 1 ELSE 0 END) as transferred_orders_count',
                ['transferred'],
            )
            ->groupBy('services.country_id')
            ->get()
            ->keyBy(fn ($row) => (int) $row->country_id);
    }

    public function apply(Builder $query, string $dateColumn = 'received_at'): Builder
    {
        $table = $query->getModel()->getTable();

        if ($this->serviceId() !== null) {
            $query->where($table.'.service_id', $this->serviceId());
        }

        if ($this->from !== null) {
            $query->where($table.'.'.$dateColumn, '>=', $this->from);
        }

        if ($this->to !== null) {
            $query->where($table.'.'.$dateColumn, '<=', $this->to);
        }

        return $query;
    }

    private static function resolveService(Request $request, Collection $services): ?PartnerService
    {
        $raw = $request->query('service_id');

        if (! is_string($raw) || $raw === '' || ! ctype_digit($raw)) {
            return null;
        }

        return $services->firstWhere('id', (int) $raw);
    }

    private static function resolvePeriod(Request $request): ?string
    {
        $period = $request->query('period');
        $period = is_string($period) && $period !== '' ? $period : null;

        if ($period !== null && ! in_array($period, [...self::PRESETS, self::CUSTOM], true)) {
            $period = null;
        }

        $hasDate = self::rawDate($request, 'date_from') !== null || self::rawDate($request, 'date_to') !== null;

        if ($period === null && $hasDate) {
            return self::CUSTOM;
        }

        return $period;
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private static function resolveBounds(Request $request, ?string $period): array
    {
        if ($period === null) {
            return [null, null];
        }

        if ($period !== self::CUSTOM) {
            return self::presetBounds($period);
        }

        $from = self::parseDate(self::rawDate($request, 'date_from'), false);
        $to = self::parseDate(self::rawDate($request, 'date_to'), true);

        if ($from !== null && $to !== null && $from->greaterThan($to)) {
            return [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private static function presetBounds(string $period): array
    {
        $now = now();

        return match ($period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            '7d' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            '30d' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            'month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'last_month' => [
                $now->copy()->subMonthNoOverflow()->startOfMonth(),
                $now->copy()->subMonthNoOverflow()->endOfMonth(),
            ],
            default => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
        };
    }

    private static function rawDate(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function parseDate(?string $value, bool $end): ?Carbon
    {
        if ($value === null || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('!Y-m-d', $value, config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }

        if ($date === false || $date->format('Y-m-d') !== $value) {
            return null;
        }

        return $end ? $date->endOfDay() : $date->startOfDay();
    }
}
