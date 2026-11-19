<?php

namespace App\Providers;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Income;
use App\Policies\FinanceRecordPolicy;
use App\Statements\GenericCsvParser;
use App\Statements\StatementParser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Formato de extracto por defecto. Para soportar el CSV propio de un
        // banco, se crea otra implementación de StatementParser.
        $this->app->bind(StatementParser::class, GenericCsvParser::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Una sola regla de propiedad para todos los registros financieros.
        foreach ([Category::class, Expense::class, Income::class, Budget::class] as $model) {
            Gate::policy($model, FinanceRecordPolicy::class);
        }

        if (app()->environment('local')) {
            URL::forceRootUrl(
                config('app.url')
            );
        }

        if (app()->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
