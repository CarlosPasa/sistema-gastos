<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])
        ->name('login');

    Route::post('/login', [LoginController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('login.submit');

    Route::get('/register', [RegisterController::class, 'show'])
        ->name('register');

    Route::post('/register', [RegisterController::class, 'register'])
        ->middleware('throttle:5,1')
        ->name('register.submit');
});

Route::post('/logout', [LoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        return redirect('/mt2-preview');
    })->name('dashboard');
});

Route::middleware(['auth', 'active.user'])
    ->prefix('mt2')
    ->name('mt2.profile.')
    ->group(function () {
        Route::get('/mi-cuenta', [ProfileController::class, 'edit'])
            ->name('edit');

        Route::put('/mi-cuenta', [ProfileController::class, 'update'])
            ->name('update');

        Route::put('/mi-cuenta/contrasena', [ProfileController::class, 'changePassword'])
            ->middleware('throttle:5,1')
            ->name('password');
    });

Route::middleware(['auth', 'active.user'])
    ->prefix('mt2')
    ->name('mt2.settings.')
    ->group(function () {

        Route::get('/configuracion', [SettingsController::class, 'index'])
            ->name('index');

        Route::put('/configuracion', [SettingsController::class, 'update'])
            ->name('update');

        Route::patch('/configuracion/tema', [SettingsController::class, 'updateTheme']
        )->name('theme');
    });

require __DIR__.'/mt2-dashboard.php';
require __DIR__.'/mt2-finance.php';
require __DIR__.'/mt2-users.php';
