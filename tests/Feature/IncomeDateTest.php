<?php

use Illuminate\Support\Facades\DB;

/*
| Los ingresos tienen fecha propia: se puede registrar hoy un ingreso de otro
| mes y aparece en el mes correcto del dashboard y del reporte.
*/

beforeEach(function () {
    $this->user = activeUser();
    $this->actingAs($this->user);
    $this->travelTo('2026-11-19 10:00:00');
});

it('registra un ingreso con una fecha pasada y lo cuenta en ese mes', function () {
    $this->post('/mt2/ingresos', [
        'description' => 'Sueldo de octubre',
        'amount' => 3000,
        'date' => '2026-10-31',
    ])->assertRedirect(route('mt2.finance.index', 'ingresos'));

    $this->get('/mt2-preview?mes=2026-10')->assertViewHas('income', 3000.0);
    $this->get('/mt2-preview?mes=2026-11')->assertViewHas('income', 0.0);

    $this->get('/mt2/reportes?mes=2026-10')
        ->assertViewHas('totals', fn (array $t) => (float) $t['income'] === 3000.0);
});

it('exige una fecha válida para el ingreso', function (?string $date) {
    $this->post('/mt2/ingresos', [
        'description' => 'Sueldo',
        'amount' => 3000,
        'date' => $date,
    ])->assertSessionHasErrors('date');

    expect(DB::table('ingreso')->count())->toBe(0);
})->with([
    'vacía' => [null],
    'formato día/mes/año' => ['31/10/2026'],
    'fecha inexistente' => ['2026-02-30'],
]);

it('permite corregir la fecha de un ingreso', function () {
    $id = createIncome($this->user, 500, '2026-11-05 09:00:00');

    $this->put("/mt2/ingresos/{$id}", [
        'description' => 'Bono',
        'amount' => 500,
        'date' => '2026-09-15',
    ])->assertSessionHasNoErrors();

    expect(DB::table('ingreso')->find($id)->date)->toBe('2026-09-15');
});

it('ordena el listado de ingresos por fecha del movimiento', function () {
    createIncome($this->user, 100, '2026-11-01 09:00:00', 'Registrado-primero');
    $late = createIncome($this->user, 100, '2026-11-02 09:00:00', 'Registrado-despues');
    DB::table('ingreso')->where('id', $late)->update(['date' => '2026-08-01']);

    $this->get('/mt2/ingresos')->assertSeeInOrder(['Registrado-primero', 'Registrado-despues']);
});

it('muestra la fecha en el formulario de edición del ingreso', function () {
    $id = createIncome($this->user, 100, '2026-11-07 09:00:00');

    $this->get("/mt2/ingresos/{$id}/editar")->assertSee('value="2026-11-07"', false);
});

describe('migración', function () {
    it('rellena la fecha desde created_at sin perder ningún ingreso', function () {
        $migration = require database_path('migrations/2026_11_19_000100_add_date_to_ingreso_table.php');

        // Volver al esquema anterior y cargar ingresos "antiguos".
        $migration->down();
        foreach (['2026-10-31 23:59:59', '2026-11-01 00:00:00', '2026-11-18 14:30:00'] as $i => $createdAt) {
            DB::table('ingreso')->insert([
                'description' => "Antiguo {$i}",
                'amount' => 100 + $i,
                'estado' => true,
                'user_id' => $this->user->id,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        $migration->up();

        expect(DB::table('ingreso')->orderBy('id')->pluck('date')->all())
            ->toBe(['2026-10-31', '2026-11-01', '2026-11-18'])
            ->and(DB::table('ingreso')->sum('amount'))->toEqual(303);

        // La columna queda obligatoria.
        expect(fn () => DB::table('ingreso')->insert([
            'description' => 'Sin fecha', 'amount' => 1, 'estado' => true,
            'user_id' => $this->user->id, 'created_at' => now(), 'updated_at' => now(),
        ]))->toThrow(\Illuminate\Database\QueryException::class);
    });
});
