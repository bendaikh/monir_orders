<?php

namespace Tests\Feature;

use App\Enums\TransferStatus;
use App\Enums\ValidationStatus;
use App\Models\ColumnMapping;
use App\Models\Country;
use App\Models\Order;
use App\Models\User;
use App\Services\Sheets\GoogleSheetsGateway;
use App\Services\Sheets\PartnerServiceProvisioner;
use App\Support\OrderNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DedupValidateTest extends TestCase
{
    use RefreshDatabase;

    private function setUpCodpayLike(array $sourceRows, array $destRows = []): array
    {
        $user = User::factory()->create();
        $country = Country::query()->create(['name' => 'Tunisie', 'code' => 'TN', 'currency' => 'TND', 'is_active' => true]);
        $service = $country->services()->create([
            'name' => 'Demo', 'is_active' => true,
            'reference_prefix' => 'CP-', 'decimal_format' => 'point', 'date_format' => 'd/m/Y H:i:s',
        ]);
        app(PartnerServiceProvisioner::class)->provision($service);
        $service->sourceIntegration->update(['spreadsheet_id' => 'src', 'worksheet_name' => 'Réponses']);
        $service->destinationIntegration->update(['spreadsheet_id' => 'dst', 'worksheet_name' => 'orders']);

        $source = ['reference' => '(non mappé)', 'customer_name' => 'Nom', 'phone' => 'Tel', 'city' => 'Ville', 'address' => 'Address',
            'total_amount' => 'Price', 'ordered_at' => 'Horodateur', 'product' => 'Produit Ref', 'quantity' => 'qte', 'unit_price' => '(non mappé unit_price)'];
        $destination = ['reference' => 'Order ID', 'customer_name' => 'Customer name', 'phone' => 'Phone number', 'city' => 'City',
            'address' => 'Address', 'total_amount' => 'Total prices', 'product' => 'Product Skus', 'quantity' => 'quantities',
            'ordered_at' => '(non mappé ordered_at)', 'unit_price' => '(non mappé unit_price)'];

        foreach (['source' => $source, 'destination' => $destination] as $direction => $map) {
            foreach ($map as $field => $column) {
                ColumnMapping::query()->updateOrCreate(
                    ['service_id' => $service->id, 'direction' => $direction, 'order_field' => $field],
                    ['column_name' => $column],
                );
            }
        }

        $gateway = new FakeBatchSheets([
            'src' => array_merge([['Horodateur', 'Produit Ref', 'Nom', 'Tel', 'Address', 'Ville', 'qte', 'Price', 'Note']], $sourceRows),
            'dst' => array_merge([['Order ID', 'Product Skus', 'Customer name', 'Phone number', 'City', 'Address', 'quantities', 'Total prices', 'Store']], $destRows),
        ]);
        $this->app->instance(GoogleSheetsGateway::class, $gateway);

        return [$user, $country, $service->fresh(), $gateway];
    }

    public function test_normalizer_parses_formats_and_phones(): void
    {
        $this->assertSame('2026-11-02 13:58:59', OrderNormalizer::date('02/11/2026 13:58:59', 'd/m/Y H:i:s')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-02-28', OrderNormalizer::date('28/02/2026 02:32:10', 'd/m/Y H:i:s')->format('Y-m-d'));
        $this->assertSame('2026-11-02', OrderNormalizer::date('2026-11-02 10:00', 'd/m/Y H:i:s')->format('Y-m-d'));
        $this->assertSame('85.00', OrderNormalizer::money('85.000', 'point'));
        $this->assertSame('1250.50', OrderNormalizer::money('1.250,5', 'comma'));
        $this->assertSame('58450856', OrderNormalizer::phone('+216 58 450 856'));
        $this->assertSame('58450856', OrderNormalizer::phone('0021658450856'));
        $this->assertSame('58450856', OrderNormalizer::phone('58450856'));
        $this->assertTrue(OrderNormalizer::isPlaceholder('(non mappé ordered_at)'));
    }

    public function test_import_flags_same_day_repeats_keeps_same_timestamp_customers_and_generates_ids(): void
    {
        [$user, $country, $service] = $this->setUpCodpayLike([
            ['28/02/2026 02:32:10', 'mm5gd1rz', 'Fathi', '58450856', 'Médenine', '', '2', '82.000', ''],
            ['28/02/2026 09:10:00', 'MM5GD1RZ', 'Fathi A.', '+216 58 450 856', 'Médenine', '', '1', '41.000', ''],
            ['01/03/2026 09:10:00', 'mm5gd1rz', 'Fathi', '58450856', 'Médenine', '', '1', '41.000', ''],
            ['13/09/2026 08:18:14', 'thermal', 'Gaston', '078548598', 'A', '', '1', '85.000', ''],
            ['13/09/2026 08:18:14', 'thermal', 'Linus', '0762505329', 'B', '', '1', '85.000', ''],
            ['13/09/2026 09:00:00', 'excluded-dar', 'Filtré', '0700000000', 'C', '', '1', '1', ''],
        ]);
        $service->update(['exclude_products' => '-dar']);

        $this->actingAs($user)->post(route('countries.services.import', [$country, $service]))->assertRedirect();

        $this->assertSame(5, Order::query()->count());
        $duplicate = Order::query()->where('validation_status', ValidationStatus::Duplicate->value)->sole();
        $this->assertSame('Fathi A.', $duplicate->customer_name);
        $this->assertSame('CP-1', $duplicate->duplicateOf->reference);
        $this->assertSame(['CP-1', 'CP-2', 'CP-3', 'CP-4', 'CP-5'], Order::query()->orderBy('id')->pluck('reference')->all());
        $this->assertTrue(Order::query()->where('customer_name', 'Gaston')->exists());
        $this->assertTrue(Order::query()->where('customer_name', 'Linus')->exists());
        $this->assertSame('85.00', Order::query()->where('customer_name', 'Linus')->value('total_amount'));
        $this->assertSame('2026-09-13', Order::query()->where('customer_name', 'Linus')->first()->ordered_at->format('Y-m-d'));

        // Re-reading the sheet imports nothing new.
        $this->actingAs($user)->post(route('countries.services.import', [$country, $service]))->assertRedirect();
        $this->assertSame(5, Order::query()->count());
    }

    public function test_validate_all_counts_today_live_pushes_once_and_second_click_pushes_nothing(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 2)->setTime(20, 0));

        [$user, $country, $service, $gateway] = $this->setUpCodpayLike([
            ['01/11/2026 10:00:00', 'old', 'Hier', '0711111111', 'X', '', '1', '10.000', ''],
            ['02/11/2026 02:32:10', 'mm5gd1rz', 'Fathi', '58450856', 'Médenine', '', '2', '82.000', ''],
            ['02/11/2026 09:10:00', 'mm5gd1rz', 'Fathi', '58450856', 'Médenine', '', '1', '41.000', ''],
            ['02/11/2026 08:18:14', 'thermal', 'Gaston', '078548598', 'A', '', '1', '85.000', ''],
            ['02/11/2026 08:18:14', 'thermal', 'Linus', '0762505329', 'B', '', '1', '85.000', ''],
        ]);

        $this->actingAs($user)->get(route('countries.services.edit', [$country, $service]))
            ->assertOk()->assertSee('Règle de détection des doublons');

        // No import step: the count is read live from the source sheet (today's rows only).
        $this->actingAs($user)->get(route('countries.services.show', [$country, $service]))
            ->assertOk()->assertSee('Valider toutes les commandes en attente (4)');
        $this->assertSame(0, Order::query()->count());

        $this->actingAs($user)->post(route('countries.services.validate-all', [$country, $service]))
            ->assertRedirect()->assertSessionHas('success', fn ($m) => str_contains($m, '3 transférée(s), 1 doublon(s) supprimé(s)'));

        $this->assertSame(3, $gateway->appended);
        $this->assertSame(3, Order::query()->where('transfer_status', TransferStatus::Transferred->value)->count());
        $this->assertSame(1, Order::query()->where('validation_status', ValidationStatus::Duplicate->value)->count());
        $this->assertSame(0, Order::query()->whereHas('items', fn ($q) => $q->where('name', 'old'))->count());
        $this->assertSame(['CP-1', 'mm5gd1rz', 'Fathi', '58450856', '', 'Médenine', '2.00', '82.00', ''], $gateway->sheets['dst'][1]);
        $this->assertSame([['src', 6, 'green']], $gateway->backgrounds);
        $this->assertSame(6, $service->sourceIntegration->fresh()->marked_row);

        // After the push the counter only shows what is left to send.
        \Illuminate\Support\Facades\Cache::flush();
        $this->actingAs($user)->get(route('countries.services.show', [$country, $service]))
            ->assertSee('Valider toutes les commandes en attente (0)');
        $this->actingAs($user)->get(route('countries.services.index', $country))
            ->assertOk()->assertSee('Commandes du jour')->assertSee('En attente d\'envoi', false);

        // A new repeat of an already-pushed order arrives, plus one new order.
        $gateway->sheets['src'][] = ['02/11/2026 18:00:00', 'thermal', 'Gaston', '+255 078548598', 'A', '', '1', '85.000', ''];
        $gateway->sheets['src'][] = ['02/11/2026 19:00:00', 'thermal', 'Nouveau', '0799999999', 'C', '', '1', '85.000', ''];

        $this->actingAs($user)->post(route('countries.services.validate-all', [$country, $service]))
            ->assertSessionHas('success', fn ($m) => str_contains($m, '1 transférée(s), 1 doublon(s) supprimé(s)'));
        $this->assertSame(4, $gateway->appended);
        $this->assertSame([['src', 6, 'green'], ['src', 6, 'clear'], ['src', 8, 'green']], $gateway->backgrounds);
        $this->assertSame(8, $service->sourceIntegration->fresh()->marked_row);

        $this->actingAs($user)->post(route('countries.services.validate-all', [$country, $service]))->assertRedirect();
        $this->assertSame(4, $gateway->appended);
        $this->assertCount(3, $gateway->backgrounds);
    }

    public function test_validate_all_is_queued_unique_and_reports_status(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        [$user, $country, $service] = $this->setUpCodpayLike([]);

        $this->actingAs($user)->post(route('countries.services.validate-all', [$country, $service]))
            ->assertRedirect()->assertSessionHas('success', fn ($m) => str_contains($m, 'arrière-plan'));
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\ValidateAllServiceOrdersJob::class, 1);
        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldBeUnique::class, new \App\Jobs\ValidateAllServiceOrdersJob($service->id, $user->id, 1));

        // Second click while the first is still pending: no second job.
        $this->actingAs($user)->post(route('countries.services.validate-all', [$country, $service]))->assertSessionHas('warning');
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\ValidateAllServiceOrdersJob::class, 1);

        $this->actingAs($user)->getJson(route('countries.services.push-status', [$country, $service]))
            ->assertOk()->assertJsonPath('run.status', 'pending')->assertJsonPath('run.active', true);
        $this->actingAs($user)->get(route('countries.services.show', [$country, $service]))->assertOk()->assertSee('Dernier envoi');

        // Worker finishes: the next poll flashes the report once, and the reloaded page shows the toast.
        \App\Models\PushRun::query()->latest('id')->first()->update(['status' => 'done', 'tone' => 'success', 'today_count' => 5, 'transferred' => 3, 'duplicates' => 1, 'failed' => 1, 'failures' => [['ref' => 'CP-9', 'name' => 'Zed', 'reason' => 'bad city']], 'finished_at' => now()]);
        $this->actingAs($user)->getJson(route('countries.services.push-status', [$country, $service]))->assertJsonPath('run.transferred', 3);
        $this->actingAs($user)->get(route('countries.services.show', [$country, $service]))
            ->assertSee('Envoyées avec succès')->assertSee('bad city');
        $this->actingAs($user)->get(route('countries.services.show', [$country, $service]))->assertDontSee('Envoyées avec succès');
    }

    public function test_show_page_handles_google_errors(): void
    {
        [$user, $country, $service] = $this->setUpCodpayLike([]);
        $this->app->instance(GoogleSheetsGateway::class, new class(['src' => [[]]]) extends FakeBatchSheets {
            public function readValues(string $spreadsheetId, string $worksheet): array
            {
                throw new \App\Services\Sheets\GoogleSheetsException('Accès refusé à la feuille.');
            }
        });

        $this->actingAs($user)->get(route('countries.services.show', [$country, $service]))
            ->assertOk()->assertSee('Valider toutes les commandes en attente (?)')->assertSee('Accès refusé à la feuille.');

        $this->actingAs($user)->post(route('countries.services.validate-all', [$country, $service]))
            ->assertSessionHas('error');
    }

    public function test_reference_already_in_destination_is_not_pushed_again(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 2)->setTime(20, 0));

        [$user, $country, $service, $gateway] = $this->setUpCodpayLike(
            [['02/11/2026 02:32:10', 'mm5gd1rz', 'Fathi', '58450856', 'Médenine', '', '2', '82.000', '']],
            [['CP-1', 'mm5gd1rz', 'Fathi', '58450856', '', 'Médenine', '2', '82', '']],
        );

        $this->actingAs($user)->post(route('countries.services.validate-all', [$country, $service]));

        $this->assertSame(0, $gateway->appended);
        $this->assertSame(TransferStatus::Transferred, Order::query()->sole()->transfer_status);
    }

    public function test_quantity_equals_total_price_cells(): void
    {
        $this->assertSame(['2.00', '85000.00'], OrderNormalizer::quantityAndTotal('2=85000', 'comma'));
        $this->assertSame(['2.00', '85000.00'], OrderNormalizer::quantityAndTotal(' 2 = 85.000 ', 'comma'));
        $this->assertSame(['2.00', '85000.00'], OrderNormalizer::quantityAndTotal('2=85,000', 'comma'));
        $this->assertSame(['3.00', '85000.00'], OrderNormalizer::quantityAndTotal('3 = 85 000', 'comma'));
        $this->assertSame(['5.00', '345000.00'], OrderNormalizer::quantityAndTotal('5=345000', 'point'));
        $this->assertSame([null, '85000.00'], OrderNormalizer::quantityAndTotal('85000', 'comma'));
        $this->assertSame([null, '85000.00'], OrderNormalizer::quantityAndTotal('85,000', 'comma'));
        $this->assertSame([null, null], OrderNormalizer::quantityAndTotal('', 'comma'));
    }

    public function test_quantity_from_price_cell_is_pushed_to_destination(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 2)->setTime(20, 0));

        [$user, $country, $service, $gateway] = $this->setUpCodpayLike([
            ['02/11/2026 03:08:31', 'chicken', 'Damas', '0753670737', 'Tabora', '', '', '2=145000', ''],
            ['02/11/2026 04:34:31', 'thermal', 'Listram', '0715700811', 'Masasi', '', '', '3 = 85.000', ''],
            ['02/11/2026 05:00:00', 'rubber', 'Plain', '0700000001', 'Dar', '', '', '85000', ''],
        ]);
        $service->update(['decimal_format' => 'comma']);

        $this->actingAs($user)->post(route('countries.services.validate-all', [$country, $service]))->assertSessionHas('success');

        $this->assertSame(['CP-1', 'chicken', 'Damas', '0753670737', '', 'Tabora', '2.00', '145000.00', ''], $gateway->sheets['dst'][1]);
        $this->assertSame('3.00', $gateway->sheets['dst'][2][6]);
        $this->assertSame('85000.00', $gateway->sheets['dst'][2][7]);
        $this->assertSame(['1.00', '85000.00'], [$gateway->sheets['dst'][3][6], $gateway->sheets['dst'][3][7]]);
    }

    public function test_push_succeeds_when_marking_the_source_row_fails(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 2)->setTime(20, 0));

        [$user, $country, $service, $gateway] = $this->setUpCodpayLike([
            ['02/11/2026 05:00:00', 'rubber', 'Plain', '0700000001', 'Dar', '', '', '85000', ''],
        ]);
        $gateway->failFormatting = true;

        $this->actingAs($user)->post(route('countries.services.validate-all', [$country, $service]))
            ->assertSessionHas('success', fn ($m) => str_contains($m, '1 transférée(s)') && ! str_contains($m, 'vert'));
        $this->assertSame(1, $gateway->appended);
        $this->assertNull($service->sourceIntegration->fresh()->marked_row);
    }
}

