<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\WhatsappAccountController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : view('welcome'));

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::middleware(['auth', 'workspace'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/whatsapp', [WhatsappAccountController::class, 'index'])->name('whatsapp.index');
    Route::post('/whatsapp/connect', [WhatsappAccountController::class, 'connect'])->name('whatsapp.connect');
    Route::post('/whatsapp/manual', [WhatsappAccountController::class, 'storeManual'])->name('whatsapp.manual');
    Route::post('/whatsapp/{account}/test', [WhatsappAccountController::class, 'sendTest'])->name('whatsapp.test');
    Route::post('/whatsapp/{account}/refresh', [WhatsappAccountController::class, 'refresh'])->name('whatsapp.refresh');
    Route::delete('/whatsapp/{account}', [WhatsappAccountController::class, 'destroy'])->name('whatsapp.destroy');
});
