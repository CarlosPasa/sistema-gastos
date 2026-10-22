<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Services\BudgetRecurrenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $settings = DB::table('user_settings')
            ->where('user_id', $request->user()->id)
            ->first();

        $defaultMonth = (int) ($settings->default_month ?? now()->month);

        // Sin parámetro ?mes= usamos el mes preferido y el año actual.
        // Con ?mes= respetamos el periodo elegido manualmente.
        $month = $request->query(
            'mes',
            now()->year . '-' . str_pad(
                (string) $defaultMonth,
                2,
                '0',
                STR_PAD_LEFT
            )
        );
        if (!is_string($month) || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $month = now()->format('Y-m');
        }
        try {
            $start = Carbon::createFromFormat('!Y-m', $month)->startOfMonth();
        } catch (\Throwable $e) {
            $start = now()->startOfMonth();
            $month = $start->format('Y-m');
        }
        $end = $start->copy()->addMonth();
        $userId = $request->user()->id;

        // Use half-open ranges to include every timestamp on the final day.
        $income = (float) DB::table('ingreso')->where('user_id', $userId)
            ->where('estado', true)->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)->sum('amount');

        $expenseRows = DB::table('gasto')->where('gasto.user_id', $userId)
            ->where('gasto.estado', true)->where('gasto.date', '>=', $start->toDateString())
            ->where('gasto.date', '<', $end->toDateString());
        $spent = (float) (clone $expenseRows)->where('save', false)->sum('amount');
        $saving = (float) (clone $expenseRows)->where('save', true)->sum('amount');
        $available = $income - $spent - $saving;

        $categoryRows = (clone $expenseRows)->where('gasto.save', false)
            ->join('categoria', function ($join) {
                $join->on('categoria.id', '=', 'gasto.id_category')
                    ->on('categoria.user_id', '=', 'gasto.user_id');
            })
            ->select('categoria.description')
            ->selectRaw('SUM(gasto.amount) AS total')
            ->groupBy('categoria.id', 'categoria.description')
            ->orderByDesc('total')->get();

        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $begin = $start->copy()->subMonths($i);
            $finish = $begin->copy()->addMonth();
            $months[] = [
                'label' => $begin->locale('es')->translatedFormat('M y'),
                'income' => (float) DB::table('ingreso')->where('user_id', $userId)->where('estado', true)
                    ->where('created_at', '>=', $begin)->where('created_at', '<', $finish)->sum('amount'),
                'spent' => (float) DB::table('gasto')->where('user_id', $userId)->where('estado', true)
                    ->where('save', false)->where('date', '>=', $begin->toDateString())
                    ->where('date', '<', $finish->toDateString())->sum('amount'),
                'saving' => (float) DB::table('gasto')->where('user_id', $userId)->where('estado', true)
                    ->where('save', true)->where('date', '>=', $begin->toDateString())
                    ->where('date', '<', $finish->toDateString())->sum('amount'),
            ];
        }

        $movements = collect();
        $expenses = DB::table('gasto')->where('user_id', $userId)->where('estado', true)
            ->where('date', '>=', $start->toDateString())->where('date', '<', $end->toDateString())
            ->orderByDesc('date')->limit(8)->get(['description', 'amount', 'date', 'save']);
        foreach ($expenses as $expense) {
            $movements->push([
                'description' => $expense->description,
                'amount' => (float) $expense->amount,
                'date' => $expense->date,
                'type' => $expense->save ? 'Ahorro' : 'Gasto',
            ]);
        }
        $incomes = DB::table('ingreso')->where('user_id', $userId)->where('estado', true)
            ->where('created_at', '>=', $start)->where('created_at', '<', $end)
            ->orderByDesc('created_at')->limit(8)->get(['description', 'amount', 'created_at']);
        foreach ($incomes as $item) {
            $movements->push([
                'description' => $item->description,
                'amount' => (float) $item->amount,
                'date' => $item->created_at,
                'type' => 'Ingreso',
            ]);
        }
        $movements = $movements->sortByDesc('date')->take(6)->values();
        // Presupuestos recurrentes del periodo seleccionado.
        $budgetSummary = app(BudgetRecurrenceService::class)
            ->summary((int) $userId, $month);

        return view('dashboard.index', compact(
            'month', 'start', 'income', 'spent', 'saving', 'available',
            'categoryRows', 'months', 'movements', 'budgetSummary'
        ));
    }
}
