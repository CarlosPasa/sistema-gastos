<?php

namespace App\Providers;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Income;
use App\Policies\FinanceRecordPolicy;
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
        //
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
