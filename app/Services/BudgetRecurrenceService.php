<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BudgetRecurrenceService
{
    /** Create initial period for a newly created recurring budget. */
    public function initialize(int $budgetId, string $amount, string $month): void
    {
        $this->saveVersion($budgetId, $amount, $month);
    }

    /** Changing a budget's limit affects that month and later months only. */
    public function change(int $budgetId, int $userId, string $amount, string $month): void
    {
        $budget = DB::table('presupuesto')->where('id', $budgetId)
            ->where('user_id', $userId)->first();
        abort_unless($budget, 404);

        $lastVersion = DB::table('presupuesto_vigencia')
            ->where('presupuesto_id', $budgetId)
            ->orderByDesc('effective_month')->first();

        // Prevent a historical revision from overwriting future decisions.
        if ($lastVersion && $month < Carbon::parse($lastVersion->effective_month)->format('Y-m')) {
            throw ValidationException::withMessages([
                'effective_month' => 'Ya existe una vigencia posterior. Elige ese mes o uno posterior.',
            ]);
        }

        DB::transaction(function () use ($budgetId, $userId, $amount, $month) {
            $this->saveVersion($budgetId, $amount, $month);
            DB::table('presupuesto')->where('id', $budgetId)
                ->where('user_id', $userId)
                ->update(['amount' => $amount, 'updated_at' => now()]);
        });
    }

    private function saveVersion(int $budgetId, string $amount, string $month): void
    {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            throw ValidationException::withMessages(['effective_month' => 'Mes de vigencia inválido.']);
        }
        DB::table('presupuesto_vigencia')->updateOrInsert(
            ['presupuesto_id' => $budgetId, 'effective_month' => $month . '-01'],
            ['amount' => $amount, 'updated_at' => now(), 'created_at' => now()]
        );
    }

    /** Active monthly budgets, using the latest version not after requested month. */
    public function summary(int $userId, string $month): array
    {
        $period = Carbon::createFromFormat('!Y-m', $month)->toDateString();
        $first = Carbon::createFromFormat('!Y-m', $month)->startOfMonth()->toDateString();
        $end = Carbon::createFromFormat('!Y-m', $month)->addMonth()->startOfMonth()->toDateString();

        $budgets = DB::table('presupuesto as p')
            ->join('categoria as c', function ($join) {
                $join->on('c.id', '=', 'p.id_category')->on('c.user_id', '=', 'p.user_id');
            })
            ->join('presupuesto_vigencia as v', 'v.presupuesto_id', '=', 'p.id')
            ->where('p.user_id', $userId)->where('p.estado', true)
            ->where('v.effective_month', '<=', $period)
            ->whereRaw('v.effective_month = (SELECT MAX(v2.effective_month) FROM presupuesto_vigencia v2 WHERE v2.presupuesto_id = p.id AND v2.effective_month <= ?)', [$period])
            ->select('p.id', 'p.description', 'p.id_category', 'c.description as category', 'v.amount as limit_amount')
            ->orderBy('p.description')->get();

        $spent = DB::table('gasto')->where('user_id', $userId)->where('estado', true)
            ->where('save', false)->where('date', '>=', $first)->where('date', '<', $end)
            ->select('id_category')->selectRaw('SUM(amount) AS total')
            ->groupBy('id_category')->pluck('total', 'id_category');

        // Budgets with the same category share consumption. They are not added together
        // as if consumption happened twice. Prefer one active budget per category.
        return $budgets->map(fn ($b) => [
            'id' => $b->id,
            'description' => $b->description,
            'category' => $b->category,
            'amount' => (float) $b->limit_amount,
            'spent' => (float) ($spent[$b->id_category] ?? 0),
            'percent' => (float) $b->limit_amount > 0
                ? round((float) ($spent[$b->id_category] ?? 0) * 100 / (float) $b->limit_amount, 1)
                : 0,
        ])->all();
    }
}
