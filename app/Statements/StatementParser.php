<?php

namespace App\Statements;

/**
 * Lee el contenido de un extracto. Cada banco que exporte un formato distinto
 * puede tener su propia implementación sin tocar el importador.
 */
interface StatementParser
{
    /** @throws InvalidStatementException si el archivo completo no es válido */
    public function parse(string $content): ParsedStatement;
}
