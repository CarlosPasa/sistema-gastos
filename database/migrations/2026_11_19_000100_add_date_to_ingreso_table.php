<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Los ingresos pasan a tener fecha propia (`date`), igual que los gastos.
 *
 * Antes el mes de un ingreso salía de `created_at`, así que no se podía
 * registrar hoy un sueldo del mes pasado. Se hace en tres pasos para no
 * perder datos ni bloquear la tabla con valores nulos:
 *
 *   1. agregar la columna como opcional;
 *   2. rellenarla con la fecha de `created_at` (mismo criterio que usaba el
 *      dashboard: cada ingreso sigue apareciendo en el mismo mes);
 *   3. volverla obligatoria e indexarla para los filtros por periodo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingreso', function (Blueprint $table) {
            $table->date('date')->nullable()->after('amount');
        });

        DB::statement('UPDATE ingreso SET date = COALESCE(created_at::date, CURRENT_DATE) WHERE date IS NULL');

        Schema::table('ingreso', function (Blueprint $table) {
            $table->date('date')->nullable(false)->change();
            $table->index(['user_id', 'date', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::table('ingreso', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'date', 'estado']);
            $table->dropColumn('date');
        });
    }
};
