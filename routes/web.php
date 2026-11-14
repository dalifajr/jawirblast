<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContactGroupController;
use App\Http\Controllers\ContactImportExportController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\TelegramAuthController;
use App\Http\Controllers\WhatsAppSessionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Halaman utama
Route::get('/', function () {
    return view('welcome');
});

// Pergantian Multi-Bahasa (Locale Switcher)
Route::get('/lang/{locale}', [LocaleController::class, 'switch'])->name('lang.switch');

// Rute untuk Pengguna Tamu (Guest Only)
Route::middleware('guest')->group(function () {
    // Form & Aksi Autentikasi Standar (Email & Password)
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post')->middleware('throttle:5,1');

    Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.post')->middleware('throttle:5,1');

    // Rute Autentikasi Telegram (Dual Mode)
    Route::get('/auth/telegram/token', [TelegramAuthController::class, 'generateToken'])->name('telegram.token');
    Route::match(['GET', 'POST'], '/auth/telegram/request', [TelegramAuthController::class, 'requestToken'])->name('auth.telegram.request');
    Route::get('/auth/telegram/check/{token}', [TelegramAuthController::class, 'checkToken'])->name('telegram.check');
    Route::match(['GET', 'POST'], '/auth/telegram/callback', [TelegramAuthController::class, 'handleCallback'])->name('telegram.callback');
});

// Rute untuk Pengguna Terautentikasi (Auth Only)
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', function () {
        return view('dashboard.index', ['user' => auth()->user()]);
    })->name('dashboard');

    Route::get('/whatsapp', [WhatsAppSessionController::class, 'index'])->name('whatsapp.index');

    // Rute Interaksi Sesi WhatsApp & Baileys Microservice (Dilindungi Auth)
    Route::get('/wa/status', [WhatsAppSessionController::class, 'status'])->name('wa.status');
    Route::get('/wa/qr', [WhatsAppSessionController::class, 'qr'])->name('wa.qr');
    Route::post('/wa/start', [WhatsAppSessionController::class, 'start'])->name('wa.start');
    Route::post('/wa/pairing-code', [WhatsAppSessionController::class, 'requestPairingCode'])->name('wa.pairing_code');
    Route::post('/wa/logout', [WhatsAppSessionController::class, 'logout'])->name('wa.logout');

    // Rute Modul Kontak & Import/Export (Milestone 4)
    Route::get('/contacts/groups', [ContactGroupController::class, 'index'])->name('contacts.groups.index');
    Route::post('/contacts/groups', [ContactGroupController::class, 'store'])->name('contacts.groups.store');
    Route::delete('/contacts/groups/{id}', [ContactGroupController::class, 'destroy'])->name('contacts.groups.destroy')->whereNumber('id');

    Route::post('/contacts/batch-delete', [ContactController::class, 'batchDestroy'])->name('contacts.batch_delete');
    Route::post('/contacts/import', [ContactImportExportController::class, 'import'])->name('contacts.import');
    Route::get('/contacts/export', [ContactImportExportController::class, 'export'])->name('contacts.export');
    Route::get('/contacts/template', [ContactImportExportController::class, 'template'])->name('contacts.template');

    Route::get('/contacts', [ContactController::class, 'index'])->name('contacts.index');
    Route::post('/contacts', [ContactController::class, 'store'])->name('contacts.store');
    Route::put('/contacts/{id}', [ContactController::class, 'update'])->name('contacts.update')->whereNumber('id');
    Route::delete('/contacts/{id}', [ContactController::class, 'destroy'])->name('contacts.destroy')->whereNumber('id');

    // Rute Modul Kampanye Siaran & Pacing Queue (Milestone 5)
    Route::post('/campaigns/preview-spintax', [CampaignController::class, 'previewSpintax'])->name('campaigns.preview_spintax');
    Route::get('/campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
    Route::get('/campaigns/create', [CampaignController::class, 'create'])->name('campaigns.create');
    Route::post('/campaigns', [CampaignController::class, 'store'])->name('campaigns.store');
    Route::get('/campaigns/{campaign}', [CampaignController::class, 'show'])->name('campaigns.show')->whereNumber('campaign');
    Route::get('/campaigns/{campaign}/status', [CampaignController::class, 'status'])->name('campaigns.status')->whereNumber('campaign');
    Route::post('/campaigns/{campaign}/start', [CampaignController::class, 'start'])->name('campaigns.start')->whereNumber('campaign');
    Route::post('/campaigns/{campaign}/pause', [CampaignController::class, 'pause'])->name('campaigns.pause')->whereNumber('campaign');
    Route::post('/campaigns/{campaign}/resume', [CampaignController::class, 'resume'])->name('campaigns.resume')->whereNumber('campaign');
    Route::post('/campaigns/{campaign}/cancel', [CampaignController::class, 'cancel'])->name('campaigns.cancel')->whereNumber('campaign');
});
