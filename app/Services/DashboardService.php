<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Arma los datos del dashboard con un número fijo de consultas, sin importar
 * cuántos movimientos tenga el usuario:
 *
 *   1. gastos y ahorros de los 6 meses, agrupados por mes y tipo
 *   2. ingresos de los 6 meses, agrupados por mes
 *   3. consumo del mes por categoría
 *   4-5. últimos gastos e ingresos del mes
 *   6-7. resumen de presupuestos (BudgetRecurrenceService)
 *
 * Antes: 3 consultas por cada mes de la serie + 3 más para los totales del
 * mes (que son el último punto de la misma serie).
 */
class DashboardService
{
    private const SERIES_MONTHS = 6;

    private const LATEST_MOVEMENTS = 6;

    public function __construct(private readonly BudgetRecurrenceService $budgets) {}

    /**
     * @param  string  $month  Y-m
     */
    public function build(User $user, string $month): array
    {
        $start = CarbonImmutable::createFromFormat('!Y-m', $month)->startOfMonth();
        $end = $start->addMonth();
        $seriesStart = $start->subMonths(self::SERIES_MONTHS - 1);

        $months = $this->monthlySeries($user, $seriesStart, $end);
        $current = end($months);

        return [
            'month' => $month,
            'start' => $start->toMutable(),
            'income' => $current['income'],
            'spent' => $current['spent'],
            'saving' => $current['saving'],
            'available' => $current['income'] - $current['spent'] - $current['saving'],
            'categoryRows' => $this->spendingByCategory($user, $start, $end),
            'months' => $months,
            'movements' => $this->latestMovements($user, $start, $end),
            'budgetSummary' => $this->budgets->summary($user->id, $month),
        ];
    }

    /**
     * Ingresos, consumo y ahorro de cada mes, del más antiguo al más reciente.
     *
     * @return list<array{label: string, income: float, spent: float, saving: float}>
     */
    private function monthlySeries(User $user, CarbonImmutable $from, CarbonImmutable $until): array
    {
        $expenses = $user->expenses()->active()->inPeriod($from, $until)
            ->toBase()
            ->selectRaw("to_char(date, 'YYYY-MM') AS period, save, SUM(amount) AS total")
            ->groupBy('period', 'save')
            ->get();

        $incomes = $user->incomes()->active()->inPeriod($from, $until)
            ->toBase()
            ->selectRaw("to_char(created_at, 'YYYY-MM') AS period, SUM(amount) AS total")
            ->groupBy('period')
            ->pluck('total', 'period');

        $series = [];

        for ($i = 0; $i < self::SERIES_MONTHS; $i++) {
            $period = $from->addMonths($i);
            $key = $period->format('Y-m');

            $series[] = [
                'label' => $period->locale('es')->translatedFormat('M y'),
                'income' => (float) ($incomes[$key] ?? 0),
                'spent' => $this->total($expenses, $key, saving: false),
                'saving' => $this->total($expenses, $key, saving: true),
            ];
        }

        return $series;
    }

    private function total(Collection $rows, string $period, bool $saving): float
    {
        return (float) $rows
            ->where('period', $period)
            ->where('save', $saving)
            ->sum('total');
    }

    /** Consumo del mes por categoría, de mayor a menor. */
    private function spendingByCategory(User $user, CarbonImmutable $from, CarbonImmutable $until): Collection
    {
        return $user->expenses()->active()->consumption()->inPeriod($from, $until)
            ->join('categoria', function ($join) {
                $join->on('categoria.id', '=', 'gasto.id_category')
                    ->on('categoria.user_id', '=', 'gasto.user_id');
            })
            ->toBase()
            ->select('categoria.description')
            ->selectRaw('SUM(gasto.amount) AS total')
            ->groupBy('categoria.id', 'categoria.description')
            ->orderByDesc('total')
            ->get();
    }

    /** Últimos movimientos del mes, gastos e ingresos mezclados por fecha. */
    private function latestMovements(User $user, CarbonImmutable $from, CarbonImmutable $until): Collection
    {
        $expenses = $user->expenses()->active()->inPeriod($from, $until)
            ->orderByDesc('date')
            ->limit(self::LATEST_MOVEMENTS)
            ->toBase()
            ->get(['description', 'amount', 'date', 'save'])
            ->map(fn ($row) => [
                'description' => $row->description,
                'amount' => (float) $row->amount,
                'date' => $row->date,
                'type' => $row->save ? 'Ahorro' : 'Gasto',
            ]);

        $incomes = $user->incomes()->active()->inPeriod($from, $until)
            ->orderByDesc('created_at')
            ->limit(self::LATEST_MOVEMENTS)
            ->toBase()
            ->get(['description', 'amount', 'created_at'])
            ->map(fn ($row) => [
                'description' => $row->description,
                'amount' => (float) $row->amount,
                'date' => $row->created_at,
                'type' => 'Ingreso',
            ]);

        return $expenses->concat($incomes)
            ->sortByDesc('date')
            ->take(self::LATEST_MOVEMENTS)
            ->values();
    }
}
