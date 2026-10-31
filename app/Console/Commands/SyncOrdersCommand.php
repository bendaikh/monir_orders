<?php

namespace App\Console\Commands;

use App\Jobs\ImportServiceOrdersJob;
use App\Models\PartnerService;
use App\Models\Setting;
use App\Services\Sheets\GoogleSheetsGateway;
use Illuminate\Console\Command;

class SyncOrdersCommand extends Command
{
    protected $signature = 'orders:sync';

    protected $description = 'Planifie l\'import des commandes depuis les feuilles Google Sheets source';

    public function handle(GoogleSheetsGateway $gateway): int
    {
        if (! $gateway->isConfigured()) {
            $this->warn($gateway->configurationError() ?? 'Identifiants Google Sheets absents.');

            return self::SUCCESS;
        }

        $interval = max(5, (int) Setting::current()->sync_interval_minutes);
        $dispatched = 0;

        PartnerService::query()
            ->where('is_active', true)
            ->whereHas('country', fn ($query) => $query->where('is_active', true))
            ->whereHas('sourceIntegration', function ($query) {
                $query->whereNotNull('spreadsheet_id')
                    ->where('spreadsheet_id', '!=', '')
                    ->whereNotNull('worksheet_name')
                    ->where('worksheet_name', '!=', '');
            })
            ->with('syncLogs')
            ->orderBy('id')
            ->each(function (PartnerService $service) use ($interval, &$dispatched) {
                $last = $service->syncLogs
                    ->where('type', 'scheduled')
                    ->sortByDesc('started_at')
                    ->first();

                if ($last?->started_at && $last->started_at->gt(now()->subMinutes($interval))) {
                    return;
                }

                ImportServiceOrdersJob::dispatch($service->id, null, 'scheduled');
                $dispatched++;
            });

        $this->info($dispatched.' import(s) planifié(s).');

        return self::SUCCESS;
    }
}
