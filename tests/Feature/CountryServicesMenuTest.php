<?php

namespace Tests\Feature;

use App\Http\Controllers\ServiceController;
use App\Jobs\ValidateAllServiceOrdersJob;
use App\Models\Country;
use App\Models\PartnerService;
use App\Models\PushRun;
use App\Models\User;
use App\Services\Sheets\GoogleSheetsGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CountryServicesMenuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(GoogleSheetsGateway::class, fn () => throw new \RuntimeException('Google Sheets must not be called from the countries list.'));
    }

    public function test_each_country_row_lists_its_services_with_the_existing_push_action(): void
    {
        $user = User::factory()->create();
        $tanzania = $this->country('Tanzanie', 'TZ');
        $empty = $this->country('Zanzibar', 'ZZ');
        $sheet = $this->service($tanzania, 'CodPay Tanzanie', 'sheet');
        $api = $this->service($tanzania, 'Simon Express', 'api');
        $this->readySource($sheet);

        Cache::put(ServiceController::todayCountCacheKey($sheet), ['today' => 4, 'pending' => 4], 60);
        Cache::put(ServiceController::todayCountCacheKey($api), ['today' => 9, 'pending' => 9], 60);

        PushRun::query()->create([
            'service_id' => $api->id,
            'user_id' => $user->id,
            'status' => PushRun::PENDING,
        ]);

        $response = $this->actingAs($user)->get(route('countries.index'));

        $response->assertOk();
        $html = $response->getContent();
        $tanzaniaMenu = $this->dialog($html, $tanzania->id);
        $emptyMenu = $this->dialog($html, $empty->id);

        $this->assertStringContainsString('Voir les services de Tanzanie', $html);
        $this->assertStringContainsString('CodPay Tanzanie', $tanzaniaMenu);
        $this->assertStringContainsString('Google Sheet', $tanzaniaMenu);
        $this->assertStringContainsString('Simon Express', $tanzaniaMenu);
        $this->assertStringContainsString('Système (API) — Simon Express', $tanzaniaMenu);
        $this->assertStringContainsString('En attente d\'envoi', $tanzaniaMenu);
        $this->assertStringContainsString('Valider et push (4 en attente)', $tanzaniaMenu);
        $this->assertStringContainsString('Envoi…', $tanzaniaMenu);
        $this->assertStringContainsString(route('countries.services.show', [$tanzania, $sheet]), $tanzaniaMenu);
        $this->assertStringContainsString(route('countries.services.show', [$tanzania, $api]), $tanzaniaMenu);
        $this->assertStringContainsString(route('countries.services.validate-all', [$tanzania, $sheet]), $tanzaniaMenu);
        $this->assertStringContainsString(route('countries.services.validate-all', [$tanzania, $api]), $tanzaniaMenu);
        $this->assertStringNotContainsString('CodPay Tanzanie', $emptyMenu);
        $this->assertStringContainsString('Aucun service', $emptyMenu);
        $this->assertSame(2, substr_count($html, 'aria-label="Valider et push"'));
    }

    public function test_the_menu_push_button_queues_the_existing_job_without_running_it(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $country = $this->country('Tanzanie', 'TZ');
        $service = $this->service($country, 'CodPay Tanzanie', 'sheet');

        $this->actingAs($user)
            ->post(route('countries.services.validate-all', [$country, $service]))
            ->assertRedirect()
            ->assertSessionHas('success');

        Queue::assertPushed(ValidateAllServiceOrdersJob::class, function (ValidateAllServiceOrdersJob $job) use ($service) {
            return $job->serviceId === $service->id;
        });
    }

    private function dialog(string $html, int $countryId): string
    {
        $marker = 'id="country-services-'.$countryId.'"';
        $start = strpos($html, $marker);
        $this->assertNotFalse($start, 'Missing services menu for country '.$countryId);
        $next = strpos($html, 'id="country-services-', $start + strlen($marker));
        $end = $next === false ? strpos($html, '</template>', $start) : $next;
        $end = $end === false ? strlen($html) : $end;

        return substr($html, $start, $end - $start);
    }

    private function country(string $name, string $code): Country
    {
        return Country::query()->create([
            'name' => $name,
            'code' => $code,
            'currency' => 'TZS',
            'is_active' => true,
        ]);
    }

    private function service(Country $country, string $name, string $destination): PartnerService
    {
        return $country->services()->create([
            'name' => $name,
            'is_active' => true,
            'destination_type' => $destination,
        ]);
    }

    private function readySource(PartnerService $service): void
    {
        $service->integrations()->create([
            'direction' => 'source',
            'spreadsheet_id' => 'sheet-'.$service->id,
            'worksheet_name' => 'Commandes',
            'connection_status' => 'connected',
        ]);
    }
}
