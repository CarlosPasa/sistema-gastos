<?php

namespace App\Statements;

/**
 * Un movimiento válido leído de un extracto.
 * El monto siempre es positivo; isIncome indica la dirección.
 */
final readonly class StatementLine
{
    public function __construct(
        public int $lineNumber,
        public string $date,
        public string $description,
        public string $amount,
        public bool $isIncome,
    ) {}
}
