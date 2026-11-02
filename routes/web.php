<?php

use App\Http\Controllers\ApiDestinationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\CountryDashboardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SheetIntegrationController;
use App\Http\Controllers\ValidationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/connexion', [LoginController::class, 'create'])->name('login');
    Route::post('/connexion', [LoginController::class, 'store'])->middleware('throttle:5,1');
});

Route::post('/deconnexion', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/tableau-de-bord', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/commandes', [OrderController::class, 'global'])->name('orders.global');

    Route::get('/pays', [CountryController::class, 'index'])->name('countries.index');
    Route::post('/pays', [CountryController::class, 'store'])->name('countries.store');
    Route::put('/pays/{country}', [CountryController::class, 'update'])->name('countries.update');
    Route::patch('/pays/{country}/statut', [CountryController::class, 'toggle'])->name('countries.toggle');
    Route::get('/pays/{country}', [CountryController::class, 'show'])->name('countries.show');

    Route::prefix('pays/{country}')->scopeBindings()->name('countries.')->group(function () {
        Route::get('/tableau-de-bord', [CountryDashboardController::class, 'index'])->name('dashboard');

        Route::get('/commandes', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/commandes/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::put('/commandes/{order}', [OrderController::class, 'update'])->name('orders.update');
        Route::post('/commandes/{order}/reessayer', [ValidationController::class, 'retry'])->name('orders.retry');

        Route::get('/validation', [ValidationController::class, 'index'])->name('validation.index');
        Route::post('/validation/lot', [ValidationController::class, 'bulk'])->name('validation.bulk');
        Route::post('/validation/{order}/valider', [ValidationController::class, 'validateOrder'])->name('validation.validate');
        Route::post('/validation/{order}/rejeter', [ValidationController::class, 'reject'])->name('validation.reject');

        Route::get('/historique', [HistoryController::class, 'index'])->name('history.index');

        Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
        Route::get('/services/creer', [ServiceController::class, 'create'])->name('services.create');
        Route::post('/services', [ServiceController::class, 'store'])->name('services.store');
        Route::get('/services/{service}', [ServiceController::class, 'show'])->name('services.show');
        Route::get('/services/{service}/modifier', [ServiceController::class, 'edit'])->name('services.edit');
        Route::put('/services/{service}', [ServiceController::class, 'update'])->name('services.update');
        Route::patch('/services/{service}/statut', [ServiceController::class, 'toggle'])->name('services.toggle');
        Route::post('/services/{service}/valider-tout', [ValidationController::class, 'validateAll'])->name('services.validate-all');
        Route::get('/services/{service}/systeme', [ApiDestinationController::class, 'edit'])->name('services.api.edit');
        Route::put('/services/{service}/systeme', [ApiDestinationController::class, 'update'])->name('services.api.update');
        Route::post('/services/{service}/systeme/tester', [ApiDestinationController::class, 'test'])->name('services.api.test');
        Route::post('/services/{service}/importer', [SheetIntegrationController::class, 'import'])->name('services.import');
        Route::post('/services/{service}/tester-envoi', [SheetIntegrationController::class, 'testSend'])->name('services.test-send');
        Route::post('/services/{service}/integration/{direction}/tester', [SheetIntegrationController::class, 'test'])->name('services.test');

        Route::get('/integrations', [SheetIntegrationController::class, 'index'])->name('sheets.index');
    });

    Route::get('/parametres', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('/parametres', [SettingsController::class, 'update'])->name('settings.update');
    Route::get('/profil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profil', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profil/mot-de-passe', [ProfileController::class, 'password'])->name('profile.password');
});
