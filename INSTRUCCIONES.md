# MT 2.0: Pasos 1 y 2 (seguridad + pruebas con Pest)

Todos los comandos son para **PowerShell**, dentro de `C:\xampp\htdocs\mt-2`,
con Docker Desktop encendido (`docker compose up -d`).

---

## Antes de empezar: respaldo

```powershell
cd C:\xampp\htdocs
Copy-Item -Recurse mt-2 mt-2-respaldo
cd mt-2
```

---

## Paso 1: Seguridad

### 1.1 Copiar los archivos

Copia el contenido de esta carpeta **encima** de tu proyecto (reemplazando),
**excepto este INSTRUCCIONES.md**. Luego borra los ejemplos de Laravel:

```powershell
Remove-Item tests\Feature\ExampleTest.php, tests\Unit\ExampleTest.php
```

Qué cambia:

| Archivo | Cambio |
|---|---|
| `routes/web.php` | Eliminadas `/test-session`, `/test-headers` y `/test-output` (exponían datos internos en producción). |
| `routes/mt2-dashboard.php` | El dashboard ahora exige `active.user`: una cuenta desactivada ya no puede verlo. Se agregó el nombre de ruta `mt2.dashboard`. |
| `LoginController.php` | Eliminado el `Log::info('MT2 LOGIN DEBUG')`, que guardaba el **ID de sesión** en los logs (con él alguien podría suplantar tu sesión). |
| `.gitignore` | Ignora `*.dump`, `*.sql` y `supervisord.pid`. |

### 1.2 Sacar los respaldos de Git (desde ahora)

```powershell
git rm --cached backups/mt2_antes_reinicio.dump backups/mt2_backup.dump supervisord.pid
```

Los archivos siguen en tu disco; Git solo deja de seguirlos.

### 1.3 Borrarlos del historial (IMPORTANTE)

Los dumps se subieron en el commit `1d7e8ee` a
`github.com/CarlosPasa/sistema-gastos`. Aunque los quites ahora, **siguen
descargables en el historial**. Para eliminarlos:

```powershell
# 1. Instalar git-filter-repo (requiere Python)
pip install git-filter-repo

# 2. Guardar la URL del remoto (filter-repo lo quita por seguridad)
git remote get-url origin

# 3. Reescribir el historial sin esos archivos
git filter-repo --invert-paths --path backups/ --path supervisord.pid --force

# 4. Volver a conectar y subir
git remote add origin https://github.com/CarlosPasa/sistema-gastos.git
git push --force --all
```

Comprueba que ya no aparecen:

```powershell
git log --all --oneline -- backups
```

(no debe mostrar nada).

**Si el repositorio es o fue público**, considera esos datos como expuestos:
- Cambia las contraseñas de los usuarios que estaban en esa base.
- Si el dump contenía datos de otras personas, avísales.
- GitHub puede mantener copias en caché; para purgarlas, abre una solicitud en
  https://support.github.com indicando el repositorio y el commit `1d7e8ee`.

> Haz el paso 1.3 **antes** del commit del paso 2, o después; da igual,
> pero hazlo antes de volver a hacer `git push` normal.

---

## Paso 2: Pruebas con Pest

### 2.1 Instalar Pest

```powershell
docker compose exec app composer remove --dev phpunit/phpunit
docker compose exec app composer require --dev pestphp/pest pestphp/pest-plugin-laravel --with-all-dependencies
```

(Pest instala su propia versión compatible de PHPUnit.)

### 2.2 Crear la base de datos de pruebas

Las pruebas usan **PostgreSQL**, igual que producción (tu código usa `ILIKE`,
que no existe en SQLite). Usan una base aparte, `mt2_testing`:

```powershell
docker compose exec db createdb -U mt2_user mt2_testing
```

### 2.3 Ejecutar

```powershell
docker compose exec app php artisan config:clear
docker compose exec app php artisan test
```

Resultado esperado:

```
Tests:    43 passed (122 assertions)
```

### Protección de tu base real

Las pruebas **borran y recrean todas las tablas** en cada prueba. Para que
nunca toquen tu base `mt2`:

1. `phpunit.xml` fuerza `DB_DATABASE=mt2_testing`, incluso si Docker define
   `DB_DATABASE=mt2` (tu `compose.yaml` lo hace).
2. `tests/TestCase.php` revisa, **antes** de borrar nada, que la base termine
   en `_testing`. Si no (por ejemplo, porque quedó un `php artisan config:cache`),
   se detiene con un mensaje claro.

Ambas protecciones se verificaron: con la configuración apuntando a `mt2`,
las pruebas se detienen y los datos quedan intactos.

### Qué cubren las 43 pruebas

| Archivo | Qué protege |
|---|---|
| `AuthTest` | Login, cuentas desactivadas (incluso con sesión abierta), registro que nunca acepta `role=admin`, contraseña mínima, acceso de administrador, rutas de depuración eliminadas. |
| `UserIsolationTest` | Un usuario **no puede** ver, editar, desactivar ni usar categorías de otro, aunque adivine el ID. El reporte no mezcla montos ajenos. |
| `FinanceCrudTest` | Montos inválidos (0, negativos, 3 decimales, texto), categorías inactivas, desactivar/reactivar, filtro por mes, búsqueda con `%` literal. |
| `BudgetRecurrenceTest` | Las vigencias: un cambio aplica desde el mes elegido sin reescribir meses anteriores; se rechazan cambios retroactivos; el consumo cuenta solo gastos del mes (no ahorros). |
| `DashboardTest` | Ingresos, gastos, ahorro y disponible; el último día del mes se incluye y el 1.º del siguiente no; registros desactivados ignorados; agrupación por categoría; 6 meses de evolución. |

Se comprobó además que las pruebas **detectan errores reales**: al quitar a
propósito el filtro `user_id` del controlador, fallan 4 pruebas de aislamiento.

---

## Commit

```powershell
git add -A
git commit -m "Seguridad: quitar rutas de depuración y log de sesión; pruebas con Pest

- Elimina /test-session, /test-headers y /test-output
- Exige usuario activo en el dashboard
- Deja de registrar el ID de sesión en el login
- Saca los respaldos .dump y supervisord.pid del repositorio
- Agrega Pest con 43 pruebas sobre PostgreSQL: autenticación,
  aislamiento entre usuarios, CRUD financiero, vigencias de
  presupuesto y totales del dashboard"
git push
```

---

## Siguiente: Paso 3

GitHub Actions que corra estas pruebas (con PostgreSQL) en cada `push` y
despliegue en Render solo si todo pasa.
