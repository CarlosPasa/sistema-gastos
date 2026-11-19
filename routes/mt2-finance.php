<?php

use App\Enums\FinanceModule;
use App\Http\Controllers\FinanceController;
use Illuminate\Support\Facades\Route;

// {modulo} se resuelve al enum FinanceModule (categorias, gastos, ingresos,
// presupuestos). Cualquier otro valor responde 404.
Route::middleware(['auth', 'active.user'])
    ->prefix('mt2')
    ->name('mt2.finance.')
    ->controller(FinanceController::class)
    ->group(function () {
        Route::get('/reportes', 'report')->name('report');

        $modules = FinanceModule::values();

        Route::get('/{modulo}', 'index')->whereIn('modulo', $modules)->name('index');
        Route::get('/{modulo}/crear', 'create')->whereIn('modulo', $modules)->name('create');
        Route::post('/{modulo}', 'store')->whereIn('modulo', $modules)->name('store');
        Route::get('/{modulo}/{id}/editar', 'edit')->whereIn('modulo', $modules)->whereNumber('id')->name('edit');
        Route::put('/{modulo}/{id}', 'update')->whereIn('modulo', $modules)->whereNumber('id')->name('update');
        Route::delete('/{modulo}/{id}', 'destroy')->whereIn('modulo', $modules)->whereNumber('id')->name('destroy');
        Route::patch('/{modulo}/{id}/activar', 'activate')->whereIn('modulo', $modules)->whereNumber('id')->name('activate');
    });
