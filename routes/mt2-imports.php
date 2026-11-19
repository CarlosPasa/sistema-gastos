<?php

use App\Http\Controllers\StatementImportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active.user'])
    ->prefix('mt2')
    ->name('mt2.imports.')
    ->controller(StatementImportController::class)
    ->group(function () {
        Route::get('/importar', 'index')->name('index');
        Route::post('/importar', 'store')->middleware('throttle:10,1')->name('store');
    });
