<?php

namespace App\Providers;

use App\Models\Setting;
use App\Models\SyncLog;
use App\Models\TransferLog;
use App\Services\Sheets\GoogleSheetsClient;
use App\Services\Sheets\GoogleSheetsGateway;
use Carbon\Carbon;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(GoogleSheetsGateway::class, GoogleSheetsClient::class);
    }

    public function boot(): void
    {
        Carbon::setLocale(config('app.locale', 'fr'));

        View::composer(['layouts.app', 'partials.sidebar', 'partials.topbar'], function ($view) {
            $view->with('currentCountry', request()->route('country'));
            $view->with('appSettings', Setting::current());
        });

        View::composer('partials.topbar', function ($view) {
            $items = collect();

            TransferLog::query()
                ->with('order.service.country')
                ->where('status', 'failed')
                ->latest()
                ->limit(5)
                ->get()
                ->each(function (TransferLog $log) use ($items) {
                    $order = $log->order;
                    $country = $order?->service?->country;

                    if ($order === null || $country === null) {
                        return;
                    }

                    $items->push([
                        'title' => 'Échec de transfert · '.$order->reference,
                        'meta' => $log->message,
                        'time' => $log->created_at,
                        'url' => route('countries.orders.show', [$country, $order]),
                    ]);
                });

            SyncLog::query()
                ->with('service.country')
                ->where('result', 'failed')
                ->where('is_demo', false)
                ->latest()
                ->limit(5)
                ->get()
                ->each(function (SyncLog $log) use ($items) {
                    $country = $log->service?->country;

                    if ($country === null) {
                        return;
                    }

                    $items->push([
                        'title' => 'Synchronisation en échec · '.($log->service->name ?? 'Service'),
                        'meta' => $log->message,
                        'time' => $log->created_at,
                        'url' => route('countries.history.index', [$country, 'tab' => 'sync']),
                    ]);
                });

            $view->with('notificationItems', $items->sortByDesc('time')->take(8)->values());
        });
    }
}
