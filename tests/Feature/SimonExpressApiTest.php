<?php

namespace Tests\Feature;

use App\Enums\TransferStatus;
use App\Enums\ValidationStatus;
use App\Models\ColumnMapping;
use App\Models\Country;
use App\Models\Order;
use App\Models\User;
use App\Services\Api\CityMatcher;
use App\Services\Sheets\GoogleSheetsGateway;
use App\Services\Sheets\PartnerServiceProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SimonExpressApiTest extends TestCase
{
    use RefreshDatabase;

    private function setUpApiService(array $rows): array
    {
        $this->travelTo(now()->setDate(2026, 11, 2)->setTime(20, 0));
        $user = User::factory()->create();
        $country = Country::query()->create(['name' => 'Tanzanie', 'code' => 'TZ', 'currency' => 'TZS', 'is_active' => true]);
        $service = $country->services()->create([
            'name' => 'Simon', 'is_active' => true, 'reference_prefix' => 'SX-', 'decimal_format' => 'comma',
            'date_format' => 'd/m/Y H:i:s', 'phone_digits' => 9, 'destination_type' => 'api',
            'api_config' => ['provider' => 'simon_express', 'base_url' => 'https://simonexpress.net', 'auth_mode' => 'bearer', 'token' => 'tk_secret'],
            'api_city_fallback' => 'ANOTHER-CITY',
        ]);
        app(PartnerServiceProvisioner::class)->provision($service);
        $service->sourceIntegration->update(['spreadsheet_id' => 'src', 'worksheet_name' => 'R']);

        foreach (['reference' => '(non mappé)', 'customer_name' => 'Nom', 'phone' => 'Tel', 'city' => 'Ville', 'address' => 'Address',
            'total_amount' => 'Price', 'ordered_at' => 'Horodateur', 'product' => 'Produit Ref', 'quantity' => 'qte'] as $field => $column) {
            ColumnMapping::query()->updateOrCreate(['service_id' => $service->id, 'direction' => 'source', 'order_field' => $field], ['column_name' => $column]);
        }

        $service->productMappings()->create(['source_product' => 'car_engine-1416-dar', 'sku' => 'Belt-Silencer-Automotive-Engine-176']);
        $service->productMappings()->create(['source_product' => 'automatic_chicken-8873-dar', 'sku' => 'chicken-water-drinking-cups-176']);

        $gateway = new FakeBatchSheets(['src' => array_merge([['Horodateur', 'Produit Ref', 'Nom', 'Tel', 'Address', 'Ville', 'qte', 'Price']], $rows)]);
        $this->app->instance(GoogleSheetsGateway::class, $gateway);

        return [$user, $country, $service->fresh(), $gateway];
    }

    public function test_api_credentials_are_encrypted_at_rest(): void
    {
        [, , $service] = $this->setUpApiService([]);
        $raw = DB::table('services')->where('id', $service->id)->value('api_config');

        $this->assertStringNotContainsString('tk_secret', $raw);
        $this->assertSame('tk_secret', $service->api_config['token']);
    }

    public function test_validate_all_posts_each_order_once_and_handles_errors(): void
    {
        [$user, $country, $service, $gateway] = $this->setUpApiService([
            ['02/11/2026 03:08:31', 'automatic_chicken-8873-dar', 'Damas', '0753670737', 'Tabora town', 'Tabora', '', '2=145000'],
            ['02/11/2026 05:13:52', 'car_engine-1416-dar', 'Malki', '0674997223', 'Moshi kati', 'moshi', '', '85000'],
            ['02/11/2026 05:20:00', 'unknown-product-dar', 'Nobody', '0711111111', 'x', 'Arusha', '', '85000'],
            ['02/11/2026 06:00:00', 'car_engine-1416-dar', 'Zed', '0722222222', 'Somewhere', 'Kijijini', '', '85000'],
            ['02/11/2026 07:00:00', 'car_engine-1416-dar', 'Malki', '+255 674 997 223', 'Moshi kati', 'Moshi', '', '85000'],
        ]);

        $n = 0;
        Http::fake(['simonexpress.net/add_lead' => function () use (&$n) {
            $n++;

            return $n === 3
                ? Http::response(['success' => false, 'message' => 'bad city'], 200)
                : Http::response(['success' => true, 'message' => 'Parcel added successfully', 'parcel_code' => 'PC'.$n], 200);
        }]);

        $this->actingAs($user)->post(route('countries.services.validate-all', [$country, $service]))
            ->assertSessionHas('warning', fn ($m) => str_contains($m, '2 transférée(s), 1 doublon(s) supprimé(s) (non envoyés), 2 échec(s)'));

        Http::assertSentCount(3);
        Http::assertSent(fn (Request $r) => $r->url() === 'https://simonexpress.net/add_lead'
            && $r->hasHeader('Authorization', 'Bearer tk_secret')
            && $r['receiver'] === 'Damas' && $r['phone'] === '+255753670737' && $r['city'] === 'TABORA' && $r['country'] === 'Tanzania'
            && $r['items'] === [['sku' => 'chicken-water-drinking-cups-176', 'quantity' => 2, 'price' => 145000]]);
        Http::assertSent(fn (Request $r) => $r['receiver'] === 'Zed' && $r['city'] === 'ANOTHER-CITY');

        $unmapped = Order::query()->where('customer_name', 'Nobody')->sole();
        $this->assertSame(TransferStatus::Failed, $unmapped->transfer_status);
        $this->assertStringStartsWith('erreur: produit non mappé', $unmapped->api_error);
        $this->assertSame('PC1', Order::query()->where('customer_name', 'Damas')->sole()->parcel_code);
        $this->assertStringContainsString('bad city', Order::query()->where('customer_name', 'Zed')->sole()->api_error);
        $this->assertSame(ValidationStatus::Duplicate, Order::query()->where('source_row_number', 6)->sole()->validation_status);
        $this->assertSame(3, $service->sourceIntegration->fresh()->marked_row);

        // Map the missing product and press again: only the failed orders are retried, sent ones never.
        $service->productMappings()->create(['source_product' => 'unknown-product-dar', 'sku' => 'magnetic-GPS-176', 'price_override' => 90000]);
        $this->actingAs($user)->post(route('countries.services.validate-all', [$country, $service]));

        Http::assertSentCount(5);
        Http::assertSent(fn (Request $r) => $r['receiver'] === 'Nobody' && $r['city'] === 'ARUSHA' && $r['items'][0]['price'] == 90000);
        $this->assertSame(4, Order::query()->whereNotNull('parcel_code')->count());

        $this->actingAs($user)->post(route('countries.services.validate-all', [$country, $service]));
        Http::assertSentCount(5);
    }

    public function test_network_errors_are_retried_and_never_mark_sent(): void
    {
        [$user, $country, $service] = $this->setUpApiService([
            ['02/11/2026 05:13:52', 'car_engine-1416-dar', 'Malki', '0674997223', 'Moshi kati', 'Moshi', '', '85000'],
        ]);
        config(['simon_express.retries' => 2]);
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->actingAs($user)->post(route('countries.services.validate-all', [$country, $service]));

        $order = Order::query()->sole();
        $this->assertNull($order->parcel_code);
        $this->assertStringContainsString('erreur réseau', $order->api_error);
    }

    public function test_settings_page_saves_masked_credentials_and_mappings_and_tests_connection(): void
    {
        [$user, $country, $service] = $this->setUpApiService([]);

        $this->actingAs($user)->get(route('countries.services.api.edit', [$country, $service]))
            ->assertOk()->assertSee('Tester la connexion')->assertDontSee('tk_secret')->assertSee('Belt-Silencer-Automotive-Engine-176');

        $this->actingAs($user)->put(route('countries.services.api.update', [$country, $service]), [
            'auth_mode' => 'basic', 'consumer_key' => 'ck_1', 'consumer_secret' => 'cs_2', 'token' => '',
            'api_price_mode' => 'unit', 'api_city_fallback' => 'ANOTHER-CITY',
            'products' => [['source' => 'lure_her-2455-dar', 'sku' => 'Lure-Her-Pheromone-Perfume-Africo-market-(176)', 'price' => '']],
            'cities' => [['source' => 'Tegete mivumon', 'city' => 'DAR SALAM']],
        ])->assertRedirect();

        $service->refresh();
        $this->assertSame(['ck_1', 'cs_2', 'tk_secret'], [$service->api_config['consumer_key'], $service->api_config['consumer_secret'], $service->api_config['token']]);
        $this->assertSame('unit', $service->api_price_mode);
        $this->assertSame(3, $service->productMappings()->count());
        $this->assertSame('DAR SALAM', $service->cityMappings()->sole()->city);

        Http::fake(['simonexpress.net/get_lead*' => Http::sequence()
            ->push(['success' => false, 'message' => 'not found'], 404)
            ->push('Unauthorized', 401)]);
        $this->actingAs($user)->post(route('countries.services.api.test', [$country, $service]))->assertSessionHas('success');
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'get_lead?parcel_code=TEST') && $r->hasHeader('Authorization', 'Basic '.base64_encode('ck_1:cs_2')));

        $this->actingAs($user)->post(route('countries.services.api.test', [$country, $service]))->assertSessionHas('error');
    }

    public function test_city_matcher(): void
    {
        $m = new CityMatcher;
        $this->assertSame('DAR SALAM', $m->fuzzy('Dar es salama'));
        $this->assertSame('ARUSHA', $m->fuzzy('Arushaa'));
        $this->assertSame('MWANZA-', $m->fuzzy('Ngudu - Kwimba Mwanza'));
        $this->assertSame('mbeya-uyole', $m->fuzzy('Mbeya Uyole'));
        $this->assertSame('MBEYA', $m->fuzzy('MBEYA VIJIJINI KATA ISUTO'));
        $this->assertNull($m->fuzzy('tz'));
    }
}
