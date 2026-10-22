# MT 2.0 – Entrega 05: módulos financieros

Incluye Categorías, Gastos (incluye ahorro `save=1`), Ingresos, Presupuestos y un primer reporte mensual. CRUD con aislamiento de registros por usuario, validación, CSRF y desactivación lógica.

## Instalación
1. Realiza copia de seguridad del proyecto actual. Fusiona este paquete en la raíz de tu Laravel 12.
2. Configura PostgreSQL en `.env` (`DB_CONNECTION=pgsql`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`).
3. Añade al final de `routes/web.php`: `require __DIR__.'/mt2-finance.php';` manteniendo la línea existente de `mt2-dashboard.php`.
4. Ejecuta `php artisan migrate` **en una base de desarrollo vacía**, después de configurar Laravel y el sistema de login.
5. Las pantallas usan `auth` de Laravel. Antes de visitarlas debes tener login funcionando y un usuario Laravel autenticado. La maqueta `/mt2-preview` sigue siendo demostrativa y pública; NO conectes allí datos privados antes de protegerla.
6. Abre `/mt2/categorias`, crea una categoría y después prueba `/mt2/gastos`, `/mt2/ingresos`, `/mt2/presupuestos`, `/mt2/reportes`.

## Restricciones
- No importar todavía el SQL original. El dump contiene datos personales e IDs antiguos; necesita una importación dedicada y auditoría.
- `ingreso` se fecha con `created_at` (como en la tabla antigua); `gasto` utiliza `date`.
- `save=1` significa ahorro y se excluye del consumo.
- Los presupuestos actuales contienen importes y categoría, pero no periodo explícito. Se respeta su estructura; no se calcula una ejecución mensual sin definir esa regla.
- NO hay login entregado en este paquete. Para probar los CRUD es obligatorio disponer de autenticación Laravel funcional.
- La vista previa del dashboard sigue con cifras ficticias hasta la integración siguiente.
- No hay integración completa con roles, permisos específicos, exportaciones ni Render en esta entrega.
