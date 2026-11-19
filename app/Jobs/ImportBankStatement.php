<?php

namespace App\Jobs;

use App\Models\StatementImport;
use App\Statements\StatementImporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Procesa un extracto en segundo plano para que la subida responda al
 * instante aunque el archivo tenga miles de filas.
 *
 * Reintentar es seguro: el importador no duplica movimientos.
 */
class ImportBankStatement implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /** Segundos de espera entre reintentos. */
    public array $backoff = [10, 60];

    public function __construct(public StatementImport $import) {}

    public function handle(StatementImporter $importer): void
    {
        $importer->import($this->import);
    }

    /** Agotados los reintentos: el usuario ve el error en la pantalla. */
    public function failed(?Throwable $exception): void
    {
        app(StatementImporter::class)->fail(
            $this->import->fresh() ?? $this->import,
            'No se pudo procesar el archivo. Inténtalo de nuevo más tarde.',
        );

        report($exception);
    }
}
