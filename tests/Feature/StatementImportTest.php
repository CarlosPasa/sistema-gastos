<?php

use App\Enums\ImportStatus;
use App\Jobs\ImportBankStatement;
use App\Models\StatementImport;
use App\Statements\StatementImporter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->user = activeUser();
    $this->actingAs($this->user);
    $this->category = createCategory($this->user, 'Importados');
});

function csvUpload(string $content, string $name = 'extracto.csv'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $content);
}

const SAMPLE_CSV = "fecha,descripcion,monto\n2026-11-03,Supermercado,-150.50\n2026-11-05,Sueldo,3000\n2026-11-06,Taxi,-12\n";

it('encola la importación y responde de inmediato', function () {
    Queue::fake();

    $this->post(route('mt2.imports.store'), [
        'file' => csvUpload(SAMPLE_CSV),
        'id_category' => $this->category,
    ])->assertRedirect(route('mt2.imports.index'))
        ->assertSessionHas('ok');

    $import = StatementImport::sole();

    expect($import->status)->toBe(ImportStatus::Pending)
        ->and($import->original_name)->toBe('extracto.csv')
        ->and(DB::table('gasto')->count())->toBe(0);

    Queue::assertPushed(ImportBankStatement::class, fn ($job) => $job->import->is($import));
});

it('crea gastos en la categoría elegida e ingresos con su fecha', function () {
    $this->post(route('mt2.imports.store'), [
        'file' => csvUpload(SAMPLE_CSV),
        'id_category' => $this->category,
    ]);

    $import = StatementImport::sole();
    $expenses = DB::table('gasto')->orderBy('date')->get();
    $income = DB::table('ingreso')->sole();

    expect($import->status)->toBe(ImportStatus::Completed)
        ->and($import->total_rows)->toBe(3)
        ->and($import->created_expenses)->toBe(2)
        ->and($import->created_incomes)->toBe(1)
        ->and($import->content)->toBeNull()
        ->and($expenses->pluck('description')->all())->toBe(['Supermercado', 'Taxi'])
        ->and($expenses->pluck('id_category')->unique()->all())->toBe([$this->category])
        ->and($expenses->pluck('save')->unique()->all())->toBe([false])
        ->and((float) $expenses[0]->amount)->toBe(150.5)
        ->and($income->date)->toBe('2026-11-05')
        ->and((float) $income->amount)->toBe(3000.0)
        ->and($income->user_id)->toBe($this->user->id);

    $this->get('/mt2-preview?mes=2026-11')
        ->assertViewHas('income', 3000.0)
        ->assertViewHas('spent', 162.5);
});

it('no duplica movimientos si se sube el mismo extracto dos veces', function () {
    foreach ([1, 2] as $attempt) {
        $this->post(route('mt2.imports.store'), [
            'file' => csvUpload(SAMPLE_CSV),
            'id_category' => $this->category,
        ]);
    }

    $second = StatementImport::latest('id')->first();

    expect(DB::table('gasto')->count())->toBe(2)
        ->and(DB::table('ingreso')->count())->toBe(1)
        ->and($second->created_expenses)->toBe(0)
        ->and($second->skipped_duplicates)->toBe(3);
});

it('conserva dos movimientos idénticos del mismo extracto', function () {
    $this->post(route('mt2.imports.store'), [
        'file' => csvUpload("fecha,descripcion,monto\n2026-11-03,Café,-8\n2026-11-03,Café,-8\n"),
        'id_category' => $this->category,
    ]);

    expect(DB::table('gasto')->count())->toBe(2);
});

it('el mismo extracto de otra persona no se considera duplicado', function () {
    $other = activeUser();
    $otherCategory = createCategory($other);

    $this->post(route('mt2.imports.store'), ['file' => csvUpload(SAMPLE_CSV), 'id_category' => $this->category]);
    $this->actingAs($other)->post(route('mt2.imports.store'), ['file' => csvUpload(SAMPLE_CSV), 'id_category' => $otherCategory]);

    expect(DB::table('gasto')->where('user_id', $other->id)->count())->toBe(2);
});

