<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use App\Services\BudgetRecurrenceService;

class FinanceController extends Controller
{
    private const MODULES = [
        'categorias' => ['table' => 'categoria', 'title' => 'Categorías', 'icon' => 'categorias', 'fields' => ['description']],
        'gastos' => ['table' => 'gasto', 'title' => 'Gastos y ahorros', 'icon' => 'gastos', 'fields' => ['description', 'amount', 'date', 'id_category', 'save']],
        'ingresos' => ['table' => 'ingreso', 'title' => 'Ingresos', 'icon' => 'ingresos', 'fields' => ['description', 'amount']],
        'presupuestos' => ['table' => 'presupuesto', 'title' => 'Presupuestos', 'icon' => 'presupuestos', 'fields' => ['description', 'amount', 'id_category']],
    ];
    private function config(string $modulo): array
    {
        abort_unless(isset(self::MODULES[$modulo]), 404);
        return self::MODULES[$modulo];
    }
    private function base(string $modulo)
    {
        return DB::table($this->config($modulo)['table'])->where('user_id', auth()->id());
    }
    private function row(string $modulo, int $id)
    {
        return $this->base($modulo)->where('id', $id)->firstOrFail();
    }
    private function categories()
    {
        return DB::table('categoria')->where('user_id', auth()->id())->where('estado', true)->orderBy('description')->get(['id', 'description']);
    }
    public function index(Request $request, string $modulo)
    {
        $config = $this->config($modulo);
        $query = $this->base($modulo);
        if ($request->filled('q')) {
            $q = mb_substr($request->input('q'), 0, 100);
            $query->where('description', 'ilike', '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%');
        }
        if ($modulo === 'gastos' && in_array($request->input('tipo'), ['ahorro', 'consumo'], true)) $query->where('save', $request->input('tipo') === 'ahorro');
        if ($modulo === 'gastos') {
            $mes = $request->input('mes');
            $desde = $request->input('desde');
            $hasta = $request->input('hasta');

            if ($request->filled('desde') || $request->filled('hasta')) {

                $request->validate([
                    'desde' => ['required', 'date_format:Y-m-d'],
                    'hasta' => [
                        'required',
                        'date_format:Y-m-d',
                        'after_or_equal:desde'
                    ],
                ]);

                $query->whereBetween('date', [$desde, $hasta]);

            } elseif ($request->filled('mes')) {

                $request->validate([
                    'mes' => ['required', 'date_format:Y-m'],
                ]);

                $start = Carbon::createFromFormat('!Y-m', $mes)
                    ->startOfMonth();

                $query->whereBetween('date', [
                    $start->toDateString(),
                    $start->copy()->endOfMonth()->toDateString(),
                ]);
            }
        }
        $rows = $query
            ->orderByDesc($modulo === 'gastos' ? 'date' : 'created_at')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        $rows->withPath(
            rtrim(config('app.url'), '/') . $request->getPathInfo()
        );
        $names = $this->categories()->pluck('description', 'id');
        return view('finance.index', compact('modulo', 'config', 'rows', 'names'));
    }
    public function create(string $modulo)
    {
        $config = $this->config($modulo);
        $item = null;
        $categories = $this->categories();
        return view('finance.form', compact('modulo', 'config', 'item', 'categories'));
    }
    public function edit(string $modulo, int $id)
    {
        $config = $this->config($modulo);
        $item = $this->row($modulo, $id);
        $categories = $this->categories();
        return view('finance.form', compact('modulo', 'config', 'item', 'categories'));
    }
    private function validated(Request $request, string $modulo): array
    {
        $rules = ['description' => ['required', 'string', 'max:100']];
        if ($modulo !== 'categorias') $rules['amount'] = ['required', 'numeric', 'gt:0', 'max:999999999999.99', 'decimal:0,2'];
        if ($modulo === 'gastos') {
            $rules['date'] = ['required', 'date_format:Y-m-d'];
            $rules['save'] = ['required', 'boolean'];
        }
        if (in_array($modulo, ['gastos', 'presupuestos'])) $rules['id_category'] = ['required', 'integer', Rule::exists('categoria', 'id')->where('user_id', auth()->id())->where('estado', true)];
        return $request->validate($rules);
    }

    public function store(
        Request $request,
        string $modulo,
        BudgetRecurrenceService $budgets
    ) {
        $this->config($modulo);

        $data = $this->validated($request, $modulo);

        if ($modulo === 'presupuestos') {
            $request->validate([
                'effective_month' => ['required', 'date_format:Y-m'],
            ]);
        }

        $effectiveMonth = $request->input('effective_month');

        $data['estado'] = true;
        $data['user_id'] = auth()->id();
        $data['created_at'] = now();
        $data['updated_at'] = now();

        DB::transaction(function () use (
            $modulo,
            $data,
            $effectiveMonth,
            $budgets
        ) {
            $id = DB::table($this->config($modulo)['table'])
                ->insertGetId($data);

            if ($modulo === 'presupuestos') {
                $budgets->initialize(
                    (int) $id,
                    (string) $data['amount'],
                    $effectiveMonth
                );
            }
        });

        return redirect()
            ->route('mt2.finance.index', $modulo)
            ->with('ok', 'Registro creado correctamente.');
    }

