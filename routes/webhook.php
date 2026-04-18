<?php

use Illuminate\Support\Facades\Route;

Route::post('/telegram', [App\Http\Controllers\Webhook\TelegramWebhookController::class, 'handle'])
    ->middleware('telegram.webhook');
