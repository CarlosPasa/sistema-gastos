<?php

use Illuminate\Support\Facades\DB;

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

it('muestra los 6 movimientos más recientes del mes mezclando gastos e ingresos', function () {
    createIncome($this->user, 100, '2026-11-01 08:00:00', 'Ingreso-01');
    createIncome($this->user, 100, '2026-11-20 08:00:00', 'Ingreso-20');
    foreach ([3, 5, 9, 12, 15, 18, 25] as $day) {
        createExpense($this->user, $this->food, 10, sprintf('2026-11-%02d', $day), description: sprintf('Gasto-%02d', $day));
    }
    createExpense($this->user, $this->food, 10, '2026-12-01', description: 'Gasto-diciembre');

    $this->get('/mt2-preview?mes=2026-11')
        ->assertViewHas('movements', function ($movements) {
            return $movements->pluck('description')->all() === [
                'Gasto-25', 'Ingreso-20', 'Gasto-18', 'Gasto-15', 'Gasto-12', 'Gasto-09',
            ] && $movements[1]['type'] === 'Ingreso' && $movements[0]['type'] === 'Gasto';
        })
        ->assertSeeInOrder(['Gasto-25', 'Ingreso-20', 'Gasto-18']);
});

it('marca los ahorros como tales en los movimientos', function () {
    createExpense($this->user, $this->food, 500, '2026-11-10', isSaving: true, description: 'Fondo');

    $this->get('/mt2-preview?mes=2026-11')
        ->assertViewHas('movements', fn ($m) => $m[0]['type'] === 'Ahorro' && $m[0]['amount'] === 500.0);
});

it('incluye el resumen de presupuestos del mes', function () {
    $this->post('/mt2/presupuestos', [
        'description' => 'Tope comida',
        'amount' => 400,
        'id_category' => $this->food,
        'effective_month' => '2026-11',
    ]);
    createExpense($this->user, $this->food, 100, '2026-11-08');

    $this->get('/mt2-preview?mes=2026-11')
        ->assertViewHas('budgetSummary', fn (array $s) => count($s) === 1
            && $s[0]['spent'] === 100.0 && $s[0]['percent'] === 25.0)
        ->assertSee('Tope comida');
});

it('usa el mes preferido de la configuración cuando no se indica ?mes=', function () {
    $this->travelTo('2026-11-19 10:00:00');
    DB::table('user_settings')->insert([
        'user_id' => $this->user->id,
        'default_month' => 3,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->get('/mt2-preview')->assertViewHas('month', '2026-03');
});

it('calcula ingresos y ahorros de cada mes de la serie', function () {
    createIncome($this->user, 1000, '2026-09-10 10:00:00');
    createExpense($this->user, $this->food, 200, '2026-09-11', isSaving: true);
    createIncome($this->user, 50, '2026-05-31 23:00:00'); // fuera de la serie

    $this->get('/mt2-preview?mes=2026-11')
        ->assertViewHas('months', function (array $months) {
            $september = $months[3];

            return array_column($months, 'label') === ['jun. 26', 'jul. 26', 'ago. 26', 'sep. 26', 'oct. 26', 'nov. 26']
                && $september['income'] === 1000.0
                && $september['saving'] === 200.0
                && $september['spent'] === 0.0
                && array_sum(array_column($months, 'income')) === 1000.0;
        });
});

it('cruza correctamente el cambio de año en la serie de 6 meses', function () {
    createExpense($this->user, $this->food, 70, '2025-11-15');
    createExpense($this->user, $this->food, 30, '2026-02-03');

    $this->get('/mt2-preview?mes=2026-02')
        ->assertViewHas('months', fn (array $m) => $m[2]['spent'] === 70.0 && $m[2]['label'] !== $m[5]['label'] && $m[5]['spent'] === 30.0);
});

it('carga el dashboard con un número fijo de consultas, sin importar los datos', function () {
    foreach (range(1, 30) as $i) {
        createExpense($this->user, $i % 2 ? $this->food : $this->transport, $i, sprintf('2026-%02d-10', ($i % 6) + 6));
        createIncome($this->user, $i * 10, sprintf('2026-%02d-05 09:00:00', ($i % 6) + 6));
    }

    DB::enableQueryLog();
    $this->get('/mt2-preview?mes=2026-11')->assertOk();
    $queries = count(DB::getQueryLog());

    expect($queries)->toBeLessThanOrEqual(10);
});