it('importa las filas válidas y lista las inválidas', function () {
    $this->post(route('mt2.imports.store'), [
        'file' => csvUpload("fecha,descripcion,monto\n2026-11-03,Válido,-10\n2026-13-01,Mal,-10\n"),
        'id_category' => $this->category,
    ]);

    $import = StatementImport::sole();

    expect($import->status)->toBe(ImportStatus::Completed)
        ->and($import->created_expenses)->toBe(1)
        ->and($import->row_errors)->toBe(['Fila 3: fecha inválida']);

    $this->get(route('mt2.imports.index'))->assertSee('Fila 3: fecha inválida');
});

it('marca la importación como fallida si el archivo no tiene el formato esperado', function () {
    $this->post(route('mt2.imports.store'), [
        'file' => csvUpload("nombre,valor\nX,1\n"),
        'id_category' => $this->category,
    ]);

    $import = StatementImport::sole();

    expect($import->status)->toBe(ImportStatus::Failed)
        ->and($import->failure_message)->toContain('Falta la columna "fecha"')
        ->and($import->content)->toBeNull();

    $this->get(route('mt2.imports.index'))->assertSee('Falló')->assertSee('Falta la columna');
});

it('convierte a UTF-8 los CSV que Excel guarda en Windows-1252', function () {
    $latin1 = mb_convert_encoding("fecha;descripcion;monto\n03/11/2026;Panadería Ñuñoa;-5,50\n", 'Windows-1252', 'UTF-8');

    $this->post(route('mt2.imports.store'), [
        'file' => csvUpload($latin1),
        'id_category' => $this->category,
    ]);

    expect(DB::table('gasto')->value('description'))->toBe('Panadería Ñuñoa');
});

it('valida el archivo y la categoría', function (array $payload, string $field) {
    $this->post(route('mt2.imports.store'), $payload)->assertSessionHasErrors($field);

    expect(StatementImport::count())->toBe(0);
})->with([
    'sin archivo' => [fn () => ['id_category' => test()->category], 'file'],
    'no es CSV' => [fn () => ['file' => UploadedFile::fake()->create('foto.png', 10, 'image/png'), 'id_category' => test()->category], 'file'],
    'demasiado grande' => [fn () => ['file' => UploadedFile::fake()->create('grande.csv', 2048, 'text/csv'), 'id_category' => test()->category], 'file'],
    'sin categoría' => [fn () => ['file' => csvUpload(SAMPLE_CSV)], 'id_category'],
]);

it('no permite usar la categoría de otro usuario', function () {
    $foreignCategory = createCategory(activeUser());

    $this->post(route('mt2.imports.store'), [
        'file' => csvUpload(SAMPLE_CSV),
        'id_category' => $foreignCategory,
    ])->assertSessionHasErrors('id_category');
});

it('muestra solo las importaciones propias', function () {
    $other = activeUser();
    $this->actingAs($other)->post(route('mt2.imports.store'), [
        'file' => csvUpload(SAMPLE_CSV, 'ajeno.csv'),
        'id_category' => createCategory($other),
    ]);

    $this->actingAs($this->user)->get(route('mt2.imports.index'))
        ->assertOk()
        ->assertDontSee('ajeno.csv')
        ->assertSee('Todavía no has importado extractos');
});

it('un reintento del job sobre una importación terminada no hace nada', function () {
    $this->post(route('mt2.imports.store'), ['file' => csvUpload(SAMPLE_CSV), 'id_category' => $this->category]);
    $import = StatementImport::sole();

    (new ImportBankStatement($import))->handle(app(StatementImporter::class));

    expect($import->fresh()->status)->toBe(ImportStatus::Completed)
        ->and(DB::table('gasto')->count())->toBe(2);
});

it('si el job agota sus reintentos, la importación queda como fallida', function () {
    Queue::fake();
    $this->post(route('mt2.imports.store'), ['file' => csvUpload(SAMPLE_CSV), 'id_category' => $this->category]);
    $import = StatementImport::sole();

    (new ImportBankStatement($import))->failed(new RuntimeException('BD caída'));

    expect($import->fresh()->status)->toBe(ImportStatus::Failed)
        ->and($import->fresh()->failure_message)->toContain('No se pudo procesar');
});

it('pide crear una categoría antes de importar si no hay ninguna', function () {
    $this->actingAs(activeUser())
        ->get(route('mt2.imports.index'))
        ->assertSee('Primero crea una categoría');
});