    public function update(
        Request $request,
        string $modulo,
        int $id,
        BudgetRecurrenceService $budgets
    ) {
        $item = $this->row($modulo, $id);
        $data = $this->validated($request, $modulo);

        if ($modulo === 'presupuestos') {
            $request->validate([
                'effective_month' => ['required', 'date_format:Y-m'],
            ]);

            $effectiveMonth = $request->input('effective_month');

            DB::transaction(function () use (
                $id,
                $data,
                $effectiveMonth,
                $budgets
            ) {
                $budgets->change(
                    $id,
                    (int) auth()->id(),
                    (string) $data['amount'],
                    $effectiveMonth
                );

                // El importe ya fue actualizado por el servicio.
                unset($data['amount']);

                $data['updated_at'] = now();

                DB::table('presupuesto')
                    ->where('user_id', auth()->id())
                    ->where('id', $id)
                    ->update($data);
            });
        } else {
            $data['updated_at'] = now();

            DB::table($this->config($modulo)['table'])
                ->where('user_id', auth()->id())
                ->where('id', $id)
                ->update($data);
        }

        return redirect()
            ->route('mt2.finance.index', $modulo)
            ->with('ok', 'Registro actualizado.');
    }

    public function destroy(string $modulo, int $id)
    {
        $this->row($modulo, $id);
        if ($modulo === 'categorias') {
            $used = DB::table('gasto')->where('user_id', auth()->id())->where('id_category', $id)->exists() || DB::table('presupuesto')->where('user_id', auth()->id())->where('id_category', $id)->exists();
            if ($used) return back()->withErrors(['delete' => 'Esta categoría tiene gastos o presupuestos asociados y no se puede desactivar.']);
        }
        DB::table($this->config($modulo)['table'])->where('user_id', auth()->id())->where('id', $id)->update(['estado' => false, 'updated_at' => now()]);
        return back()->with('ok', 'Registro desactivado.');
    }
    public function activate(string $modulo, int $id)
    {
        // Valida que el módulo exista y que el registro
        // pertenezca al usuario autenticado.
        $item = $this->row($modulo, $id);

        if ($item->estado) {
            return back()->with('ok', 'El registro ya se encuentra activo.');
        }

        $this->base($modulo)
            ->where('id', $id)
            ->update([
                'estado' => true,
                'updated_at' => now(),
            ]);

        return back()->with('ok', 'Registro activado correctamente.');
    }

    public function report(Request $request)
    {
        $month = $request->input('mes', now()->format('Y-m'));

        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $month = now()->format('Y-m');
        }

        $first = Carbon::createFromFormat('!Y-m', $month)
            ->startOfMonth();

        $last = $first->copy()->endOfMonth();

        // Gastos y ahorros del usuario
        $gasto = DB::table('gasto')
            ->where('gasto.user_id', auth()->id())
            ->where('gasto.estado', true)
            ->whereBetween('gasto.date', [
                $first->toDateString(),
                $last->toDateString()
            ]);

        // Ingresos del usuario
        // El límite superior es exclusivo para incluir
        // todos los movimientos del último día del mes.
        $income = DB::table('ingreso')
            ->where('ingreso.user_id', auth()->id())
            ->where('ingreso.estado', true)
            ->where('ingreso.created_at', '>=', $first)
            ->where('ingreso.created_at', '<', $first->copy()->addMonth());

        // Resumen financiero
        $totals = [
            'income' => (clone $income)->sum('amount'),
            'spent' => (clone $gasto)
                ->where('gasto.save', false)
                ->sum('gasto.amount'),
            'saving' => (clone $gasto)
                ->where('gasto.save', true)
                ->sum('gasto.amount'),
        ];

        // Distribución de gastos de consumo por categoría
        $breakdown = (clone $gasto)
            ->where('gasto.save', false)
            ->join('categoria', function ($join) {
                $join->on(
                    'categoria.id',
                    '=',
                    'gasto.id_category'
                )->on(
                    'categoria.user_id',
                    '=',
                    'gasto.user_id'
                );
            })
            ->select('categoria.description')
            ->selectRaw('SUM(gasto.amount) AS total')
            ->groupBy('categoria.id', 'categoria.description')
            ->orderByDesc('total')
            ->get();

        return view('finance.report', compact(
            'month',
            'totals',
            'breakdown'
        ));
    }
}
