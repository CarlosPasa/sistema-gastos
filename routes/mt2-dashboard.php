<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/mt2-preview', [DashboardController::class, 'index'])
    ->middleware(['auth', 'active.user'])
    ->name('mt2.dashboard');
