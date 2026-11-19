<?php

use App\Services\BudgetRecurrenceService;
use Illuminate\Support\Facades\DB;

/*
| Regla de negocio: cambiar el límite de un presupuesto afecta el mes elegido
| y los siguientes, pero nunca reescribe el historial de meses anteriores.
*/

beforeEach(function () {
    $this->user = activeUser();
    $this->actingAs($this->user);
    $this->category = createCategory($this->user, 'Comida');
    $this->service = app(BudgetRecurrenceService::class);
});

function createBudget(float $amount, string $month): int
{
    test()->post('/mt2/presupuestos', [
        'description' => 'Comida mensual',
        'amount' => $amount,
        'id_category' => test()->category,
        'effective_month' => $month,
    ])->assertRedirect(route('mt2.finance.index', 'presupuestos'));

    return (int) DB::table('presupuesto')->where('user_id', test()->user->id)->value('id');
}

function limitFor(string $month): ?float
{
    $summary = test()->service->summary(test()->user->id, $month);

    return isset($summary[0]) ? $summary[0]['amount'] : null;
}

it('crea la primera vigencia al registrar un presupuesto', function () {
    $id = createBudget(500, '2026-09');

    $versions = DB::table('presupuesto_vigencia')->where('presupuesto_id', $id)->get();

    expect($versions)->toHaveCount(1)
        ->and($versions[0]->effective_month)->toBe('2026-09-01')
        ->and((float) $versions[0]->amount)->toBe(500.0);
});

it('no aplica el presupuesto a meses anteriores a su inicio', function () {
    createBudget(500, '2026-09');

    expect(limitFor('2026-08'))->toBeNull()
        ->and(limitFor('2026-09'))->toBe(500.0);
});

it('un cambio aplica desde el mes elegido sin alterar los meses anteriores', function () {
    $id = createBudget(500, '2026-09');

    $this->put("/mt2/presupuestos/{$id}", [
        'description' => 'Comida mensual',
        'amount' => 650,
        'id_category' => $this->category,
        'effective_month' => '2026-11',
    ])->assertRedirect(route('mt2.finance.index', 'presupuestos'));

    expect(limitFor('2026-09'))->toBe(500.0)
        ->and(limitFor('2026-10'))->toBe(500.0)
        ->and(limitFor('2026-11'))->toBe(650.0)
        ->and(limitFor('2027-03'))->toBe(650.0);
});

it('rechaza un cambio en un mes anterior a la última vigencia', function () {
    $id = createBudget(500, '2026-09');
    $this->service->change($id, $this->user->id, '650', '2026-11');

    $this->put("/mt2/presupuestos/{$id}", [
        'description' => 'Comida mensual',
        'amount' => 300,
        'id_category' => $this->category,
        'effective_month' => '2026-10',
    ])->assertSessionHasErrors('effective_month');

    expect(limitFor('2026-10'))->toBe(500.0)
        ->and(limitFor('2026-11'))->toBe(650.0);
});

it('no permite cambiar el presupuesto de otro usuario', function () {
    $id = createBudget(500, '2026-09');
    $intruder = activeUser();

    $this->actingAs($intruder)
        ->put("/mt2/presupuestos/{$id}", [
            'description' => 'Hackeado',
            'amount' => 1,
            'id_category' => createCategory($intruder),
            'effective_month' => '2026-12',
        ])->assertNotFound();

    expect(DB::table('presupuesto_vigencia')->where('presupuesto_id', $id)->count())->toBe(1);
});

it('calcula el consumo del mes solo con gastos, sin contar ahorros ni otros meses', function () {
    createBudget(400, '2026-11');

    createExpense($this->user, $this->category, 100, '2026-11-01');
    createExpense($this->user, $this->category, 50, '2026-11-30');
    createExpense($this->user, $this->category, 999, '2026-11-15', isSaving: true);
    createExpense($this->user, $this->category, 777, '2026-12-01');
    createExpense($this->user, $this->category, 888, '2026-10-31');

    $summary = $this->service->summary($this->user->id, '2026-11');

    expect($summary[0]['spent'])->toBe(150.0)
        ->and($summary[0]['percent'])->toBe(37.5);
});

it('no muestra presupuestos desactivados', function () {
    $id = createBudget(400, '2026-11');
    $this->delete("/mt2/presupuestos/{$id}");

    expect($this->service->summary($this->user->id, '2026-11'))->toBeEmpty();
});
