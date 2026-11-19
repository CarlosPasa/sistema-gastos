<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Configuración base
|--------------------------------------------------------------------------
| Todas las pruebas de Feature usan Tests\TestCase, que limpia la base de
| datos antes de cada prueba y se niega a correr fuera de una base *_testing.
*/

pest()->extend(TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
| El sistema usa Query Builder (sin modelos Eloquent para finanzas), así que
| los datos de prueba se crean directamente en las tablas.
*/

function activeUser(array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'role' => 'user',
        'is_active' => true,
    ], $attributes));
}

function adminUser(): User
{
    return activeUser(['role' => 'admin']);
}

function createCategory(User $user, string $description = 'Comida', bool $active = true): int
{
    return DB::table('categoria')->insertGetId([
        'description' => $description,
        'estado' => $active,
        'user_id' => $user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function createExpense(User $user, int $categoryId, float $amount, string $date, bool $isSaving = false, string $description = 'Gasto'): int
{
    return DB::table('gasto')->insertGetId([
        'description' => $description,
        'amount' => $amount,
        'date' => $date,
        'save' => $isSaving,
        'id_category' => $categoryId,
        'estado' => true,
        'user_id' => $user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function createIncome(User $user, float $amount, string $createdAt, string $description = 'Sueldo'): int
{
    return DB::table('ingreso')->insertGetId([
        'description' => $description,
        'amount' => $amount,
        'date' => substr($createdAt, 0, 10),
        'estado' => true,
        'user_id' => $user->id,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);
}
