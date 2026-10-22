<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportMt2Legacy extends Command
{
    protected $signature = 'mt2:import-legacy {--apply : Importar realmente (sin esta opción solo valida)}';
    protected $description = 'Valida y migra el historial del usuario antiguo ID 1 a admin@admin.com';

    private function csv(string $filename): array
    {
        $path = storage_path('app/imports/' . $filename);
        if (!is_file($path)) {
            throw new RuntimeException('No existe ' . $path);
        }
        $f = fopen($path, 'rb');
        if ($f === false) {
            throw new RuntimeException('No se pudo abrir ' . $path);
        }
        try {
            $header = fgetcsv($f);
            if (!$header) throw new RuntimeException('CSV vacío: ' . $filename);
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
            $rows = [];
            while (($values = fgetcsv($f)) !== false) {
                if ($values === [null]) continue;
                if (count($values) !== count($header)) {
                    throw new RuntimeException('Número de columnas inválido en ' . $filename);
                }
                $row = array_combine($header, $values);
                $id = (int) ($row['legacy_id'] ?? 0);
                if ($id <= 0 || isset($rows[$id])) {
                    throw new RuntimeException('ID repetido o inválido en ' . $filename . ': ' . $id);
                }
                $rows[$id] = $row;
            }
            return $rows;
        } finally {
            fclose($f);
        }
    }

    private function mapped(int $userId, string $entity, int $oldId): ?int
    {
        $new = DB::table('mt2_legacy_import_maps')
            ->where('user_id', $userId)->where('entity', $entity)
            ->where('legacy_id', $oldId)->value('new_id');
        return $new === null ? null : (int) $new;
    }

    private function addMap(int $userId, string $entity, int $oldId, int $newId): void
    {
        DB::table('mt2_legacy_import_maps')->insert([
            'user_id' => $userId,
            'entity' => $entity,
            'legacy_id' => $oldId,
            'new_id' => $newId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function handle(): int
    {
        $path = storage_path('app/imports/origen_usuario1.json');
        if (!is_file($path)) {
            $this->error('Falta storage/app/imports/origen_usuario1.json');
            return self::FAILURE;
        }
        try {
            $source = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $categories = $this->csv('revision_categorias.csv');
            $expenses = $this->csv('revision_gastos.csv');
            foreach (['categoria', 'gasto', 'ingreso', 'presupuesto'] as $key) {
                if (!isset($source[$key]) || !is_array($source[$key])) {
                    throw new RuntimeException('Sección faltante: ' . $key);
                }
            }
            $user = DB::table('users')->where('email', 'admin@admin.com')->first();
            if (!$user) throw new RuntimeException('No existe el destinatario admin@admin.com');
            $userId = (int) $user->id;

            $sourceCategories = collect($source['categoria'])->keyBy('id');
            $sourceExpenses = collect($source['gasto'])->keyBy('id');
            if (
                count($categories) !== $sourceCategories->count() ||
                count($expenses) !== $sourceExpenses->count()
            ) {
                throw new RuntimeException('Los CSV de revisión no contienen todos los IDs originales.');
            }
            $issues = [];
            $categoryNames = [];
            foreach ($sourceCategories as $id => $row) {
                if ((int) $row['user_id'] !== 1) throw new RuntimeException('Origen inesperado');
                $review = $categories[$id] ?? null;
                if (!$review || trim($review['nombre_antiguo']) !== trim($row['description'])) {
                    $issues[] = 'Categoría ' . $id . ': fila de revisión no coincide.';
                    continue;
                }
                $dest = trim($review['categoria_destino'] ?? '');
                if ($dest === '' || mb_strlen($dest) > 100) {
                    $issues[] = 'Categoría ' . $id . ': completa categoria_destino (máximo 100).';
                } else $categoryNames[$id] = $dest;
            }
            $decisions = [];
            foreach ($sourceExpenses as $id => $row) {
                if ((int) $row['user_id'] !== 1) throw new RuntimeException('Origen inesperado');
                $review = $expenses[$id] ?? null;
                if (
                    !$review || (string)$review['description'] !== (string)$row['description'] ||
                    (string)$review['date'] !== (string)$row['date'] ||
                    (int)$review['legacy_save'] !== (int)$row['save'] ||
                    (int)$review['categoria_id_antigua'] !== (int)$row['id_category'] ||
                    abs((float)$review['amount'] - (float)$row['amount']) > 0.001
                ) {
                    $issues[] = 'Gasto ' . $id . ': fila de revisión alterada o ausente.';
                    continue;
                }
                $type = mb_strtolower(trim($review['clasificacion']));
                if (!in_array($type, ['consumo', 'ahorro'], true)) {
                    $issues[] = 'Gasto ' . $id . ': clasificacion pendiente (consumo / ahorro).';
                }
                $oldCat = (int)$row['id_category'];
                $destName = $oldCat === 0
                    ? trim($review['categoria_destino'] ?? '')
                    : ($categoryNames[$oldCat] ?? '');
                if ($destName === '' || mb_strlen($destName) > 100) {
                    $issues[] = 'Gasto ' . $id . ': categoría destino sin resolver.';
                }
                $decisions[$id] = ['save' => $type === 'ahorro', 'category_name' => $destName];
            }
            $this->info('Origen usuario 1: ' . $sourceCategories->count() . ' categorías; ' .
                $sourceExpenses->count() . ' gastos; ' . count($source['ingreso']) . ' ingresos; ' .
                count($source['presupuesto']) . ' presupuestos (estos últimos NO se importan).');
            if ($issues) {
                $this->warn(count($issues) . ' incidencias de revisión. Primeras 25:');
                foreach (array_slice($issues, 0, 25) as $issue) $this->line(' - ' . $issue);
                $this->error('No se ejecuta ninguna inserción hasta resolver todas las incidencias.');
                return self::FAILURE;
            }
            // Detectar coincidencias contra movimientos YA existentes antes de permitir insertar.
            // Estos podrían ser datos ingresados manualmente o importados sin trazabilidad.
            $matches = [];
            $existingNames = DB::table('categoria')->where('user_id', $userId)
                ->get(['id', 'description'])->groupBy(fn($r) => mb_strtolower(trim($r->description)));
            foreach ($sourceExpenses as $id => $row) {
                if ($this->mapped($userId, 'gasto', (int)$id)) continue;
                $destName = mb_strtolower(trim($decisions[$id]['category_name']));
                foreach ($existingNames->get($destName, collect()) as $cat) {
                    $exists = DB::table('gasto')->where('user_id', $userId)
                        ->where('id_category', $cat->id)
                        ->where('date', $row['date'])
                        ->where('description', $row['description'])
                        ->where('amount', $row['amount'])
                        ->where('save', $decisions[$id]['save'])->exists();
                    if ($exists) {
                        $matches[] = $id;
                        break;
                    }
                }
            }
            if ($matches) {
                $this->error('Posibles duplicados con registros actuales (IDs antiguos): ' . implode(', ', array_slice($matches, 0, 30)));
                $this->error('No se importará nada. Revisar manualmente estas coincidencias antes de continuar.');
                return self::FAILURE;
            }
            // Detectar categorías ambiguas antes de procesar.
            foreach (array_unique(array_values($categoryNames)) as $name) {
                if ($existingNames->get(mb_strtolower(trim($name)), collect())->count() > 1) {
                    $this->error('Existen varias categorías destino con el mismo nombre: ' . $name);
                    return self::FAILURE;
                }
            }
            if (!$this->option('apply')) {
                $this->info('SIMULACIÓN OK: validación y duplicados correctos; no se modificó PostgreSQL.');
                $this->warn('Revisa que las decisiones de ahorro/consumo sean correctas antes de --apply.');
                return self::SUCCESS;
            }
            DB::transaction(function () use ($source, $userId, $sourceCategories, $sourceExpenses, $categoryNames, $decisions) {
                $nameToId = [];
                foreach ($sourceCategories as $oldId => $row) {
                    $name = $categoryNames[$oldId];
                    $key = mb_strtolower(trim($name));
                    if (isset($nameToId[$key])) {
                        $destId = $nameToId[$key];
                    } else {
                        $existing = DB::table('categoria')->where('user_id', $userId)
                            ->whereRaw('LOWER(TRIM(description)) = ?', [$key])->first();
                        $destId = $existing ? (int)$existing->id : (int) DB::table('categoria')->insertGetId([
                            'user_id' => $userId,
                            'description' => $name,
                            'estado' => true,
                            'created_at' => $this->normalizarFecha(
                                $gasto['created_at'] ?? null
                            ),
                            'updated_at' => $this->normalizarFecha(
                                $gasto['updated_at'] ?? null,
                                $gasto['created_at'] ?? null
                            ),
                        ]);
                        $nameToId[$key] = $destId;
                    }
                    if (!$this->mapped($userId, 'categoria', (int)$oldId)) {
                        $this->addMap($userId, 'categoria', (int)$oldId, $destId);
                    }
                }
                $extraNames = [];
                foreach ($sourceExpenses as $oldId => $row) {
                    if ($this->mapped($userId, 'gasto', (int)$oldId)) continue;
                    $name = $decisions[$oldId]['category_name'];
                    $key = mb_strtolower(trim($name));
                    if (isset($nameToId[$key])) $catId = $nameToId[$key];
                    elseif (isset($extraNames[$key])) $catId = $extraNames[$key];
                    else {
                        $existing = DB::table('categoria')->where('user_id', $userId)
                            ->whereRaw('LOWER(TRIM(description)) = ?', [$key])->first();
                        $catId = $existing ? (int)$existing->id : (int)DB::table('categoria')->insertGetId([
                            'user_id' => $userId,
                            'description' => $name,
                            'estado' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $extraNames[$key] = $catId;
                    }
                    $newId = (int)DB::table('gasto')->insertGetId([
                        'description' => $row['description'],
                        'amount' => $row['amount'],
                        'date' => $row['date'],
                        'save' => $decisions[$oldId]['save'],
                        'id_category' => $catId,
                        'estado' => (bool)$row['estado'],
                        'user_id' => $userId,
                        'created_at' => $this->normalizarFecha(
                            $gasto['created_at'] ?? null
                        ),
                        'updated_at' => $this->normalizarFecha(
                            $gasto['updated_at'] ?? null,
                            $gasto['created_at'] ?? null
                        ),
                    ]);
                    $this->addMap($userId, 'gasto', (int)$oldId, $newId);
                }
                foreach ($source['ingreso'] as $row) {
                    if ((int)$row['user_id'] !== 1) throw new RuntimeException('Origen inesperado en ingreso');
                    $oldId = (int)$row['id'];
                    if ($this->mapped($userId, 'ingreso', $oldId)) continue;
                    $existing = DB::table('ingreso')->where('user_id', $userId)
                        ->where('description', $row['description'])->where('amount', $row['amount'])
                        ->where('created_at', $row['created_at'])->exists();
                    if ($existing) throw new RuntimeException('Posible ingreso duplicado sin mapear: ' . $oldId);
                    $newId = (int)DB::table('ingreso')->insertGetId([
                        'description' => $row['description'],
                        'amount' => $row['amount'],
                        'estado' => (bool)$row['estado'],
                        'user_id' => $userId,
                        'created_at' => $row['created_at'],
                        'updated_at' => $row['updated_at'],
                    ]);
                    $this->addMap($userId, 'ingreso', $oldId, $newId);
                }
            });
            $this->info('Importación completada. Los presupuestos antiguos permanecen pendientes.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }

    private function normalizarFecha(
        ?string $fecha,
        ?string $alternativa = null
    ): string {
        foreach ([$fecha, $alternativa] as $valor) {
            $valor = trim((string) $valor);

            if (
                $valor !== '' &&
                $valor !== '0000-00-00' &&
                $valor !== '0000-00-00 00:00:00'
            ) {
                try {
                    $fechaValida = \Carbon\Carbon::createFromFormat(
                        'Y-m-d H:i:s',
                        $valor
                    );

                    if (
                        $fechaValida &&
                        $fechaValida->format('Y-m-d H:i:s') === $valor
                    ) {
                        return $valor;
                    }
                } catch (\Throwable $e) {
                    // Intentar con la fecha alternativa.
                }
            }
        }

        return now()->format('Y-m-d H:i:s');
    }

}
