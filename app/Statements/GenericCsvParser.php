<?php

namespace App\Statements;

use DateTimeImmutable;

/**
 * CSV genérico con encabezados: fecha, descripcion, monto.
 *
 * - Separador: coma o punto y coma (se detecta en el encabezado).
 * - Fecha: AAAA-MM-DD, DD/MM/AAAA o DD-MM-AAAA.
 * - Monto: negativo = gasto, positivo = ingreso. Acepta "S/", separador de
 *   miles y decimales con punto o coma ("1,234.50", "1.234,50", "-45,9").
 * - También acepta los encabezados: date, description, concepto, amount, importe.
 */
class GenericCsvParser implements StatementParser
{
    public const MAX_ROWS = 5000;

    public const MAX_ERRORS = 50;

    private const HEADERS = [
        'date' => ['fecha', 'date'],
        'description' => ['descripcion', 'description', 'concepto', 'detalle'],
        'amount' => ['monto', 'importe', 'amount'],
    ];

    private const DATE_FORMATS = ['!Y-m-d', '!d/m/Y', '!d-m-Y'];

    public function parse(string $content): ParsedStatement
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $rows = preg_split('/\r\n|\r|\n/', trim($content));

        $headerLine = array_shift($rows);

        if ($headerLine === null || trim($headerLine) === '') {
            throw new InvalidStatementException('El archivo está vacío.');
        }

        $delimiter = substr_count($headerLine, ';') > substr_count($headerLine, ',') ? ';' : ',';
        $columns = $this->mapHeaders(str_getcsv($headerLine, $delimiter, '"', ''));

        $rows = array_filter($rows, fn ($row) => trim($row) !== '');

        if (count($rows) > self::MAX_ROWS) {
            throw new InvalidStatementException('El archivo tiene más de '.self::MAX_ROWS.' movimientos. Divídelo en partes.');
        }

        $lines = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            $lineNumber = $index + 2; // +1 por el encabezado, +1 porque empieza en 1
            $values = str_getcsv($row, $delimiter, '"', '');

            $error = null;
            $line = $this->parseRow($values, $columns, $lineNumber, $error);

            if ($line) {
                $lines[] = $line;
            } elseif (count($errors) < self::MAX_ERRORS) {
                $errors[] = "Fila {$lineNumber}: {$error}";
            }
        }

        return new ParsedStatement($lines, $errors, count($rows));
    }

    /** @return array{date: int, description: int, amount: int} */
    private function mapHeaders(array $headers): array
    {
        $normalized = array_map(fn ($h) => $this->normalizeHeader((string) $h), $headers);
        $columns = [];

        foreach (self::HEADERS as $field => $aliases) {
            $position = array_search(true, array_map(fn ($h) => in_array($h, $aliases, true), $normalized), true);

            if ($position === false) {
                throw new InvalidStatementException(
                    'Falta la columna "'.$aliases[0].'". El encabezado debe tener: fecha, descripcion, monto.'
                );
            }

            $columns[$field] = $position;
        }

        return $columns;
    }

    private function normalizeHeader(string $header): string
    {
        $header = mb_strtolower(trim($header));

        return strtr($header, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);
    }

    private function parseRow(array $values, array $columns, int $lineNumber, ?string &$error): ?StatementLine
    {
        $date = $this->parseDate(trim($values[$columns['date']] ?? ''));
        if (! $date) {
            $error = 'fecha inválida';

            return null;
        }

        $description = trim(preg_replace('/\s+/u', ' ', $values[$columns['description']] ?? ''));
        if ($description === '') {
            $error = 'falta la descripción';

            return null;
        }

        $cents = $this->parseCents($values[$columns['amount']] ?? '');
        if ($cents === null) {
            $error = 'monto inválido';

            return null;
        }

        if ($cents === 0) {
            $error = 'el monto es cero';

            return null;
        }

        if (abs($cents) > 99_999_999_999_999) {
            $error = 'monto demasiado grande';

            return null;
        }

        return new StatementLine(
            lineNumber: $lineNumber,
            date: $date,
            description: mb_substr($description, 0, 100),
            amount: sprintf('%d.%02d', intdiv(abs($cents), 100), abs($cents) % 100),
            isIncome: $cents > 0,
        );
    }

    private function parseDate(string $value): ?string
    {
        foreach (self::DATE_FORMATS as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            $problems = DateTimeImmutable::getLastErrors();

            if ($date && ! ($problems && ($problems['warning_count'] || $problems['error_count']))) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    /**
     * Convierte el monto a céntimos enteros (sin errores de redondeo).
     * Devuelve null si no es un monto válido.
     */
    private function parseCents(string $value): ?int
    {
        $value = str_ireplace(['S/.', 'S/', 'PEN', ' ', "\u{00A0}"], '', trim($value));

        if (! preg_match('/^[+-]?[\d.,]+$/', $value)) {
            return null;
        }

        $lastDot = strrpos($value, '.');
        $lastComma = strrpos($value, ',');

        if ($lastDot !== false && $lastComma !== false) {
            // El separador que aparece al final es el decimal.
            $decimal = $lastDot > $lastComma ? '.' : ',';
        } elseif ($lastComma !== false) {
            // Solo comas: "45,9" o "45,90" es decimal; "1,500" es miles.
            $decimal = preg_match('/,\d{1,2}$/', $value) ? ',' : null;
        } else {
            $decimal = '.';
        }

        $thousands = $decimal === ',' ? '.' : ',';
        $value = str_replace($thousands, '', $value);

        if ($decimal === ',') {
            $value = str_replace(',', '.', $value);
        }

        if (! preg_match('/^([+-]?)(\d+)(?:\.(\d{1,2}))?$/', $value, $m)) {
            return null;
        }

        if (strlen(ltrim($m[2], '0')) > 12) {
            return null;
        }

        $cents = (int) $m[2] * 100 + (int) str_pad($m[3] ?? '0', 2, '0');

        return $m[1] === '-' ? -$cents : $cents;
    }
}
