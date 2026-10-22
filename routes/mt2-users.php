<?php

use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active.user', 'admin'])
    ->prefix('mt2')
    ->name('mt2.')
    ->group(function () {

        Route::get('/usuarios', [UserController::class, 'index'])
            ->name('users.index');

        Route::get('/usuarios/crear', [UserController::class, 'create'])
            ->name('users.create');

        Route::post('/usuarios', [UserController::class, 'store'])
            ->name('users.store');

        Route::get('/usuarios/{id}/editar', [UserController::class, 'edit'])
            ->whereNumber('id')
            ->name('users.edit');

        Route::put('/usuarios/{id}', [UserController::class, 'update'])
            ->whereNumber('id')
            ->name('users.update');

        Route::patch('/usuarios/{id}/estado', [UserController::class, 'changeStatus'])
            ->whereNumber('id')
            ->name('users.status');
    });
