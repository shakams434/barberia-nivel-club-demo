<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\CelebrationController;
use App\Http\Controllers\ConsentController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\JoinController;
use App\Http\Controllers\MessageAutomationController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\RewardRedemptionController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\VisitController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : redirect()->route('login'));
Route::get('/health', fn () => response()->json(['status' => 'ok']))->name('health');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.update');
});

Route::get('/join/{businessSlug}', [JoinController::class, 'show'])->name('join.show');
Route::get('/join/{businessSlug}/qr', [JoinController::class, 'qr'])->name('join.qr');

Route::middleware(['auth', 'tenant'])->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/celebraciones', CelebrationController::class)->name('celebrations.index');

    Route::get('/clientes', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('/clientes/nuevo', [CustomerController::class, 'create'])->name('customers.create');
    Route::post('/clientes', [CustomerController::class, 'store'])->name('customers.store');
    Route::get('/clientes/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    Route::get('/clientes/{customer}/editar', [CustomerController::class, 'edit'])->name('customers.edit');
    Route::put('/clientes/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    Route::get('/clientes/{customer}/exportar', [CustomerController::class, 'export'])->name('customers.export');
    Route::get('/clientes-exportar.csv', [CustomerController::class, 'exportCsv'])->name('customers.export.csv');
    Route::post('/clientes/{customer}/anonimizar', [CustomerController::class, 'anonymize'])->name('customers.anonymize');
    Route::post('/clientes/{customer}/consentimientos', [ConsentController::class, 'store'])->name('customers.consents.store');
    Route::post('/atenciones/{visit}/revertir', [VisitController::class, 'reverse'])->name('visits.reverse');
    Route::post('/recompensas/{reward}/canjear', [RewardRedemptionController::class, 'store'])->name('rewards.redeem');
    Route::post('/canjes/{redemption}/revertir', [RewardRedemptionController::class, 'reverse'])->name('redemptions.reverse');

    Route::get('/campanas', [CampaignController::class, 'index'])->name('campaigns.index');
    Route::get('/campanas/nueva', [CampaignController::class, 'create'])->name('campaigns.create');
    Route::post('/campanas', [CampaignController::class, 'store'])->name('campaigns.store');
    Route::get('/campanas/{campaign}', [CampaignController::class, 'show'])->name('campaigns.show');
    Route::post('/campanas/{campaign}/confirmar', [CampaignController::class, 'confirm'])->name('campaigns.confirm');
    Route::post('/campanas/{campaign}/pausar', [CampaignController::class, 'pause'])->name('campaigns.pause');
    Route::post('/campanas/{campaign}/reanudar', [CampaignController::class, 'resume'])->name('campaigns.resume');
    Route::post('/campanas/{campaign}/cancelar', [CampaignController::class, 'cancel'])->name('campaigns.cancel');

    Route::get('/mensajes', [MessageController::class, 'index'])->name('messages.index');
    Route::post('/mensajes/{message}/reintentar', [MessageController::class, 'retry'])->name('messages.retry');
    Route::post('/mensajes/{message}/simular', [MessageController::class, 'simulate'])->name('messages.simulate');

    Route::get('/configuracion', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('/configuracion/negocio', [SettingsController::class, 'updateBusiness'])->name('settings.business');
    Route::put('/configuracion/programa', [SettingsController::class, 'updateProgram'])->name('settings.program');
    Route::post('/configuracion/servicios', [SettingsController::class, 'storeService'])->name('settings.services.store');
    Route::put('/configuracion/servicios/{service}', [SettingsController::class, 'updateService'])->name('settings.services.update');
    Route::post('/configuracion/rangos', [SettingsController::class, 'storeTier'])->name('settings.tiers.store');
    Route::put('/configuracion/rangos/{tier}', [SettingsController::class, 'updateTier'])->name('settings.tiers.update');
    Route::post('/configuracion/recompensas', [SettingsController::class, 'storeReward'])->name('settings.rewards.store');
    Route::put('/configuracion/recompensas/{reward}', [SettingsController::class, 'updateReward'])->name('settings.rewards.update');
    Route::put('/configuracion/whatsapp', [SettingsController::class, 'updateWhatsApp'])->name('settings.whatsapp');
    Route::put('/configuracion/contrasena', [SettingsController::class, 'updatePassword'])->name('settings.password');
    Route::get('/configuracion/whatsapp/health', [SettingsController::class, 'health'])->name('settings.whatsapp.health');
    Route::post('/configuracion/whatsapp/probar', [SettingsController::class, 'testWhatsApp'])->name('settings.whatsapp.test');
    Route::post('/configuracion/plantillas', [TemplateController::class, 'store'])->name('templates.store');
    Route::put('/configuracion/plantillas/{template}', [TemplateController::class, 'update'])->name('templates.update');
    Route::put('/configuracion/plantillas/{template}/estado', [TemplateController::class, 'status'])->name('templates.status');
    Route::put('/configuracion/automatizaciones', [MessageAutomationController::class, 'update'])->name('automations.update');
    Route::delete('/configuracion/automatizaciones/{eventKey}', [MessageAutomationController::class, 'disable'])->name('automations.disable');
});
