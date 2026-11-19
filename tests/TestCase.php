<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase {
        refreshDatabase as protected refreshDatabaseWithoutGuard;
    }

    /**
     * RefreshDatabase borra y recrea todas las tablas antes de cada prueba.
     * Antes de ese borrado verificamos que la conexión apunte a una base
     * *_testing. Si no (por ejemplo, por un `config:cache` olvidado o una
     * variable de entorno de Docker), se detiene todo sin tocar datos reales.
     */
    public function refreshDatabase(): void
    {
        $database = (string) config('database.connections.'.config('database.default').'.database');

        if (! str_ends_with($database, '_testing')) {
            throw new RuntimeException(
                "Las pruebas solo pueden correr en una base *_testing. Base actual: {$database}. "
                .'Ejecuta `php artisan config:clear` e inténtalo de nuevo.'
            );
        }

        $this->refreshDatabaseWithoutGuard();
    }
}
