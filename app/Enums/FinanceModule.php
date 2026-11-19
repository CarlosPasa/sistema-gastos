<?php

namespace App\Enums;

use App\Http\Requests\Finance\BudgetRequest;
use App\Http\Requests\Finance\CategoryRequest;
use App\Http\Requests\Finance\ExpenseRequest;
use App\Http\Requests\Finance\IncomeRequest;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Income;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Los módulos de finanzas que comparten pantallas de listado y formulario.
 * El valor es el segmento de la URL (/mt2/gastos, /mt2/ingresos, ...).
 *
 * Laravel resuelve el enum desde la ruta: un módulo inexistente da 404
 * antes de llegar al controlador.
 */
enum FinanceModule: string
{
    case Categories = 'categorias';
    case Expenses = 'gastos';
    case Incomes = 'ingresos';
    case Budgets = 'presupuestos';

    /** @return class-string<Model> */
    public function model(): string
    {
        return match ($this) {
            self::Categories => Category::class,
            self::Expenses => Expense::class,
            self::Incomes => Income::class,
            self::Budgets => Budget::class,
        };
    }

    /** Nombre de la relación en User (categories, expenses, ...). */
    public function relation(): string
    {
        return match ($this) {
            self::Categories => 'categories',
            self::Expenses => 'expenses',
            self::Incomes => 'incomes',
            self::Budgets => 'budgets',
        };
    }

    /** @return class-string<FormRequest> */
    public function request(): string
    {
        return match ($this) {
            self::Categories => CategoryRequest::class,
            self::Expenses => ExpenseRequest::class,
            self::Incomes => IncomeRequest::class,
            self::Budgets => BudgetRequest::class,
        };
    }

    public function title(): string
    {
        return match ($this) {
            self::Categories => 'Categorías',
            self::Expenses => 'Gastos y ahorros',
            self::Incomes => 'Ingresos',
            self::Budgets => 'Presupuestos',
        };
    }

    /** Columna por la que se ordena el listado (más reciente primero). */
    public function sortColumn(): string
    {
        return in_array($this, [self::Expenses, self::Incomes], true) ? 'date' : 'created_at';
    }

    public function usesCategories(): bool
    {
        return in_array($this, [self::Expenses, self::Budgets], true);
    }

    /** Datos que las vistas finance/index y finance/form esperan en $config. */
    public function viewConfig(): array
    {
        return [
            'title' => $this->title(),
            'icon' => $this->value,
        ];
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
