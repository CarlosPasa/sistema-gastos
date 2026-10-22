<?php
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

// Ruta temporal para la maqueta. Proteger con auth antes de usar datos reales.
Route::get('/mt2-preview', [DashboardController::class, 'index'])
    ->middleware('auth');
