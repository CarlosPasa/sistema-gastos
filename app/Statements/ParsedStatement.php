<?php

namespace App\Statements;

/**
 * Resultado de leer un extracto: movimientos válidos y errores por fila.
 * Las filas con error no detienen la importación de las demás.
 */
final readonly class ParsedStatement
{
    /**
     * @param  list<StatementLine>  $lines
     * @param  list<string>  $errors
     */
    public function __construct(
        public array $lines,
        public array $errors,
        public int $totalRows,
    ) {}
}
