<?php

namespace App\Http\Controllers;

use App\Enums\FinanceModule;
use App\Models\Budget;
use App\Models\Category;
use App\Services\BudgetRecurrenceService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Pantallas de listado y formulario compartidas por categorías, gastos,
 * ingresos y presupuestos.
 *
 * - Qué módulo es: FinanceModule (enum resuelto desde la URL).
 * - Validación: un Form Request por módulo (App\Http\Requests\Finance).
 * - Quién puede tocar un registro: FinanceRecordPolicy (404 si no es tuyo).
 * - Reglas de presupuestos: BudgetRecurrenceService.
 */
class FinanceController extends Controller
{
    private const PER_PAGE = 12;

    public function __construct(private readonly BudgetRecurrenceService $budgets) {}

    public function index(Request $request, FinanceModule $modulo): View
    {
        $query = $this->ownedRecords($request, $modulo)->search($request->input('q'));

        if ($modulo === FinanceModule::Expenses) {
            $this->applyExpenseFilters($request, $query);
        }

        $rows = $query
            ->orderByDesc($modulo->sortColumn())
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->withPath(rtrim(config('app.url'), '/').$request->getPathInfo());

        return view('finance.index', [
            'modulo' => $modulo->value,
            'config' => $modulo->viewConfig(),
            'rows' => $rows,
            'names' => $this->activeCategories($request)->pluck('description', 'id'),
        ]);
    }

    public function create(Request $request, FinanceModule $modulo): View
    {
        return $this->form($request, $modulo, null);
    }

    public function edit(Request $request, FinanceModule $modulo, int $id): View
    {
        $record = $this->findRecord($modulo, $id);
        Gate::authorize('view', $record);

        return $this->form($request, $modulo, $record);
    }

    public function store(Request $request, FinanceModule $modulo): RedirectResponse
    {
        $data = $this->validated($modulo);

        if ($modulo === FinanceModule::Budgets) {
            $this->budgets->create($request->user(), $data);
        } else {
            $this->ownedRecords($request, $modulo)->create($data);
        }

        return redirect()
            ->route('mt2.finance.index', $modulo->value)
            ->with('ok', 'Registro creado correctamente.');
    }

    public function update(FinanceModule $modulo, int $id): RedirectResponse
    {
        $record = $this->findRecord($modulo, $id);
        Gate::authorize('update', $record);

        $data = $this->validated($modulo);

        if ($record instanceof Budget) {
            $this->budgets->update($record, $data);
        } else {
            $record->update($data);
        }

        return redirect()
            ->route('mt2.finance.index', $modulo->value)
            ->with('ok', 'Registro actualizado.');
    }

    public function destroy(FinanceModule $modulo, int $id): RedirectResponse
    {
        $record = $this->findRecord($modulo, $id);
        Gate::authorize('delete', $record);

        if ($record instanceof Category && $record->isInUse()) {
            return back()->withErrors([
                'delete' => 'Esta categoría tiene gastos o presupuestos asociados y no se puede desactivar.',
            ]);
        }

        $record->deactivate();

        return back()->with('ok', 'Registro desactivado.');
    }

    public function activate(FinanceModule $modulo, int $id): RedirectResponse
    {
        $record = $this->findRecord($modulo, $id);
        Gate::authorize('restore', $record);

        if ($record->estado) {
            return back()->with('ok', 'El registro ya se encuentra activo.');
        }

        $record->activate();

        return back()->with('ok', 'Registro activado correctamente.');
    }

    public function report(Request $request): View
    {
        $month = $request->input('mes', now()->format('Y-m'));

        if (! is_string($month) || ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $month = now()->format('Y-m');
        }

        $from = Carbon::createFromFormat('!Y-m', $month)->startOfMonth();
        $until = $from->copy()->addMonth();
        $user = $request->user();

        $expenses = $user->expenses()->active()->inPeriod($from, $until);

        $totals = [
            'income' => $user->incomes()->active()->inPeriod($from, $until)->sum('amount'),
            'spent' => (clone $expenses)->consumption()->sum('amount'),
            'saving' => (clone $expenses)->savings()->sum('amount'),
        ];

        // Consumo por categoría, de mayor a menor.
        $breakdown = (clone $expenses)
            ->consumption()
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

        return view('finance.report', compact('month', 'totals', 'breakdown'));
    }

    /** Registros del módulo que pertenecen al usuario autenticado. */
    private function ownedRecords(Request $request, FinanceModule $modulo): HasMany
    {
        return $request->user()->{$modulo->relation()}();
    }

    /**
     * Busca el registro sin filtrar por usuario: la Policy decide si es
     * tuyo y responde 404 si no lo es.
     */
    private function findRecord(FinanceModule $modulo, int $id): Model
    {
        return $modulo->model()::query()->findOrFail($id);
    }

    /** Valida con el Form Request del módulo y devuelve solo los campos permitidos. */
    private function validated(FinanceModule $modulo): array
    {
        return app($modulo->request())->validated();
    }

    private function form(Request $request, FinanceModule $modulo, ?Model $record): View
    {
        return view('finance.form', [
            'modulo' => $modulo->value,
            'config' => $modulo->viewConfig(),
            'item' => $record,
            'categories' => $modulo->usesCategories() ? $this->activeCategories($request) : collect(),
        ]);
    }

    private function activeCategories(Request $request): Collection
    {
        return $request->user()->categories()
            ->active()
            ->orderBy('description')
            ->get(['id', 'description']);
    }

    /** Filtros del listado de gastos: tipo y (rango de fechas o mes). */
    private function applyExpenseFilters(Request $request, HasMany|Builder $query): void
    {
        $type = $request->input('tipo');

        if (in_array($type, ['ahorro', 'consumo'], true)) {
            $type === 'ahorro' ? $query->savings() : $query->consumption();
        }

        if ($request->filled('desde') || $request->filled('hasta')) {
            $range = $request->validate([
                'desde' => ['required', 'date_format:Y-m-d'],
                'hasta' => ['required', 'date_format:Y-m-d', 'after_or_equal:desde'],
            ]);

            $query->whereBetween('date', [$range['desde'], $range['hasta']]);

            return;
        }

        if ($request->filled('mes')) {
            $request->validate(['mes' => ['required', 'date_format:Y-m']]);

            $from = Carbon::createFromFormat('!Y-m', $request->input('mes'));
            $query->inPeriod($from, $from->copy()->addMonth());
        }
    }
}
