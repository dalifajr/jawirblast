<?php

use App\Http\Controllers\Api\TelegramWebhookController;
use App\Http\Controllers\Api\WaWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| Seluruh rute di sini otomatis berada di bawah prefix /api dan stateless.
| Pengecualian CSRF telah didaftarkan pada bootstrap/app.php.
*/

Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handleWebhook'])->name('telegram.webhook');
Route::post('/wa/webhook', [WaWebhookController::class, 'handleWebhook'])->name('wa.webhook');
