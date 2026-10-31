<?php

namespace App\Http\Controllers;

use App\Jobs\ImportServiceOrdersJob;
use Illuminate\Support\Facades\Bus;
use App\Models\Country;
use App\Models\PartnerService;
use App\Services\Sheets\GoogleSheetsException;
use App\Services\Sheets\GoogleSheetsGateway;
use App\Services\Sheets\SheetConnectionTester;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SheetIntegrationController extends Controller
{
    public function index(Country $country, GoogleSheetsGateway $gateway): View
    {
        $services = $country->services()
            ->with(['sourceIntegration', 'destinationIntegration'])
            ->withCount('orders')
            ->orderBy('name')
            ->get();

        return view('sheets.index', [
            'country' => $country,
            'services' => $services,
            'sheetsReady' => $gateway->isConfigured(),
            'sheetsError' => $gateway->configurationError(),
            'accountEmail' => $gateway->accountEmail(),
        ]);
    }

    public function test(Country $country, PartnerService $service, string $direction, SheetConnectionTester $tester): RedirectResponse
    {
        $integration = $this->integration($service, $direction);

        try {
            $message = $tester->test($integration, request()->user());
        } catch (GoogleSheetsException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', $message);
    }

    public function import(Country $country, PartnerService $service): RedirectResponse
    {
        if (! $service->is_active || ! $country->is_active) {
            return back()->with('error', 'Activez le pays et le service avant d\'importer des commandes.');
        }

        $log = Bus::dispatchNow(new ImportServiceOrdersJob($service->id, request()->user()->id, 'manual'));
        $flash = $log->result === 'failed' ? 'error' : ($log->result === 'partial' ? 'warning' : 'success');

        return back()->with($flash, $log->message ?: 'Import terminé.');
    }

    public function testSend(Country $country, PartnerService $service, SheetConnectionTester $tester): RedirectResponse
    {
        $integration = $service->destinationIntegration;

        if ($integration === null) {
            return back()->with('error', 'La destination de ce service n\'existe pas encore.');
        }

        try {
            $message = $tester->testSend($integration, request()->user());
        } catch (GoogleSheetsException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', $message);
    }

    private function integration(PartnerService $service, string $direction)
    {
        abort_unless(in_array($direction, ['source', 'destination'], true), 404);

        $integration = $service->integrations()->where('direction', $direction)->first();
        abort_if($integration === null, 404);

        return $integration;
    }
}
