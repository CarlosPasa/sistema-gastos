<?php

beforeEach(function () {
    $this->user = activeUser();
    $this->actingAs($this->user);
    $this->food = createCategory($this->user, 'Comida');
    $this->transport = createCategory($this->user, 'Transporte');
});

it('calcula ingresos, gastos, ahorro y disponible del mes', function () {
    createIncome($this->user, 3000, '2026-11-01 09:00:00');
    createIncome($this->user, 500, '2026-11-20 18:00:00');
    createExpense($this->user, $this->food, 800, '2026-11-05');
    createExpense($this->user, $this->transport, 200, '2026-11-06');
    createExpense($this->user, $this->food, 1000, '2026-11-25', isSaving: true);

    $this->get('/mt2-preview?mes=2026-11')
        ->assertOk()
        ->assertViewHas('income', 3500.0)
        ->assertViewHas('spent', 1000.0)
        ->assertViewHas('saving', 1000.0)
        ->assertViewHas('available', 1500.0);
});

it('incluye los movimientos del último día del mes y excluye los del siguiente', function () {
    createIncome($this->user, 100, '2026-11-30 23:59:59');
    createIncome($this->user, 999, '2026-12-01 00:00:00');
    createExpense($this->user, $this->food, 40, '2026-11-30');
    createExpense($this->user, $this->food, 888, '2026-12-01');

    $this->get('/mt2-preview?mes=2026-11')
        ->assertViewHas('income', 100.0)
        ->assertViewHas('spent', 40.0);
});

it('ignora los registros desactivados', function () {
    createIncome($this->user, 1000, '2026-11-10 10:00:00');
    $expense = createExpense($this->user, $this->food, 300, '2026-11-10');

    $this->delete("/mt2/gastos/{$expense}");

    $this->get('/mt2-preview?mes=2026-11')
        ->assertViewHas('spent', 0.0)
        ->assertViewHas('available', 1000.0);
});

it('agrupa el consumo por categoría, de mayor a menor', function () {
    createExpense($this->user, $this->food, 100, '2026-11-02');
    createExpense($this->user, $this->transport, 300, '2026-11-03');
    createExpense($this->user, $this->food, 50, '2026-11-04');

    $this->get('/mt2-preview?mes=2026-11')
        ->assertViewHas('categoryRows', function ($rows) {
            return $rows->pluck('description')->all() === ['Transporte', 'Comida']
                && (float) $rows[1]->total === 150.0;
        });
});

it('muestra la evolución de los últimos 6 meses', function () {
    createExpense($this->user, $this->food, 70, '2026-06-15');
    createExpense($this->user, $this->food, 90, '2026-11-15');

    $this->get('/mt2-preview?mes=2026-11')
        ->assertViewHas('months', function (array $months) {
            return count($months) === 6
                && $months[0]['spent'] === 70.0
                && $months[5]['spent'] === 90.0;
        });
});

it('usa el mes actual si el parámetro ?mes= no es válido', function () {
    $this->travelTo('2026-11-19 10:00:00');

    $this->get('/mt2-preview?mes=2026-13')
        ->assertOk()
        ->assertViewHas('month', '2026-11');
});
