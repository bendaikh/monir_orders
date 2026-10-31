<?php

namespace App\Http\Controllers;

use App\Http\Requests\SettingsRequest;
use App\Models\Setting;
use App\Services\Sheets\GoogleSheetsGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(GoogleSheetsGateway $gateway): View
    {
        return view('settings.index', [
            'settings' => Setting::current(),
            'sheetsReady' => $gateway->isConfigured(),
            'sheetsError' => $gateway->configurationError(),
            'accountEmail' => $gateway->accountEmail(),
        ]);
    }

    public function update(SettingsRequest $request): RedirectResponse
    {
        $data = $request->safe()->only([
            'company_name',
            'support_email',
            'default_currency',
            'sync_interval_minutes',
        ]);

        $settings = Setting::query()->first();

        if ($settings) {
            $settings->update($data);
        } else {
            Setting::query()->create($data);
        }

        $request->user()->update([
            'rows_per_page' => $request->integer('rows_per_page'),
        ]);

        return back()->with('success', 'Les paramètres ont été enregistrés.');
    }
}
