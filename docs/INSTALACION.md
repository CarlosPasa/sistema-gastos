# MT 2.0 — Entrega 08: presupuestos recurrentes con vigencias

## Qué hace y qué NO hace

- Cada presupuesto representa un límite que se repite mensualmente.
- Un cambio de monto desde 2026-11 afecta noviembre de 2026 y meses posteriores; octubre conserva su vigencia anterior.
- Los presupuestos que ya existen reciben una vigencia inicial en el **mes de su `created_at`** con el importe **actual**. No hay manera de reconstruir importes históricos que fueron editados antes de esta implementación.
- No modifica las tablas ni los registros `gasto`, `ingreso`, `categoria` o los datos de `presupuesto` durante la migración; solo crea y rellena `presupuesto_vigencia`.
- Las desactivaciones actuales todavía se aplican globalmente; **esta entrega historiza importes, no el estado activo/inactivo ni los cambios de categoría**.
- Si existen varios presupuestos activos de una misma categoría, cada uno muestra el mismo consumo de categoría. Conviene definir un presupuesto por categoría o ajustar el modelo de asignaciones antes de sumar sus valores.

## Paso 0: respaldo

En PowerShell, desde la raíz del proyecto, crea un dump **antes de migrar**:

```powershell
docker compose exec -T db pg_dump -U mt2_user -d mt2 > mt2_backup_pre_entrega08.sql
```

No subas el respaldo a Git.

## Paso 1: copiar archivos nuevos

Copia las carpetas `app/`, `database/` y `resources/` de este ZIP a `C:\xampp\htdocs\mt-2`. No reemplazan archivos existentes.

## Paso 2: adaptar solo los presupuestos en `FinanceController`

En `app/Http/Controllers/FinanceController.php`, añade arriba:

```php
use App\Services\BudgetRecurrenceService;
```

En `store()`, **después** de `$data['updated_at']=now();` y **antes** de `DB::table(...)->insert($data);`, añade:

```php
if ($modulo === 'presupuestos') {
    $request->validate(['effective_month' => ['required','date_format:Y-m']]);
    DB::transaction(function () use ($data, $request) {
        $id = DB::table('presupuesto')->insertGetId($data);
        app(BudgetRecurrenceService::class)->initialize(
            (int) $id, (string) $data['amount'], $request->input('effective_month')
        );
    });
    return redirect()->route('mt2.finance.index', 'presupuestos')
        ->with('ok', 'Presupuesto mensual creado.');
}
```

En `update()`, reemplaza **únicamente el cuerpo del método** por:

```php
{
    $current = $this->row($modulo, $id);
    $data = $this->validated($request, $modulo);
    $data['updated_at'] = now();

    if ($modulo === 'presupuestos') {
        $request->validate(['effective_month' => ['required','date_format:Y-m']]);
        $last = DB::table('presupuesto_vigencia')
            ->where('presupuesto_id', $id)->orderByDesc('effective_month')->first();
        if ($last && $request->input('effective_month') < substr($last->effective_month, 0, 7)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'effective_month' => 'Hay una vigencia posterior. Elige ese mes o uno posterior.'
            ]);
        }
        // Don't silently change historical category assignment.
        if ((int) $current->id_category !== (int) $data['id_category']) {
            return back()->withInput()->withErrors([
                'id_category' => 'No se puede cambiar la categoría de un presupuesto existente con historial. Crea otro presupuesto.'
            ]);
        }
        DB::transaction(function () use ($data, $request, $id) {
            DB::table('presupuesto')->where('id', $id)->where('user_id', auth()->id())
                ->update(['description' => $data['description'], 'updated_at' => now()]);
            app(BudgetRecurrenceService::class)->change(
                $id, (int) auth()->id(), (string) $data['amount'], $request->input('effective_month')
            );
        });
        return redirect()->route('mt2.finance.index', 'presupuestos')
            ->with('ok', 'Nueva vigencia mensual guardada.');
    }

    DB::table($this->config($modulo)['table'])
        ->where('user_id', auth()->id())->where('id', $id)->update($data);
    return redirect()->route('mt2.finance.index', $modulo)->with('ok', 'Registro actualizado.');
}
```

**Observación:** la columna `presupuesto.amount` conserva el último valor como referencia para el CRUD. Los cálculos históricos se hacen **siempre** contra `presupuesto_vigencia`, nunca contra ese campo directamente.

## Paso 3: modificar SOLO el formulario de presupuestos

En `resources/views/finance/form.blade.php`, agrega dentro del `<form>` (después del input de Monto, por ejemplo):

```blade
@if($modulo === 'presupuestos')
    <label>Vigente desde (año y mes)
        <input type="month" name="effective_month" required
            value="{{ old('effective_month', now()->format('Y-m')) }}">
    </label>
    <small>Al editar el límite, se mantendrán los importes de los meses anteriores.</small>
@endif
```

En edición, el mes predeterminado es el **actual**, y se puede cambiar a uno posterior. Si un presupuesto tiene una vigencia posterior al mes actual, escoge ese mes o uno posterior.

## Paso 4: añadir al dashboard (Entrega 07)

En `app/Http/Controllers/DashboardController.php`, añade:

```php
use App\Services\BudgetRecurrenceService;
```

Antes del `return view('dashboard.index', ...)` añade:

```php
$recurringBudgets = app(BudgetRecurrenceService::class)->summary((int) $userId, $month);
```

Agrega `'recurringBudgets'` al `compact(...)` del `return view`.

En `resources/views/dashboard/index.blade.php`, sustituye **solo** la tarjeta que contiene el título `Presupuestos recurrentes` y el texto `Próxima etapa` por:

```blade
@include('dashboard.partials.recurring-budgets')
```

No sustituyas el resto del dashboard ni el `layouts.mt2`.

## Paso 5: ejecutar migración y validar

```powershell
docker compose exec app php artisan migrate --pretend
docker compose exec app php artisan migrate
docker compose exec app php artisan optimize:clear
docker compose exec db psql -U mt2_user -d mt2 -P pager=off -c "SELECT presupuesto_id, amount, effective_month FROM presupuesto_vigencia ORDER BY presupuesto_id, effective_month;"
```

**Prueba:** crea presupuesto Alimentación S/500 con vigencia 2026-10; cambia S/600 desde 2026-11. El dashboard octubre debe mostrar S/500, noviembre S/600. Comprueba con valores reales en tu entorno; no hace falta insertar datos artificiales si ya tienes presupuestos.

### Consideraciones pendientes

- Historial de activación y desactivación: actualmente `estado` se aplica a todos los meses; no está versionado.
- Historial de cambios de descripción: solo se conserva la descripción actual.
- Límite por categoría: si hay varios presupuestos asociados a una misma categoría, puede duplicarse visualmente el consumo. Acordar regla antes de consolidarlos.
- Bloquear cambios retroactivos de importe en meses anteriores a la vigencia más reciente (incluido aquí).
