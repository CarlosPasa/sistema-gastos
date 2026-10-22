# MT 2.0 — Paquete de revisión e importación histórica (usuario antiguo 1)

**Destino exclusivo:** cuenta `admin@admin.com`. **Origen:** respaldo `if0_38959097_dev(1).sql` (13/10/2026). No incluye contraseñas ni tablas operativas del sistema anterior.

## Datos verificados

- 10 categorías pertenecientes al usuario antiguo 1; la undécima categoría original corresponde al usuario 2 y se excluye.
- 657 gastos (todos del usuario antiguo 1), 438 con `save=0` y 219 con `save=1`.
- 30 ingresos del usuario antiguo 1.
- 37 presupuestos del usuario antiguo 1: **no se importan todavía**; requerirán una migración especial para vigencias y evitar presupuestos activos duplicados.
- Gasto antiguo 31 «Hospedaje» referencia categoría 0, inexistente.
- Los gastos van del 01/03/2024 al 01/08/2026. `save=1` aparece tanto en consumos como en ahorros: no asumir que todo `1` era ahorro.

## Archivos

- `origen_usuario1.json`: filas filtradas por el usuario antiguo 1; preservar como confidencial.
- `revision_gastos.csv`: decisiones por movimiento (`clasificacion=consumo|ahorro`), compatible con Excel. 219 están en `REVISAR` y deben resolverse individualmente.
- `revision_categorias.csv`: nombres de destino por categoría (editables). Completar `categoria_destino` de gasto 31, que no tiene categoría antigua.
- `app/Console/Commands/ImportMt2Legacy.php`: comando Laravel que **simula por defecto**, y solo inserta con `--apply` tras comprobar revisión y colisiones con datos existentes.
- `database/migrations/2026_10_20_120000_create_mt2_legacy_import_maps_table.php`: tabla de trazabilidad origen→destino para evitar importar el mismo ID dos veces.

## Instalación en Windows y Docker

1. Extrae este ZIP al proyecto `C:\xampp\htdocs\mt-2` de forma que las rutas `app/`, `database/` queden fusionadas. Coloca los archivos JSON y CSV en `storage/app/imports/` (créala).
2. **Antes de ejecutar migraciones**, guarda un respaldo de PostgreSQL:
   `docker compose exec -T db pg_dump -U mt2_user -d mt2 > respaldo_mt2_antes_importacion.sql`
3. Verifica que la migración nueva no exista ya. Ejecuta `docker compose exec app php artisan migrate`.
4. Completa `revision_gastos.csv`: en cada fila `REVISAR` reemplaza por `consumo` o `ahorro`, según su significado real. No cambies IDs, fechas, importes, `legacy_save`, descripciones ni `categoria_id_antigua`. Para gasto 31 rellena `categoria_destino` con categoría existente o nuevo nombre.
5. Revisa `revision_categorias.csv`. `categoria_destino` es el nombre a reutilizar o crear. No cambies `legacy_id` ni `nombre_antiguo`.
6. Simula con `docker compose exec app php artisan mt2:import-legacy`. Reporta pendientes, categorías no resueltas o posibles duplicados en la base actual. **No modifica datos**.
7. Solo si la simulación es satisfactoria, ejecuta `docker compose exec app php artisan mt2:import-legacy --apply`.
8. Valida cantidades, importes, clasificación por mes y fechas; compara dashboard, gastos e ingresos de MT 2.0 con el backup original.

## Precauciones

- No ejecutar el SQL de MariaDB directamente en PostgreSQL: contiene sentencias destructivas `DROP TABLE`.
- La tabla `ingreso` actual utiliza `created_at` para el periodo, por lo que el comando preserva los timestamps antiguos. Recomendación futura: incorporar `income_date` de negocio; el backup no distingue fecha de movimiento y fecha de creación.
- El CSV refleja la clasificación **pendiente**, no un dictamen sobre cuáles son ahorros reales.
- El importador no añade ni modifica usuarios, sesiones, ajustes, roles, ni presupuestos existentes.
- El importador puede crear categorías al importar, pero no modifica ni renombra las existentes.
- Ante una coincidencia exacta con un gasto existente sin mapa de trazabilidad, el comando **se detiene**, para revisión manual. No presupone que operaciones iguales sean duplicados.
- Reejecutar después de importar omite IDs mapeados; conserva movimientos previamente migrados.
- `--apply` es para entorno local y después de respaldar y revisar. No subir estos archivos con datos personales a un repositorio público.
