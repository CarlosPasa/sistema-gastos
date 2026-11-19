<?php

use Illuminate\Support\Facades\DB;

/*
| Lo más crítico de una app financiera multiusuario: nadie puede ver, editar,
| desactivar ni referenciar datos de otra persona, aunque adivine el ID.
*/

beforeEach(function () {
    $this->owner = activeUser();
    $this->intruder = activeUser();

    $this->category = createCategory($this->owner, 'Comida del dueño');
    $this->expense = createExpense($this->owner, $this->category, 150.00, '2026-11-10', description: 'Mercado del dueño');
});

it('no muestra los gastos de otro usuario en el listado', function () {
    $this->actingAs($this->intruder)
        ->get('/mt2/gastos')
        ->assertOk()
        ->assertDontSee('Mercado del dueño');
});

it('sí muestra los gastos propios en el listado', function () {
    $this->actingAs($this->owner)
        ->get('/mt2/gastos')
        ->assertOk()
        ->assertSee('Mercado del dueño');
});

it('responde 404 al abrir la edición de un gasto ajeno', function () {
    $this->actingAs($this->intruder)
        ->get("/mt2/gastos/{$this->expense}/editar")
        ->assertNotFound();
});

it('no permite modificar un gasto ajeno', function () {
    $intruderCategory = createCategory($this->intruder);

    $this->actingAs($this->intruder)
        ->put("/mt2/gastos/{$this->expense}", [
            'description' => 'Hackeado',
            'amount' => 1,
            'date' => '2026-11-10',
            'save' => 0,
            'id_category' => $intruderCategory,
        ])
        ->assertNotFound();

    expect(DB::table('gasto')->find($this->expense)->description)->toBe('Mercado del dueño');
});

it('no permite desactivar un gasto ajeno', function () {
    $this->actingAs($this->intruder)
        ->delete("/mt2/gastos/{$this->expense}")
        ->assertNotFound();

    expect(DB::table('gasto')->find($this->expense)->estado)->toBeTrue();
});

it('no permite registrar un gasto usando la categoría de otro usuario', function () {
    $this->actingAs($this->intruder)
        ->post('/mt2/gastos', [
            'description' => 'Gasto con categoría ajena',
            'amount' => 50,
            'date' => '2026-11-12',
            'save' => 0,
            'id_category' => $this->category,
        ])
        ->assertSessionHasErrors('id_category');

    expect(DB::table('gasto')->where('user_id', $this->intruder->id)->count())->toBe(0);
});

it('no incluye montos ajenos en el reporte mensual', function () {
    createIncome($this->intruder, 1000, '2026-11-05 10:00:00');

    $this->actingAs($this->intruder)
        ->get('/mt2/reportes?mes=2026-11')
        ->assertOk()
        ->assertViewHas('totals', fn (array $totals) => (float) $totals['spent'] === 0.0
            && (float) $totals['income'] === 1000.0);
});
