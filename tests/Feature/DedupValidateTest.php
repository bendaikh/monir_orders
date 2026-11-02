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

    public function test_validate_all_pushes_once_skips_duplicates_and_second_click_pushes_nothing(): void
    {
        [$user, $country, $service, $gateway] = $this->setUpCodpayLike([
            ['28/02/2026 02:32:10', 'mm5gd1rz', 'Fathi', '58450856', 'Médenine', '', '2', '82.000', ''],
            ['28/02/2026 09:10:00', 'mm5gd1rz', 'Fathi', '58450856', 'Médenine', '', '1', '41.000', ''],
            ['13/09/2026 08:18:14', 'thermal', 'Gaston', '078548598', 'A', '', '1', '85.000', ''],
            ['13/09/2026 08:18:14', 'thermal', 'Linus', '0762505329', 'B', '', '1', '85.000', ''],
        ]);

        $this->actingAs($user)->get(route('countries.services.edit', [$country, $service]))
            ->assertOk()->assertSee('Règle de détection des doublons');
        $this->actingAs($user)->post(route('countries.services.import', [$country, $service]));
        $this->actingAs($user)->get(route('countries.services.show', [$country, $service]))
            ->assertOk()->assertSee('Valider toutes les commandes en attente (3)');

        $this->actingAs($user)->post(route('countries.services.validate-all', [$country, $service]))
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame(3, $gateway->appended);
        $this->assertSame(3, Order::query()->where('transfer_status', TransferStatus::Transferred->value)->count());
        $this->assertSame(1, Order::query()->where('validation_status', ValidationStatus::Duplicate->value)->count());
        $this->assertSame(['CP-1', 'mm5gd1rz', 'Fathi', '58450856', '', 'Médenine', '2.00', '82.00', ''], $gateway->sheets['dst'][1]);

        $this->actingAs($user)->post(route('countries.services.import', [$country, $service]));
        $this->actingAs($user)->post(route('countries.services.validate-all', [$country, $service]))->assertRedirect();
        $this->assertSame(3, $gateway->appended);
    }

    public function test_reference_already_in_destination_is_not_pushed_again(): void
    {
        [$user, $country, $service, $gateway] = $this->setUpCodpayLike(
            [['28/02/2026 02:32:10', 'mm5gd1rz', 'Fathi', '58450856', 'Médenine', '', '2', '82.000', '']],
            [['CP-1', 'mm5gd1rz', 'Fathi', '58450856', '', 'Médenine', '2', '82', '']],
        );

        $this->actingAs($user)->post(route('countries.services.import', [$country, $service]));
        $this->actingAs($user)->post(route('countries.services.validate-all', [$country, $service]));

        $this->assertSame(0, $gateway->appended);
        $this->assertSame(TransferStatus::Transferred, Order::query()->sole()->transfer_status);
    }
}

class FakeBatchSheets implements GoogleSheetsGateway
{
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
