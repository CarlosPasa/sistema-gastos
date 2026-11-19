<?php

use Illuminate\Support\Facades\DB;

/*
| Pruebas de caracterización: fijan lo que hoy muestran las pantallas de
| finanzas (listado, alta y edición) para que el refactor a Eloquent no
| cambie nada que el usuario vea.
*/

beforeEach(function () {
    $this->user = activeUser();
    $this->actingAs($this->user);
    $this->category = createCategory($this->user, 'Servicios');
});

it('muestra el listado y el formulario de alta de cada módulo', function (string $module, string $title) {
    $this->get("/mt2/{$module}")->assertOk()->assertSee($title);
    $this->get("/mt2/{$module}/crear")->assertOk()->assertSee('Nuevo registro');
})->with([
    ['categorias', 'Categorías'],
    ['gastos', 'Gastos y ahorros'],
    ['ingresos', 'Ingresos'],
    ['presupuestos', 'Presupuestos'],
]);

it('responde 404 para un módulo que no existe', function () {
    $this->get('/mt2/inversiones')->assertNotFound();
    $this->get('/mt2/inversiones/crear')->assertNotFound();
});

it('carga el formulario de edición de un gasto con sus valores', function () {
    $id = createExpense($this->user, $this->category, 25.5, '2026-11-15', isSaving: true, description: 'Internet hogar');

    $this->get("/mt2/gastos/{$id}/editar")
        ->assertOk()
        ->assertSee('value="Internet hogar"', false)
        ->assertSee('value="25.50"', false)
        ->assertSee('value="2026-11-15"', false)
        ->assertSee('Servicios');
});

it('carga el formulario de edición de una categoría, un ingreso y un presupuesto', function () {
    $income = createIncome($this->user, 3000, '2026-11-01 09:00:00', 'Sueldo noviembre');

    $this->post('/mt2/presupuestos', [
        'description' => 'Tope servicios',
        'amount' => 400,
        'id_category' => $this->category,
        'effective_month' => '2026-11',
    ]);
    $budget = DB::table('presupuesto')->value('id');

    $this->get("/mt2/categorias/{$this->category}/editar")->assertOk()->assertSee('value="Servicios"', false);
    $this->get("/mt2/ingresos/{$income}/editar")->assertOk()->assertSee('value="3000.00"', false);
    $this->get("/mt2/presupuestos/{$budget}/editar")->assertOk()->assertSee('value="400.00"', false);
});

it('muestra en el listado de gastos la categoría, el tipo, el estado y la fecha', function () {
    createExpense($this->user, $this->category, 99.9, '2026-11-03', isSaving: true, description: 'Fondo de emergencia');

    $this->get('/mt2/gastos')
        ->assertOk()
        ->assertSeeInOrder(['Fondo de emergencia', 'S/ 99.90', 'Servicios', 'Ahorro', 'Activo', '2026-11-03']);
});

it('muestra en el listado de ingresos la fecha de registro', function () {
    createIncome($this->user, 1500, '2026-11-01 09:00:00', 'Freelance');

    $this->get('/mt2/ingresos')
        ->assertOk()
        ->assertSeeInOrder(['Freelance', 'S/ 1,500.00', '2026-11-01']);
});

it('pagina los listados de 12 en 12, del más reciente al más antiguo', function () {
    foreach (range(1, 13) as $day) {
        createExpense($this->user, $this->category, $day, sprintf('2026-11-%02d', $day), description: sprintf('Gasto-dia-%02d', $day));
    }

    $this->get('/mt2/gastos')
        ->assertSee('Gasto-dia-13')
        ->assertSee('Gasto-dia-02')
        ->assertDontSee('Gasto-dia-01');

    $this->get('/mt2/gastos?page=2')->assertSee('Gasto-dia-01');
});

it('filtra los gastos por tipo y por rango de fechas', function () {
    createExpense($this->user, $this->category, 10, '2026-11-05', description: 'Consumo-uno');
    createExpense($this->user, $this->category, 20, '2026-11-06', isSaving: true, description: 'Ahorro-uno');
    createExpense($this->user, $this->category, 30, '2026-11-20', description: 'Consumo-dos');

    $this->get('/mt2/gastos?tipo=ahorro')
        ->assertSee('Ahorro-uno')->assertDontSee('Consumo-uno');

    $this->get('/mt2/gastos?desde=2026-11-01&hasta=2026-11-10')
        ->assertSee('Consumo-uno')->assertSee('Ahorro-uno')->assertDontSee('Consumo-dos');

    $this->get('/mt2/gastos?desde=2026-11-10&hasta=2026-11-01')
        ->assertSessionHasErrors('hasta');
});

it('exige el mes de vigencia al crear un presupuesto', function () {
    $this->post('/mt2/presupuestos', [
        'description' => 'Sin mes',
        'amount' => 100,
        'id_category' => $this->category,
    ])->assertSessionHasErrors('effective_month');

    expect(DB::table('presupuesto')->count())->toBe(0);
});

it('actualiza una categoría y un ingreso', function () {
    $income = createIncome($this->user, 100, '2026-11-01 09:00:00');

    $this->put("/mt2/categorias/{$this->category}", ['description' => 'Servicios básicos'])
        ->assertRedirect(route('mt2.finance.index', 'categorias'));
    $this->put("/mt2/ingresos/{$income}", ['description' => 'Sueldo', 'amount' => '4200.00'])
        ->assertRedirect(route('mt2.finance.index', 'ingresos'));

    expect(DB::table('categoria')->find($this->category)->description)->toBe('Servicios básicos')
        ->and((float) DB::table('ingreso')->find($income)->amount)->toBe(4200.0);
});

it('no permite enviar campos que no pertenecen al módulo', function () {
    $intruder = activeUser();

    $this->post('/mt2/categorias', [
        'description' => 'Ropa',
        'user_id' => $intruder->id,
        'estado' => false,
    ])->assertRedirect(route('mt2.finance.index', 'categorias'));

    $row = DB::table('categoria')->where('description', 'Ropa')->first();

    expect($row->user_id)->toBe($this->user->id)
        ->and($row->estado)->toBeTrue();
});

it('genera el reporte mensual con totales y consumo por categoría', function () {
    $food = createCategory($this->user, 'Comida');
    createIncome($this->user, 2000, '2026-11-02 08:00:00');
    createExpense($this->user, $food, 300, '2026-11-10');
    createExpense($this->user, $this->category, 120, '2026-11-11');
    createExpense($this->user, $food, 500, '2026-11-12', isSaving: true);
    createExpense($this->user, $food, 999, '2026-12-01');

    $this->get('/mt2/reportes?mes=2026-11')
        ->assertOk()
        ->assertViewHas('totals', fn (array $t) => (float) $t['income'] === 2000.0
            && (float) $t['spent'] === 420.0
            && (float) $t['saving'] === 500.0)
        ->assertViewHas('breakdown', fn ($rows) => $rows->pluck('description')->all() === ['Comida', 'Servicios']);
});
