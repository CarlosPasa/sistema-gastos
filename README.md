# MT 2.0 · Sistema de gastos personales

[![Tests](https://github.com/CarlosPasa/sistema-gastos/actions/workflows/tests.yml/badge.svg)](https://github.com/CarlosPasa/sistema-gastos/actions/workflows/tests.yml)

Aplicación web para registrar ingresos, gastos y ahorros, controlar
presupuestos mensuales por categoría e importar extractos bancarios. La uso
para mis propias finanzas; está desplegada en Render con PostgreSQL.

**Stack:** PHP 8.3 · Laravel 12 · PostgreSQL 17 · Blade · Pest · Docker
(Nginx + PHP-FPM + worker de colas con Supervisor) · GitHub Actions · Render

---

## Qué hace

- **Ingresos, gastos y ahorros** con categorías. Un gasto puede marcarse como
  ahorro, para separar lo que se consume de lo que se guarda.
- **Presupuestos mensuales recurrentes** con historial: cambiar el límite
  afecta desde el mes elegido sin reescribir los meses anteriores.
- **Dashboard**: disponible del mes, evolución de 6 meses, consumo por
  categoría, avance de presupuestos y últimos movimientos.
- **Reporte mensual** con totales y distribución por categoría.
- **Importación de extractos CSV** en segundo plano, sin duplicar movimientos
  aunque se suba el mismo archivo dos veces.
- **Multiusuario** con roles (administrador / usuario) y cuentas desactivables.
  Cada persona solo ve y modifica sus propios datos.

## Arquitectura

```
Petición HTTP
   │
   ├─ Middleware: auth · active.user · throttle
   ├─ Ruta: {modulo} → enum FinanceModule (404 si no existe)
   │
   ├─ Controlador (delgado): busca → autoriza → valida → delega
   │     ├─ Policy: FinanceRecordPolicy ...... ¿el registro es tuyo? (404 si no)
   │     ├─ Form Request por módulo ........... ¿los datos son válidos?
   │     └─ Servicio ........................... reglas de negocio
   │           ├─ BudgetRecurrenceService ..... vigencias de presupuestos
   │           ├─ DashboardService ............ agregados del dashboard
   │           └─ StatementImporter ........... extractos → movimientos
   │
   └─ Modelos Eloquent + scopes (active, consumption, savings, inPeriod, search)

Subida de extracto ─► statement_imports (pending) ─► cola (database)
                                                      │
                          worker (Supervisor) ◄───────┘
                              └─ ImportBankStatement → StatementImporter
                                    ├─ StatementParser (GenericCsvParser)
                                    └─ INSERT … ON CONFLICT DO NOTHING
```

| Carpeta | Contenido |
|---|---|
| `app/Models` | `Category`, `Expense`, `Income`, `Budget`, `BudgetVersion`, `StatementImport` |
| `app/Models/Concerns/BelongsToUser` | Dueño, estado activo/inactivo y búsqueda, comunes a los registros financieros |
| `app/Enums` | `FinanceModule` (módulos de finanzas), `ImportStatus` |
| `app/Http/Requests/Finance` | Validación por módulo |
| `app/Policies` | `FinanceRecordPolicy` |
| `app/Services` | Presupuestos y dashboard |
| `app/Statements` | Lectura e importación de extractos |
| `app/Jobs` | `ImportBankStatement` |
| `tests/Feature`, `tests/Unit` | 108 pruebas con Pest sobre PostgreSQL |

> Las tablas están en español (`gasto`, `ingreso`, `categoria`,
> `presupuesto`) porque vienen de la primera versión del sistema; el código
> está en inglés. Los modelos declaran `$table` y las relaciones indican sus
> llaves (`id_category`). Renombrar las tablas en producción no aportaba valor
> frente a su riesgo.

---

## Decisiones de diseño

Cada decisión indica qué problema resuelve, qué alternativa descarté y qué
costo acepté.

### 1. Las pruebas usan PostgreSQL, no SQLite en memoria

**Problema:** el código usa `ILIKE`, `to_char()` y subconsultas propias de
PostgreSQL. Con SQLite, las pruebas pasaban o fallaban por razones distintas
a las de producción.
**Decisión:** las pruebas corren contra una base `mt2_testing` en PostgreSQL,
igual en local (Docker) y en CI (servicio de GitHub Actions).
**Protección:** `phpunit.xml` fuerza `DB_DATABASE=mt2_testing` y `APP_ENV=testing`
incluso si el contenedor define otras variables, y `tests/TestCase.php` se
niega a borrar tablas si la base no termina en `_testing` (por ejemplo, con un
`config:cache` olvidado).
**Costo:** las pruebas necesitan un PostgreSQL levantado.

### 2. Un registro ajeno responde 404, no 403

**Decisión:** `FinanceRecordPolicy` usa `Response::denyAsNotFound()`. Pedir el
gasto 123 de otra persona da el mismo resultado que pedir uno que no existe.
**Por qué:** un 403 confirma que el ID existe y permite enumerar registros.
**Verificado:** si la Policy deja pasar a cualquiera o responde 403, fallan 4
pruebas de aislamiento.

### 3. Un controlador para cuatro módulos, guiado por un enum

**Contexto:** categorías, gastos, ingresos y presupuestos comparten las mismas
pantallas genéricas (`finance/index`, `finance/form`).
**Decisión:** un `FinanceController` delgado + `enum FinanceModule`, que indica
el modelo, la relación, el Form Request y la columna de orden de cada módulo.
Laravel resuelve el enum desde la URL.
**Descartado:** cuatro controladores de recurso. Es lo más común en Laravel,
pero obligaba a reescribir las vistas en el mismo cambio. Cambié una capa a la
vez; si un módulo empieza a crecer distinto a los demás, se separa.

### 4. Presupuestos con historial de vigencias

**Problema:** si se edita el límite de un presupuesto, los meses pasados no
deben cambiar.
**Decisión:** cada cambio crea una `BudgetVersion` (`presupuesto_vigencia`)
con el mes desde el que rige. El límite de un mes es la última versión no
posterior a ese mes. No se permite registrar un cambio anterior a la última
vigencia, para no sobrescribir decisiones posteriores.

### 5. Desactivar en lugar de borrar

Los registros financieros tienen `estado` y se desactivan; no se eliminan. Una
categoría con gastos o presupuestos no se puede desactivar. `estado` y
`user_id` no son asignables en masa: el dueño se fija al crear desde la
relación (`$user->expenses()->create(...)`).

### 6. Dashboard con un número fijo de consultas

**Problema:** la gráfica de 6 meses hacía 3 consultas por mes, y los totales
del mes se pedían aparte aunque son el último punto de la serie: 29 consultas
por carga.
**Decisión:** dos consultas agregadas (`GROUP BY to_char(date, 'YYYY-MM')`)
traen toda la serie; los totales salen de ella.
**Resultado:** 9 consultas fijas. Con 10.000 gastos, de 21 ms a 13 ms en
local (en Render pesa más, porque cada consulta cruza la red).
**Regresión:** una prueba falla si el dashboard supera 10 consultas.

### 7. Ingresos con fecha propia, migrados sin perder datos

**Problema:** el mes de un ingreso salía de `created_at`; no se podía registrar
hoy el sueldo del mes pasado.
**Decisión:** migración en tres pasos: columna opcional → relleno con
`created_at::date` → obligatoria con índice. Cada ingreso existente sigue en
el mismo mes en el que se mostraba. Una prueba ejecuta la migración sobre datos
antiguos y comprueba que no se pierde ninguno.

### 8. Importación de extractos en una cola

- **En cola, no en la petición:** un extracto puede tener miles de filas. La
  subida responde al instante y el worker procesa el archivo; la pantalla
  muestra el estado y se refresca sola.
- **El CSV se guarda en la base, no en disco:** el disco de Render se borra en
  cada deploy; si el archivo estuviera en disco, un deploy entre la subida y el
  procesamiento lo perdería. El contenido se borra al terminar: no conservo
  extractos.
- **Sin duplicados:** cada movimiento importado lleva una huella SHA-256
  (tipo, fecha, monto, descripción y número de aparición dentro del archivo).
  Un índice único `(user_id, import_fingerprint)` con `INSERT … ON CONFLICT DO
  NOTHING` hace que subir el mismo extracto dos veces, reintentar el job o
  procesar dos importaciones a la vez no duplique nada. El número de aparición
  conserva dos movimientos idénticos legítimos (dos cafés el mismo día).
- **Filas inválidas no bloquean el resto:** se importan las válidas y se
  listan las que fallaron, con su número de fila.
- **Formato intercambiable:** `StatementParser` es una interfaz;
  `GenericCsvParser` lee `fecha, descripcion, monto`. Para el CSV propio de un
  banco se agrega otra implementación sin tocar el importador.
- **Montos en céntimos enteros** al leer el archivo, para evitar errores de
  redondeo con decimales.
- **Codificación:** Excel en Windows exporta CSV en Windows-1252; se convierte
  a UTF-8 para que las tildes y la ñ lleguen bien a PostgreSQL.

### 9. El worker vive en el mismo contenedor que la web

**Decisión:** Supervisor corre Nginx, PHP-FPM y `queue:work` en un solo
contenedor, con la cola en la tabla `jobs` de PostgreSQL.
**Descartado:** un servicio worker aparte en Render y Redis. Duplican el costo
y la operación para un volumen que no los necesita.
**Costo aceptado:** la web y el worker escalan juntos. Si el plan de Render
suspende el servicio por inactividad, los jobs esperan hasta que despierte.

### 10. Render despliega solo si CI pasa

GitHub Actions corre las 108 pruebas contra PostgreSQL en cada push y pull
request. Render está en **Auto-Deploy: After CI Checks Pass**, así que un
commit con pruebas fallidas no llega a producción. No hace falta un deploy hook
ni guardar secretos en GitHub.

---

## Correr el proyecto en local

Requisitos: Docker Desktop.

```bash
cp .env.example .env            # ajusta DB_* según compose.yaml
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

Abre http://localhost:8000 y crea una cuenta.

## Pruebas

```bash
docker compose exec db createdb -U mt2_user mt2_testing   # solo la primera vez
docker compose exec app php artisan config:clear
docker compose exec app php artisan test
```

| Archivo | Qué cubre |
|---|---|
| `AuthTest` | Login, registro, cuentas desactivadas, permisos de administrador |
| `UserIsolationTest` | Nadie ve ni modifica datos ajenos |
| `FinanceCrudTest`, `FinanceScreensTest` | Validaciones, filtros, paginación, pantallas |
| `BudgetRecurrenceTest` | Vigencias de presupuestos |
| `DashboardTest` | Totales, serie de 6 meses, movimientos, número de consultas |
| `IncomeDateTest` | Fecha de ingresos y su migración |
| `StatementImportTest`, `GenericCsvParserTest` | Importación, duplicados, formatos de CSV |

## Formato de extracto

```csv
fecha;descripcion;monto
01/11/2026;Sueldo noviembre;4500,00
03/11/2026;Plaza Vea;-210,40
```

- Separador `,` o `;`. Fechas `AAAA-MM-DD`, `DD/MM/AAAA` o `DD-MM-AAAA`.
- **Monto negativo = gasto** (en la categoría que elijas al subir);
  **positivo = ingreso**.
- Máximo 1 MB y 5.000 movimientos. Ejemplo completo en
  [`docs/ejemplo-extracto.csv`](docs/ejemplo-extracto.csv).

## Despliegue en Render

- Web Service con el `Dockerfile` (puerto 10000) y PostgreSQL administrado.
- Variables: `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY`, `APP_URL`,
  `DB_*` y `QUEUE_CONNECTION=database`.
- Auto-Deploy: **After CI Checks Pass**.
- Después de desplegar cambios con migraciones: `php artisan migrate --force`.

## Limitaciones conocidas

- **Zona horaria:** `config/app.php` fija `'timezone' => 'UTC'` y no lee
  `APP_TIMEZONE`. Después de las 19:00 en Lima, la fecha que proponen los
  formularios por defecto ya es la del día siguiente. Corregirlo cambia cómo
  se guardan los `created_at` nuevos, así que es una decisión aparte.
- La importación entiende un formato genérico; los formatos propios de cada
  banco (por ejemplo, columnas separadas de cargo y abono) requieren su propio
  `StatementParser`.
- Si hay dos presupuestos activos de la misma categoría, ambos muestran el
  mismo consumo. Lo recomendable es uno por categoría.

---

Carlos Pasache · [LinkedIn](https://linkedin.com/in/carlos-arturo-pasache-ordinola-719ab1260)
