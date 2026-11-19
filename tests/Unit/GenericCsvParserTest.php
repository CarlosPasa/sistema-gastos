<?php

use App\Statements\GenericCsvParser;
use App\Statements\InvalidStatementException;

function parseCsv(string $csv)
{
    return (new GenericCsvParser)->parse($csv);
}

it('lee fecha, descripción y monto; negativo es gasto y positivo ingreso', function () {
    $result = parseCsv("fecha,descripcion,monto\n2026-11-03,Supermercado,-150.50\n2026-11-05,Sueldo,3000");

    expect($result->totalRows)->toBe(2)
        ->and($result->errors)->toBe([])
        ->and($result->lines[0]->date)->toBe('2026-11-03')
        ->and($result->lines[0]->amount)->toBe('150.50')
        ->and($result->lines[0]->isIncome)->toBeFalse()
        ->and($result->lines[1]->amount)->toBe('3000.00')
        ->and($result->lines[1]->isIncome)->toBeTrue();
});

it('entiende los formatos de monto habituales', function (string $raw, string $expected, bool $isIncome) {
    $line = parseCsv("fecha;descripcion;monto\n2026-11-03;X;{$raw}")->lines[0];

    expect($line->amount)->toBe($expected)->and($line->isIncome)->toBe($isIncome);
})->with([
    'punto decimal' => ['-45.90', '45.90', false],
    'coma decimal' => ['-45,9', '45.90', false],
    'miles con coma' => ['1,500', '1500.00', true],
    'miles y decimal anglosajón' => ['"-1,234.50"', '1234.50', false],
    'miles y decimal europeo' => ['-1.234,50', '1234.50', false],
    'con símbolo de soles' => ['S/ 250.00', '250.00', true],
    'con signo +' => ['+10', '10.00', true],
]);

it('acepta fechas AAAA-MM-DD, DD/MM/AAAA y DD-MM-AAAA', function (string $raw) {
    expect(parseCsv("fecha,descripcion,monto\n{$raw},X,-1")->lines[0]->date)->toBe('2026-11-03');
})->with(['2026-11-03', '03/11/2026', '03-11-2026']);

it('detecta el separador punto y coma y quita el BOM de Excel', function () {
    $result = parseCsv("\xEF\xBB\xBFFecha;Descripción;Monto\r\n03/11/2026;Taxi;-12,50\r\n");

    expect($result->lines)->toHaveCount(1)
        ->and($result->lines[0]->description)->toBe('Taxi')
        ->and($result->lines[0]->amount)->toBe('12.50');
});

it('acepta encabezados en inglés o con otros nombres y en cualquier orden', function () {
    $line = parseCsv("Amount,Date,Concepto\n-5,2026-11-03,Pan")->lines[0];

    expect($line->description)->toBe('Pan')->and($line->amount)->toBe('5.00');
});

it('informa cada fila inválida sin descartar las válidas', function () {
    $result = parseCsv(implode("\n", [
        'fecha,descripcion,monto',
        '2026-11-03,Válida,-10',
        '2026-02-30,Fecha imposible,-10',
        '2026-11-04,,-10',
        '2026-11-05,Monto raro,abc',
        '2026-11-06,Cero,0',
        '2026-11-07,Tres decimales,-1.234',
        '',
        '2026-11-08,Otra válida,25',
    ]));

    expect($result->lines)->toHaveCount(2)
        ->and($result->totalRows)->toBe(7)
        ->and($result->errors)->toBe([
            'Fila 3: fecha inválida',
            'Fila 4: falta la descripción',
            'Fila 5: monto inválido',
            'Fila 6: el monto es cero',
            'Fila 7: monto inválido',
        ]);
});

it('recorta descripciones largas a 100 caracteres y compacta espacios', function () {
    $line = parseCsv("fecha,descripcion,monto\n2026-11-03,\"  Compra   en ".str_repeat('x', 150).'",-1')->lines[0];

    expect(mb_strlen($line->description))->toBe(100)
        ->and($line->description)->toStartWith('Compra en x');
});

it('rechaza el archivo completo si faltan columnas', function () {
    parseCsv("fecha,detalle\n2026-11-03,X");
})->throws(InvalidStatementException::class, 'Falta la columna "monto"');

it('rechaza un archivo vacío', function () {
    parseCsv("   \n");
})->throws(InvalidStatementException::class, 'vacío');

it('rechaza archivos con demasiadas filas', function () {
    parseCsv("fecha,descripcion,monto\n".str_repeat("2026-11-03,X,-1\n", GenericCsvParser::MAX_ROWS + 1));
})->throws(InvalidStatementException::class, 'más de 5000');
