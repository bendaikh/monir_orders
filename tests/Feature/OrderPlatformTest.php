<?php

namespace Tests\Feature;

use App\Enums\OrderDisplayStatus;
use App\Enums\TransferStatus;
use App\Enums\ValidationStatus;
use App\Models\Country;
use App\Models\Order;
use App\Models\PartnerService;
use App\Models\User;
use App\Services\Sheets\GoogleSheetsException;
use App\Services\Sheets\GoogleSheetsGateway;
use App\Services\Sheets\PartnerServiceProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderPlatformTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_the_dashboard(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_super_admin_can_sign_in_and_open_the_main_pages(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@monir-orders.local',
            'password' => 'password',
        ]);
        $country = $this->country();

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->actingAs($user)
            ->get(route('dashboard'))->assertOk()
            ->assertSee('Tableau de bord');

        $this->actingAs($user)->get(route('countries.index'))->assertOk();
        $this->actingAs($user)->get(route('countries.dashboard', $country))->assertOk();
        $this->actingAs($user)->get(route('countries.services.index', $country))->assertOk();
        $this->actingAs($user)->get(route('countries.orders.index', $country))->assertOk();
        $this->actingAs($user)->get(route('countries.validation.index', $country))->assertOk();
        $this->actingAs($user)->get(route('countries.history.index', $country))->assertOk();
        $this->actingAs($user)->get(route('countries.sheets.index', $country))->assertOk();
        $this->actingAs($user)->get(route('settings.index'))->assertOk();
        $this->actingAs($user)->get(route('profile.edit'))->assertOk();
    }

    public function test_orders_stay_inside_their_country(): void
    {
        $user = User::factory()->create();
        $morocco = $this->country('Maroc', 'MA');
        $france = $this->country('France', 'FR');
        $service = $this->service($morocco);
        $order = $this->order($service, 'MA-1');

        $this->actingAs($user)
            ->get(route('countries.orders.show', [$france, $order]))
            ->assertNotFound();

        $this->actingAs($user)
            ->get(route('countries.orders.show', [$morocco, $order]))
            ->assertOk()
            ->assertSee('MA-1');
    }

    public function test_validation_without_google_credentials_does_not_mark_the_order_transferred(): void
    {
        $user = User::factory()->create();
        $country = $this->country();
        $order = $this->order($this->service($country), 'MA-9');

        $this->actingAs($user)
            ->post(route('countries.validation.validate', [$country, $order]))
            ->assertRedirect()
            ->assertSessionHas('error');

        $order->refresh();

        $this->assertSame(ValidationStatus::Validated, $order->validation_status);
        $this->assertSame(TransferStatus::Failed, $order->transfer_status);
        $this->assertSame(OrderDisplayStatus::TransferFailed, $order->displayStatus());
        $this->assertNull($order->transferred_at);
        $this->assertDatabaseHas('validation_logs', [
            'order_id' => $order->id,
            'decision' => 'validated',
        ]);
        $this->assertDatabaseHas('transfer_logs', [
            'order_id' => $order->id,
            'status' => 'failed',
        ]);
    }

    public function test_rejection_does_not_send_the_order(): void
    {
        $user = User::factory()->create();
        $country = $this->country();
        $order = $this->order($this->service($country), 'MA-10');

        $this->actingAs($user)
            ->post(route('countries.validation.reject', [$country, $order]), [
                'reason' => 'Client injoignable',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $order->refresh();

        $this->assertSame(ValidationStatus::Rejected, $order->validation_status);
        $this->assertSame(TransferStatus::None, $order->transfer_status);
        $this->assertDatabaseCount('transfer_logs', 0);
        $this->assertDatabaseHas('validation_logs', [
            'order_id' => $order->id,
            'decision' => 'rejected',
            'reason' => 'Client injoignable',
        ]);
    }

    public function test_import_skips_duplicate_references_and_transfer_checks_existing_rows(): void
    {
        $user = User::factory()->create();
        $country = $this->country();
        $service = $this->service($country);
        $service->sourceIntegration->update([
            'spreadsheet_id' => 'source-sheet',
            'worksheet_name' => 'Commandes',
        ]);
        $service->destinationIntegration->update([
            'spreadsheet_id' => 'dest-sheet',
            'worksheet_name' => 'Sortie',
        ]);

        $this->app->instance(GoogleSheetsGateway::class, new FakeGoogleSheets([
            'source-sheet' => [
                ['Référence', 'Nom du client', 'Téléphone', 'Ville', 'Adresse', 'Montant total'],
                ['CMD-1', 'Amina Benali', '0600000000', 'Casablanca', 'Rue 1', '120,50'],
                ['CMD-1', 'Doublon', '0600000001', 'Rabat', 'Rue 2', '10'],
            ],
            'dest-sheet' => [
                ['Order ID', 'Customer Name', 'Phone', 'City', 'Address', 'Total', 'Order Date', 'Product', 'Quantity', 'Unit Price'],
            ],
        ]));

        $this->actingAs($user)
            ->post(route('countries.services.import', [$country, $service]))
            ->assertRedirect()
            ->assertSessionHas('success');

        // Same reference on a different row is kept visible as a duplicate, never pushed.
        $this->assertSame(2, Order::query()->count());
        $this->assertSame(1, Order::query()->where('validation_status', ValidationStatus::Duplicate->value)->count());

        $this->actingAs($user)
            ->post(route('countries.services.import', [$country, $service]))
            ->assertRedirect();

        $this->assertSame(2, Order::query()->count());

        $order = Order::query()->where('reference', 'CMD-1')->first();
        $this->actingAs($user)
            ->post(route('countries.validation.validate', [$country, $order]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertSame(TransferStatus::Transferred, $order->transfer_status);
        $this->assertNotNull($order->transferred_at);
    }

    public function test_an_ambiguous_write_does_not_append_a_second_time_when_the_reference_is_already_present(): void
    {
        $user = User::factory()->create();
        $country = $this->country();
        $service = $this->service($country);
        $service->destinationIntegration->update([
            'spreadsheet_id' => 'dest-sheet',
            'worksheet_name' => 'Sortie',
        ]);
        $order = $this->order($service, 'CMD-EXISTE');
        $order->update([
            'validation_status' => ValidationStatus::Validated,
            'validated_at' => now(),
        ]);

        $gateway = new FakeGoogleSheets([
            'dest-sheet' => [
                ['Order ID', 'Customer Name', 'Phone', 'City', 'Address', 'Total', 'Order Date', 'Product', 'Quantity', 'Unit Price'],
            ],
        ]);
        $gateway->failAppend = true;
        $this->app->instance(GoogleSheetsGateway::class, $gateway);

        $this->actingAs($user)
            ->post(route('countries.orders.retry', [$country, $order]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertSame(TransferStatus::Transferred, $order->transfer_status);
        $this->assertSame(1, $gateway->appends);
    }

    private function country(string $name = 'Maroc', string $code = 'MA'): Country
    {
        return Country::query()->create([
            'name' => $name,
            'code' => $code,
            'currency' => 'MAD',
            'is_active' => true,
        ]);
    }

    private function service(Country $country): PartnerService
    {
        $service = $country->services()->create([
            'name' => 'Atlas '.$country->code,
            'description' => 'Partenaire',
            'is_active' => true,
        ]);

        return app(PartnerServiceProvisioner::class)->provision($service);
    }

    private function order(PartnerService $service, string $reference): Order
    {
        return $service->orders()->create([
            'reference' => $reference,
            'customer_name' => 'Amina Benali',
            'phone' => '0600000000',
            'city' => 'Casablanca',
            'address' => 'Rue 1',
            'total_amount' => 120,
            'received_at' => now(),
            'validation_status' => ValidationStatus::Pending,
            'transfer_status' => TransferStatus::None,
            'is_demo' => false,
        ]);
    }
}

class FakeGoogleSheets implements GoogleSheetsGateway
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

    public int $appends = 0;

    public bool $failAppend = false;

    public function __construct(private array $sheets) {}

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
        return ['title' => 'Tableur', 'sheets' => ['Commandes', 'Sortie']];
    }

    public function readValues(string $spreadsheetId, string $worksheet): array
    {
        return $this->sheets[$spreadsheetId] ?? [[]];
    }

    public function appendRow(string $spreadsheetId, string $worksheet, array $row): array
    {
        $this->appends++;
        $this->sheets[$spreadsheetId][] = array_map(fn ($cell) => (string) $cell, $row);

        if ($this->failAppend) {
            throw new GoogleSheetsException('Délai dépassé.', ambiguous: true);
        }

        return ['updatedRange' => 'Sortie!A2:J2', 'updatedRows' => 1];
    }

    public function appendRows(string $spreadsheetId, string $worksheet, array $rows): array
    {
        foreach ($rows as $row) {
            $this->appendRow($spreadsheetId, $worksheet, $row);
        }

        return ['updatedRange' => 'Sortie!A2', 'updatedRows' => count($rows)];
    }
}
