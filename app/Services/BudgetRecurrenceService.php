<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Expense;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Presupuestos mensuales recurrentes con historial.
 *
 * Regla de negocio: cambiar el límite de un presupuesto afecta el mes elegido
 * y los siguientes. Los meses anteriores conservan el límite que tenían.
 * Cada cambio se guarda como una BudgetVersion (presupuesto_vigencia).
 */
class BudgetRecurrenceService
{
    /**
     * @param  array{description: string, amount: string, id_category: int, effective_month: string}  $data
     */
    public function create(User $user, array $data): Budget
    {
        return DB::transaction(function () use ($user, $data) {
            $budget = $user->budgets()->create($this->budgetAttributes($data));

            $this->saveVersion($budget, (string) $data['amount'], $data['effective_month']);

            return $budget;
        });
    }

    /**
     * @param  array{description: string, amount: string, id_category: int, effective_month: string}  $data
     */
    public function update(Budget $budget, array $data): void
    {
        $month = $data['effective_month'];

        $latestMonth = $budget->versions()->max('effective_month');

        // Un cambio histórico no puede sobrescribir decisiones posteriores.
        if ($latestMonth && $month < Carbon::parse($latestMonth)->format('Y-m')) {
            throw ValidationException::withMessages([
                'effective_month' => 'Ya existe una vigencia posterior. Elige ese mes o uno posterior.',
            ]);
        }

        DB::transaction(function () use ($budget, $data, $month) {
            $this->saveVersion($budget, (string) $data['amount'], $month);
            $budget->update($this->budgetAttributes($data));
        });
    }

    /**
     * Presupuestos activos del mes con su límite vigente y lo consumido.
     * Los ahorros no cuentan como consumo.
     *
     * @return list<array{id: int, description: string, category: string, amount: float, spent: float, percent: float}>
     */
    public function summary(int $userId, string $month): array
    {
        $period = Carbon::createFromFormat('!Y-m', $month);
        $from = $period->copy()->startOfMonth();
        $until = $period->copy()->addMonth()->startOfMonth();

        // Consulta de reporte: el límite vigente es la última versión no
        // posterior al mes pedido.
        $budgets = DB::table('presupuesto as p')
            ->join('categoria as c', function ($join) {
                $join->on('c.id', '=', 'p.id_category')->on('c.user_id', '=', 'p.user_id');
            })
            ->join('presupuesto_vigencia as v', 'v.presupuesto_id', '=', 'p.id')
            ->where('p.user_id', $userId)->where('p.estado', true)
            ->whereRaw(
                'v.effective_month = (SELECT MAX(v2.effective_month) FROM presupuesto_vigencia v2 WHERE v2.presupuesto_id = p.id AND v2.effective_month <= ?)',
                [$from->toDateString()]
            )
            ->select('p.id', 'p.description', 'p.id_category', 'c.description as category', 'v.amount as limit_amount')
            ->orderBy('p.description')
            ->get();

        $spentByCategory = Expense::query()
            ->where('user_id', $userId)
            ->active()
            ->consumption()
            ->inPeriod($from, $until)
            ->groupBy('id_category')
            ->selectRaw('id_category, SUM(amount) AS total')
            ->pluck('total', 'id_category');

        // Presupuestos de la misma categoría comparten el consumo (no se
        // suma dos veces). Lo recomendable es uno activo por categoría.
        return $budgets->map(function ($budget) use ($spentByCategory) {
            $limit = (float) $budget->limit_amount;
            $spent = (float) ($spentByCategory[$budget->id_category] ?? 0);

            return [
                'id' => $budget->id,
                'description' => $budget->description,
                'category' => $budget->category,
                'amount' => $limit,
                'spent' => $spent,
                'percent' => $limit > 0 ? round($spent * 100 / $limit, 1) : 0,
            ];
        })->all();
    }

    private function saveVersion(Budget $budget, string $amount, string $month): void
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            throw ValidationException::withMessages(['effective_month' => 'Mes de vigencia inválido.']);
        }

        $budget->versions()->updateOrCreate(
            ['effective_month' => $month.'-01'],
            ['amount' => $amount],
        );
    }

    private function budgetAttributes(array $data): array
    {
        return array_intersect_key($data, array_flip(['description', 'amount', 'id_category']));
    }
}
