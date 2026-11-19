<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardService $dashboard): View
    {
        return view('dashboard.index', $dashboard->build(
            $request->user(),
            $this->selectedMonth($request),
        ));
    }

    /**
     * Mes a mostrar (Y-m):
     * - con ?mes= válido, ese periodo;
     * - sin ?mes=, el mes preferido de la configuración en el año actual;
     * - con un ?mes= inválido, el mes actual.
     */
    private function selectedMonth(Request $request): string
    {
        if (! $request->has('mes')) {
            $preferred = DB::table('user_settings')
                ->where('user_id', $request->user()->id)
                ->value('default_month');

            return now()->year.'-'.str_pad((string) ($preferred ?: now()->month), 2, '0', STR_PAD_LEFT);
        }

        $month = $request->query('mes');

        return is_string($month) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)
            ? $month
            : now()->format('Y-m');
    }
}
