<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ResetMt2Data extends Command
{
    protected $signature = 'mt2:reset-data {--apply : Ejecutar la limpieza}';

    protected $description = 'Reinicia datos de MT 2.0 conservando el administrador principal';

    public function handle(): int
    {
        $tablas = [
            'mt2_legacy_import_maps',
            'presupuesto_vigencia',
            'gasto',
            'ingreso',
            'presupuesto',
            'categoria',
        ];

        $admin = \Illuminate\Support\Facades\DB::table('users')
            ->where('email', 'admin@admin.com')
            ->first();

        if (!$admin) {
            $this->error('No se encontró el usuario administrador.');
            return self::FAILURE;
        }

        $this->info('Se vaciarán las siguientes tablas:');

        foreach ($tablas as $tabla) {
            $total = \Illuminate\Support\Facades\DB::table($tabla)->count();
            $this->line(" - {$tabla}: {$total} registros");
        }

        $this->warn('Se conservarán todos los usuarios y sus configuraciones.');

        if (!$this->option('apply')) {
            $this->info('SIMULACIÓN: no se modificó ninguna tabla.');
            return self::SUCCESS;
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($tablas) {
            $lista = implode(', ', $tablas);

            \Illuminate\Support\Facades\DB::statement(
                "TRUNCATE TABLE {$lista} RESTART IDENTITY"
            );
        });

        $this->info('Datos financieros reiniciados correctamente.');

        return self::SUCCESS;
    }
}
