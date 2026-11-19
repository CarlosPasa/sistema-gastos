<?php

use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->user = activeUser();
    $this->actingAs($this->user);
    $this->category = createCategory($this->user, 'Transporte');
});

it('registra un gasto válido', function () {
    $this->post('/mt2/gastos', [
        'description' => 'Taxi',
        'amount' => '25.50',
        'date' => '2026-11-15',
        'save' => 0,
        'id_category' => $this->category,
    ])->assertRedirect(route('mt2.finance.index', 'gastos'));

    $row = DB::table('gasto')->where('user_id', $this->user->id)->first();

    expect($row->description)->toBe('Taxi')
        ->and((float) $row->amount)->toBe(25.5)
        ->and($row->save)->toBeFalse()
        ->and($row->estado)->toBeTrue();
});

it('rechaza montos inválidos', function (mixed $amount) {
    $this->post('/mt2/gastos', [
        'description' => 'Taxi',
        'amount' => $amount,
        'date' => '2026-11-15',
        'save' => 0,
        'id_category' => $this->category,
    ])->assertSessionHasErrors('amount');

    expect(DB::table('gasto')->count())->toBe(0);
})->with([
    'cero' => [0],
    'negativo' => [-10],
    'más de dos decimales' => ['10.555'],
    'texto' => ['diez'],
]);

it('no permite usar una categoría desactivada', function () {
    $inactive = createCategory($this->user, 'Antigua', active: false);

    $this->post('/mt2/gastos', [
        'description' => 'Taxi',
        'amount' => 10,
        'date' => '2026-11-15',
        'save' => 0,
        'id_category' => $inactive,
    ])->assertSessionHasErrors('id_category');
});

it('desactiva un gasto en lugar de borrarlo y luego lo reactiva', function () {
    $id = createExpense($this->user, $this->category, 30, '2026-11-01');

    $this->delete("/mt2/gastos/{$id}");
    expect(DB::table('gasto')->find($id)->estado)->toBeFalse();

    $this->patch("/mt2/gastos/{$id}/activar");
    expect(DB::table('gasto')->find($id)->estado)->toBeTrue();
});

it('no permite desactivar una categoría que tiene gastos', function () {
    createExpense($this->user, $this->category, 30, '2026-11-01');

    $this->delete("/mt2/categorias/{$this->category}")
        ->assertSessionHasErrors('delete');

    expect(DB::table('categoria')->find($this->category)->estado)->toBeTrue();
});

it('filtra los gastos por mes', function () {
    createExpense($this->user, $this->category, 10, '2026-10-31', description: 'Gasto-de-octubre-xyz');
    createExpense($this->user, $this->category, 20, '2026-11-01', description: 'Gasto-de-noviembre-xyz');

    $this->get('/mt2/gastos?mes=2026-11')
        ->assertOk()
        ->assertSee('Gasto-de-noviembre-xyz')
        ->assertDontSee('Gasto-de-octubre-xyz');
});

it('trata los comodines de la búsqueda como texto literal', function () {
    createExpense($this->user, $this->category, 10, '2026-11-01', description: 'Descuento 50%');
    createExpense($this->user, $this->category, 20, '2026-11-02', description: 'Pasaje 500');

    $this->get('/mt2/gastos?q='.urlencode('50%'))
        ->assertOk()
        ->assertSee('Descuento 50%')
        ->assertDontSee('Pasaje 500');
});
