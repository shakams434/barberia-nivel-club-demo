<?php

use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify'])
    ->middleware('throttle:webhooks')
    ->name('whatsapp.webhook.verify');

Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'receive'])
    ->middleware('throttle:webhooks')
    ->name('whatsapp.webhook.receive');

Route::post('/webhooks/whatsapp-bot', [WhatsAppWebhookController::class, 'receiveBot'])
    ->middleware('throttle:webhooks')
    ->name('whatsapp.webhook.bot.receive');
