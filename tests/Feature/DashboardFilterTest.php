<?php

namespace Tests\Feature;

use App\Enums\TransferStatus;
use App\Enums\ValidationStatus;
use App\Models\Country;
use App\Models\Order;
use App\Models\PartnerService;
use App\Models\SyncLog;
use App\Models\User;
use App\Services\Orders\OrderStats;
use App\Services\Sheets\GoogleSheetsGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DashboardFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-10 15:00:00', config('app.timezone')));
        $this->user = User::factory()->create();
        $this->app->bind(GoogleSheetsGateway::class, fn () => throw new \RuntimeException('The dashboard must not touch Google Sheets.'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_guest_is_redirected_when_filters_are_present(): void
    {
        $this->get(route('dashboard', ['period' => 'today']))
            ->assertRedirect(route('login'));
    }

    public function test_default_dashboard_matches_the_unfiltered_totals(): void
    {
        [$ci, $lb] = $this->catalog();
        $this->seedOrders($ci, $lb);

        $response = $this->getDashboard();

        $response->assertOk();
        $response->assertSee('Synthèse de tous les pays, services et commandes.');
        $response->assertSee('Dernières commandes reçues, tous pays confondus.');
        $response->assertSee('Tous les services');
        $response->assertSee("Aujourd'hui");
        $response->assertSee('Hier');
        $response->assertSee('7 derniers jours');
        $response->assertSee('30 derniers jours');
        $response->assertSee('Ce mois');
        $response->assertSee('Mois dernier');
        $response->assertSee('Côte d\'Ivoire Sureship');
        $response->assertSee('Lebanon COD Service');
        $response->assertSee('Simon Express');
        $response->assertSee('(inactif)');
        $response->assertSee('data-period="all" data-active="true"', false);
        $response->assertSee('value="" selected', false);
        $response->assertDontSee('Réinitialiser');
        $response->assertDontSee('Synthèse filtrée');

        $this->assertSame($this->counts(8, pending: 2, validated: 1, transferPending: 1, transferred: 2, rejected: 1, failed: 1), $response->viewData('summary'));
        $this->assertSame(3, $response->viewData('countryCount'));
        $this->assertSame(3, $response->viewData('serviceCount'));
        $this->assertSame([
            'labels' => ['Côte d\'Ivoire', 'Liban', 'Maroc'],
            'values' => [6, 2, 0],
        ], $response->viewData('ordersByCountry'));
        $this->assertSame([2, 1, 1, 2, 1, 1], $response->viewData('statusChart')['values']);
        $this->assertSame(
            app(OrderStats::class)->summarize(Order::query()),
            $response->viewData('summary'),
        );

        foreach (['CI-PENDING', 'CI-VALID', 'CI-TPEND', 'CI-SENT', 'CI-REJ', 'CI-FAIL', 'LB-PENDING', 'LB-SENT'] as $reference) {
            $response->assertSee($reference);
        }

        $this->assertSame(['sync-lb-today', 'sync-ci-today', 'sync-ci-old'], $this->syncMessages($response));
        $this->assertTrue($response->viewData('hasDemo'));
    }

    public function test_service_selector_is_in_the_header_and_only_on_the_dashboard(): void
    {
        [$ci, $lb] = $this->catalog();
        $this->seedOrders($ci, $lb);

        $dashboard = $this->getDashboard([
            'service_id' => $ci->id,
            'period' => 'custom',
            'date_from' => '2026-11-01',
            'date_to' => '2026-11-10',
        ]);

        $dashboard->assertOk();
        $html = $dashboard->getContent();
        $selectAt = strpos($html, 'id="dashboard-service"');
        $periodAt = strpos($html, '>Période<');

        $this->assertNotFalse($selectAt);
        $this->assertNotFalse($periodAt);
        $this->assertLessThan($periodAt, $selectAt);
        $this->assertSame(1, substr_count($html, '<select'));
        $this->assertStringNotContainsString('font-medium text-slate-700">Service', $html);
        $dashboard->assertSee('id="dashboard-service-form"', false);
        $dashboard->assertSee('name="period" value="custom"', false);
        $dashboard->assertSee('name="date_from" value="2026-11-01"', false);
        $dashboard->assertSee('name="date_to" value="2026-11-10"', false);
        $dashboard->assertSee('value="'.$ci->id.'" selected', false);
        $dashboard->assertSee('Tous les services');

        $this->actingAs($this->user)
            ->get(route('countries.index'))
            ->assertOk()
            ->assertDontSee('id="dashboard-service"', false);

        $this->actingAs($this->user)
            ->get(route('countries.dashboard', 'CI'))
            ->assertOk()
            ->assertDontSee('id="dashboard-service"', false);
    }

    public function test_service_filter_limits_every_card_table_and_chart(): void
    {
        [$ci, $lb] = $this->catalog();
        $this->seedOrders($ci, $lb);

        $response = $this->getDashboard(['service_id' => $ci->id]);

        $response->assertOk();
        $response->assertSee('Synthèse filtrée · Côte d\'Ivoire Sureship · Toute la période.');
        $response->assertSee('Dernières commandes reçues pour ce service.');
        $response->assertSee('value="'.$ci->id.'" selected', false);
        $response->assertSee('service_id='.$ci->id.'&amp;period=today', false);
        $response->assertSee('Lebanon COD Service');
        $response->assertSee('Réinitialiser');
        $response->assertDontSee('LB-PENDING');
        $response->assertDontSee('LB-SENT');
        $response->assertSee('CI-PENDING');
        $response->assertSee('CI-SENT');
        $this->assertSame(['sync-ci-today', 'sync-ci-old'], $this->syncMessages($response));

        $this->assertSame($this->counts(6, pending: 1, validated: 1, transferPending: 1, transferred: 1, rejected: 1, failed: 1), $response->viewData('summary'));
        $this->assertSame(1, $response->viewData('countryCount'));
        $this->assertSame(1, $response->viewData('serviceCount'));
        $this->assertSame([
            'labels' => ['Côte d\'Ivoire'],
            'values' => [6],
        ], $response->viewData('ordersByCountry'));
        $this->assertSame([1, 1, 1, 1, 1, 1], $response->viewData('statusChart')['values']);
        $this->assertFalse($response->viewData('hasDemo'));
    }

    public function test_date_presets_limit_stats_charts_and_syncs_without_changing_catalog_counts(): void
    {
        [$ci, $lb] = $this->catalog();
        $this->seedOrders($ci, $lb);

        $today = $this->getDashboard(['period' => 'today']);
        $today->assertOk();
        $today->assertSee('data-period="today" data-active="true"', false);
        $today->assertSee('Dernières commandes reçues sur la période sélectionnée.');
        $this->assertSame($this->counts(4, pending: 2, transferPending: 1, rejected: 1), $today->viewData('summary'));
        $this->assertSame(3, $today->viewData('countryCount'));
        $this->assertSame(3, $today->viewData('serviceCount'));
        $this->assertSame([3, 1, 0], $today->viewData('ordersByCountry')['values']);
        $today->assertSee('CI-PENDING');
        $today->assertSee('LB-PENDING');
        $today->assertDontSee('CI-SENT');
        $today->assertDontSee('CI-VALID');
        $this->assertSame(['sync-lb-today', 'sync-ci-today'], $this->syncMessages($today));

        $yesterday = $this->getDashboard(['period' => 'yesterday']);
        $this->assertSame($this->counts(3, validated: 1, transferred: 1, failed: 1), $yesterday->viewData('summary'));
        $this->assertSame([2, 1, 0], $yesterday->viewData('ordersByCountry')['values']);

        $week = $this->getDashboard(['period' => '7d']);
        $this->assertSame($this->counts(7, pending: 2, validated: 1, transferPending: 1, transferred: 1, rejected: 1, failed: 1), $week->viewData('summary'));

        $month = $this->getDashboard(['period' => '30d']);
        $this->assertSame($this->counts(8, pending: 2, validated: 1, transferPending: 1, transferred: 2, rejected: 1, failed: 1), $month->viewData('summary'));

        $thisMonth = $this->getDashboard(['period' => 'month']);
        $this->assertSame($week->viewData('summary'), $thisMonth->viewData('summary'));

        $lastMonth = $this->getDashboard(['period' => 'last_month']);
        $this->assertSame($this->counts(1, transferred: 1), $lastMonth->viewData('summary'));
        $this->assertSame([1, 0, 0], $lastMonth->viewData('ordersByCountry')['values']);
        $lastMonth->assertSee('CI-SENT');
        $lastMonth->assertDontSee('CI-PENDING');
    }

    public function test_custom_range_is_inclusive_and_combines_with_the_service(): void
    {
        [$ci, $lb] = $this->catalog();
        $this->seedOrders($ci, $lb);

        $range = $this->getDashboard([
            'period' => 'custom',
            'date_from' => '2026-11-09',
            'date_to' => '2026-11-09',
        ]);
        $range->assertOk();
        $range->assertSee('name="date_from"', false);
        $range->assertSee('value="2026-11-09"', false);
        $this->assertSame($this->counts(3, validated: 1, transferred: 1, failed: 1), $range->viewData('summary'));

        $combined = $this->getDashboard([
            'service_id' => $ci->id,
            'period' => 'today',
        ]);
        $combined->assertOk();
        $combined->assertSee('Synthèse filtrée · Côte d\'Ivoire Sureship · Aujourd\'hui.');
        $combined->assertSee('Dernières commandes reçues pour ce service, sur la période sélectionnée.');
        $this->assertSame($this->counts(3, pending: 1, transferPending: 1, rejected: 1), $combined->viewData('summary'));
        $this->assertSame([
            'labels' => ['Côte d\'Ivoire'],
            'values' => [3],
        ], $combined->viewData('ordersByCountry'));
        $combined->assertSee('CI-PENDING');
        $combined->assertDontSee('LB-PENDING');
        $combined->assertDontSee('CI-VALID');
        $this->assertSame(['sync-ci-today'], $this->syncMessages($combined));

        $open = $this->getDashboard([
            'service_id' => (string) $lb->id,
            'period' => 'custom',
            'date_to' => '2026-11-09',
        ]);
        $this->assertSame($this->counts(1, transferred: 1), $open->viewData('summary'));
        $open->assertSee('LB-SENT');
        $open->assertDontSee('LB-PENDING');
    }

    public function test_period_boundaries_include_the_first_and_last_moment(): void
    {
        $country = $this->country('Alpha', 'AL');
        $service = $this->service($country, 'Alpha Express');

        $moments = [
            '2026-11-10 00:00:00',
            '2026-11-10 23:59:59',
            '2026-11-11 00:00:00',
            '2026-11-09 23:59:59',
            '2026-11-04 00:00:00',
            '2026-11-03 23:59:59',
            '2026-10-12 00:00:00',
            '2026-10-11 23:59:59',
            '2026-11-01 00:00:00',
            '2026-10-31 12:00:00',
            '2026-10-01 00:00:00',
            '2026-09-30 23:59:59',
        ];

        foreach ($moments as $index => $moment) {
            $this->order($service, 'B-'.$index, 'pending', $moment);
        }

        $this->assertSame(12, $this->getDashboard()->viewData('summary')['total']);
        $this->assertSame(2, $this->getDashboard(['period' => 'today'])->viewData('summary')['total']);
        $this->assertSame(1, $this->getDashboard(['period' => 'yesterday'])->viewData('summary')['total']);
        $this->assertSame(4, $this->getDashboard(['period' => '7d'])->viewData('summary')['total']);
        $this->assertSame(8, $this->getDashboard(['period' => '30d'])->viewData('summary')['total']);
        $this->assertSame(7, $this->getDashboard(['period' => 'month'])->viewData('summary')['total']);
        $this->assertSame(4, $this->getDashboard(['period' => 'last_month'])->viewData('summary')['total']);

        $custom = $this->getDashboard([
            'period' => 'custom',
            'date_from' => '2026-11-03',
            'date_to' => '2026-11-04',
        ]);
        $this->assertSame(2, $custom->viewData('summary')['total']);
    }

    public function test_invalid_filters_redirect_to_the_canonical_query(): void
    {
        [$ci] = $this->catalog();

        $this->getDashboard(['service_id' => '99999'])
            ->assertRedirect(route('dashboard'));

        $this->getDashboard(['period' => 'nope'])
            ->assertRedirect(route('dashboard'));

        $this->getDashboard(['period' => 'today', 'date_from' => '2020-01-01'])
            ->assertRedirect(route('dashboard', ['period' => 'today']));

        $this->getDashboard([
            'period' => 'custom',
            'date_from' => '2026-11-10',
            'date_to' => '2026-11-09',
        ])->assertRedirect(route('dashboard', [
            'period' => 'custom',
            'date_from' => '2026-11-09',
            'date_to' => '2026-11-10',
        ]));

        $this->getDashboard(['date_from' => '2026-11-10'])
            ->assertRedirect(route('dashboard', [
                'period' => 'custom',
                'date_from' => '2026-11-10',
            ]));

        $this->getDashboard(['period' => 'custom', 'date_from' => '2026-02-31'])
            ->assertRedirect(route('dashboard'));

        $this->getDashboard(['period' => 'custom'])
            ->assertRedirect(route('dashboard'));

        $this->getDashboard(['service_id' => (string) $ci->id, 'period' => 'custom'])
            ->assertRedirect(route('dashboard', ['service_id' => $ci->id]));

        $this->actingAs($this->user)
            ->get('/tableau-de-bord?service_id[]=1')
            ->assertRedirect(route('dashboard'));

        $this->actingAs($this->user)
            ->get('/tableau-de-bord?service_id=&period=today')
            ->assertRedirect(route('dashboard', ['period' => 'today']));

        $this->getDashboard(['service_id' => (string) $ci->id, 'period' => 'today'])
            ->assertOk()
            ->assertViewHas('filters', fn ($filters) => $filters->serviceId() === $ci->id && $filters->period === 'today');
    }

    public function test_deleted_services_stay_out_of_the_selector_but_their_orders_remain_in_the_unfiltered_total(): void
    {
        $country = $this->country('Côte d\'Ivoire', 'CI');
        $live = $this->service($country, 'Côte d\'Ivoire Sureship');
        $removed = $this->service($country, 'Ancien CodPay');
        $this->order($live, 'CI-LIVE', 'pending', '2026-11-10 10:00:00');
        $this->order($removed, 'CI-OLD', 'pending', '2026-11-10 11:00:00');
        $removed->delete();

        $response = $this->getDashboard();

        $response->assertOk();
        $response->assertSee('Côte d\'Ivoire Sureship');
        $response->assertDontSee('Ancien CodPay');
        $response->assertSee('CI-LIVE');
        $response->assertDontSee('CI-OLD');
        $this->assertSame(2, $response->viewData('summary')['total']);
        $this->assertSame(1, $response->viewData('serviceCount'));
        $this->assertSame([1], $response->viewData('ordersByCountry')['values']);

        $this->getDashboard(['service_id' => $removed->id])
            ->assertRedirect(route('dashboard'));
    }

    /**
     * @return array{0: PartnerService, 1: PartnerService}
     */
    private function catalog(): array
    {
        $ci = $this->service($this->country('Côte d\'Ivoire', 'CI'), 'Côte d\'Ivoire Sureship');
        $lb = $this->service($this->country('Liban', 'LB'), 'Lebanon COD Service');
        $this->service($this->country('Maroc', 'MA'), 'Simon Express', active: false);

        return [$ci, $lb];
    }

    private function seedOrders(PartnerService $ci, PartnerService $lb): void
    {
        $this->order($ci, 'CI-PENDING', 'pending', '2026-11-10 10:00:00');
        $this->order($ci, 'CI-VALID', 'validated', '2026-11-09 18:00:00');
        $this->order($ci, 'CI-TPEND', 'transfer_pending', '2026-11-10 11:00:00');
        $this->order($ci, 'CI-SENT', 'transferred', '2026-10-31 12:00:00');
        $this->order($ci, 'CI-REJ', 'rejected', '2026-11-10 12:00:00');
        $this->order($ci, 'CI-FAIL', 'failed', '2026-11-09 09:00:00');
        $this->order($lb, 'LB-PENDING', 'pending', '2026-11-10 13:00:00', demo: true);
        $this->order($lb, 'LB-SENT', 'transferred', '2026-11-09 16:00:00');

        $this->sync($ci, 'sync-ci-today', '2026-11-10 08:00:00');
        $this->sync($ci, 'sync-ci-old', '2026-10-01 08:00:00');
        $this->sync($lb, 'sync-lb-today', '2026-11-10 08:30:00');
    }

    private function country(string $name, string $code): Country
    {
        return Country::query()->create([
            'name' => $name,
            'code' => $code,
            'currency' => 'MAD',
            'is_active' => true,
        ]);
    }

    private function service(Country $country, string $name, bool $active = true): PartnerService
    {
        return $country->services()->create([
            'name' => $name,
            'is_active' => $active,
        ]);
    }

    private function order(PartnerService $service, string $reference, string $state, string $receivedAt, bool $demo = false): Order
    {
        [$validation, $transfer] = match ($state) {
            'pending' => [ValidationStatus::Pending, TransferStatus::None],
            'validated' => [ValidationStatus::Validated, TransferStatus::None],
            'transfer_pending' => [ValidationStatus::Validated, TransferStatus::Pending],
            'transferred' => [ValidationStatus::Validated, TransferStatus::Transferred],
            'rejected' => [ValidationStatus::Rejected, TransferStatus::None],
            'failed' => [ValidationStatus::Validated, TransferStatus::Failed],
            default => throw new \InvalidArgumentException($state),
        };

        return $service->orders()->create([
            'reference' => $reference,
            'customer_name' => 'Client '.$reference,
            'phone' => '0600000000',
            'city' => 'Casablanca',
            'address' => 'Rue 1',
            'total_amount' => 100,
            'received_at' => Carbon::parse($receivedAt, config('app.timezone')),
            'validation_status' => $validation,
            'transfer_status' => $transfer,
            'is_demo' => $demo,
        ]);
    }

    private function sync(PartnerService $service, string $message, string $startedAt): void
    {
        SyncLog::query()->create([
            'service_id' => $service->id,
            'type' => 'manual',
            'result' => 'success',
            'message' => $message,
            'started_at' => Carbon::parse($startedAt, config('app.timezone')),
        ]);
    }

    private function syncMessages(TestResponse $response): array
    {
        return $response->viewData('recentSyncs')->pluck('message')->all();
    }

    private function getDashboard(array $query = []): TestResponse
    {
        return $this->actingAs($this->user)->get(route('dashboard', $query));
    }

    /**
     * @return array{total: int, pending: int, validated: int, transfer_pending: int, transferred: int, rejected: int, failed: int, duplicates: int}
     */
    private function counts(
        int $total,
        int $pending = 0,
        int $validated = 0,
        int $transferPending = 0,
        int $transferred = 0,
        int $rejected = 0,
        int $failed = 0,
        int $duplicates = 0,
    ): array {
        return [
            'total' => $total,
            'pending' => $pending,
            'validated' => $validated,
            'transfer_pending' => $transferPending,
            'transferred' => $transferred,
            'rejected' => $rejected,
            'failed' => $failed,
            'duplicates' => $duplicates,
        ];
    }
}
