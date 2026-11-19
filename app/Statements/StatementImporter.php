<?php

namespace App\Statements;

use App\Enums\ImportStatus;
use App\Models\Expense;
use App\Models\Income;
use App\Models\StatementImport;
use Illuminate\Support\Facades\DB;

/**
 * Convierte un extracto en gastos e ingresos del usuario.
 *
 * - Los montos negativos se registran como gastos de consumo en la categoría
 *   elegida al subir el archivo; los positivos, como ingresos.
 * - Cada movimiento lleva una huella (import_fingerprint). Con el índice
 *   único (user_id, import_fingerprint) y INSERT ... ON CONFLICT DO NOTHING,
 *   volver a subir el mismo extracto no duplica nada y reintentar el job es
 *   seguro.
 * - Las filas inválidas se informan; no impiden importar las demás.
 */
class StatementImporter
{
    private const CHUNK = 500;

    public function __construct(private readonly StatementParser $parser) {}

    public function import(StatementImport $import): void
    {
        // Un job repetido sobre una importación ya terminada no hace nada.
        if ($import->status->isFinished()) {
            return;
        }

        $import->forceFill(['status' => ImportStatus::Processing])->save();

        try {
            $statement = $this->parser->parse((string) $import->content);
        } catch (InvalidStatementException $e) {
            $this->fail($import, $e->getMessage());

            return;
        }

        [$expenses, $incomes] = $this->buildRows($import, $statement->lines);

        DB::transaction(function () use ($import, $statement, $expenses, $incomes) {
            $createdExpenses = $this->insertIgnoringDuplicates(Expense::class, $expenses);
            $createdIncomes = $this->insertIgnoringDuplicates(Income::class, $incomes);

            $import->forceFill([
                'status' => ImportStatus::Completed,
                'total_rows' => $statement->totalRows,
                'created_expenses' => $createdExpenses,
                'created_incomes' => $createdIncomes,
                'skipped_duplicates' => count($statement->lines) - $createdExpenses - $createdIncomes,
                'row_errors' => $statement->errors,
                'content' => null,
                'processed_at' => now(),
            ])->save();
        });
    }

    public function fail(StatementImport $import, string $message): void
    {
        $import->forceFill([
            'status' => ImportStatus::Failed,
            'failure_message' => mb_substr($message, 0, 500),
            'content' => null,
            'processed_at' => now(),
        ])->save();
    }

    /**
     * @param  list<StatementLine>  $lines
     * @return array{0: list<array>, 1: list<array>}
     */
    private function buildRows(StatementImport $import, array $lines): array
    {
        $now = now();
        $occurrences = [];
        $expenses = [];
        $incomes = [];

        foreach ($lines as $line) {
            // Dos movimientos idénticos en el mismo extracto (dos cafés iguales
            // el mismo día) son legítimos: el número de aparición los distingue.
            $key = implode('|', [
                $line->isIncome ? 'in' : 'out',
                $line->date,
                $line->amount,
                mb_strtolower($line->description),
            ]);
            $occurrences[$key] = ($occurrences[$key] ?? 0) + 1;

            $row = [
                'description' => $line->description,
                'amount' => $line->amount,
                'date' => $line->date,
                'estado' => true,
                'user_id' => $import->user_id,
                'import_fingerprint' => hash('sha256', $key.'|'.$occurrences[$key]),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($line->isIncome) {
                $incomes[] = $row;
            } else {
                $expenses[] = $row + ['save' => false, 'id_category' => $import->category_id];
            }
        }

        return [$expenses, $incomes];
    }

    /** @param  class-string<Expense|Income>  $model */
    private function insertIgnoringDuplicates(string $model, array $rows): int
    {
        $created = 0;

        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            $created += $model::query()->insertOrIgnore($chunk);
        }

        return $created;
    }
}
