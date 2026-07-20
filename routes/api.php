<?php

use App\Http\Controllers\Api\MetaWebhookController;
use App\Http\Controllers\Api\TelegramWebhookController;
use App\Http\Controllers\Api\ViberWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/viber/webhook', ViberWebhookController::class);
Route::post('/telegram/webhook', TelegramWebhookController::class);
Route::match(['get', 'post'], '/meta/webhook', MetaWebhookController::class);