class FakeBatchSheets implements GoogleSheetsGateway
{
    public array $backgrounds = [];

    public bool $failFormatting = false;

    public function setRowBackground(string $spreadsheetId, string $worksheet, int $row, ?array $rgb): void
    {
        if ($this->failFormatting) {
            throw new \RuntimeException('format refused');
        }

        $this->backgrounds[] = [$spreadsheetId, $row, $rgb === null ? 'clear' : 'green'];
    }

    public int $appended = 0;

    public function __construct(public array $sheets) {}

    public function isConfigured(): bool
    {
        return true;
    }

    public function configurationError(): ?string
    {
        return null;
    }

    public function accountEmail(): ?string
    {
        return 'sheets@example.com';
    }

    public function getSpreadsheet(string $spreadsheetId): array
    {
        return ['title' => 'T', 'sheets' => ['Réponses', 'orders']];
    }

    public function readValues(string $spreadsheetId, string $worksheet): array
    {
        return $this->sheets[$spreadsheetId] ?? [[]];
    }

    public function appendRow(string $spreadsheetId, string $worksheet, array $row): array
    {
        return $this->appendRows($spreadsheetId, $worksheet, [$row]);
    }

    public function appendRows(string $spreadsheetId, string $worksheet, array $rows): array
    {
        foreach ($rows as $row) {
            $this->sheets[$spreadsheetId][] = array_map(fn ($cell) => (string) $cell, $row);
            $this->appended++;
        }

        return ['updatedRange' => $worksheet.'!A2', 'updatedRows' => count($rows)];
    }
}
