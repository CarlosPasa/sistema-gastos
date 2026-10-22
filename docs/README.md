# MT 2.0 Docker: Laravel + Nginx + PostgreSQL

## Alcance
Paquete SOLO de infraestructura; combinar con el proyecto Laravel 12 existente (Entrega 05). No reemplazar app/, resources/, routes/, public/, ni database/. Compatible con desarrollo en Windows mediante Docker Desktop. El `Dockerfile` contiene PHP 8.3, Nginx y PHP-FPM ejecutados con Supervisor, y sirve la aplicación por el puerto 10000 dentro del contenedor. PostgreSQL corre en un servicio independiente. No utiliza `php artisan serve`.

## Inicio
1. Copiar Dockerfile, compose.yaml, .dockerignore y `docker/` en `C:\xampp\htdocs\mt-2`.
2. Crear `.env` si falta: PowerShell `Copy-Item .env.example .env`.
3. En `.env`: `APP_URL=http://localhost:8000`, `DB_CONNECTION=pgsql`, `DB_HOST=db`, `DB_PORT=5432`, `DB_DATABASE=mt2`, `DB_USERNAME=mt2_user`, `DB_PASSWORD=mt2_local_2026`.
4. Ejecutar `docker compose up -d --build`.
5. Ejecutar `docker compose exec app php artisan key:generate` (la primera vez).
6. Ejecutar `docker compose exec app php artisan config:clear`.
7. Comprobar conexión `docker compose exec app php artisan db:show`.
8. Ejecutar `docker compose exec app php artisan migrate` **solo después de validar las migraciones de la Entrega 05** y conflictos con Laravel.
9. Abrir http://localhost:8000/mt2-preview.

## Operación
- `docker compose logs -f app`
- `docker compose exec app php artisan optimize:clear`
- `docker compose exec app php artisan migrate:status`
- `docker compose down` detiene y preserva volúmenes.
- NO usar `docker compose down -v` si quieres conservar datos.
- Si cambias composer.json, `docker compose exec app composer install`; si cambias Dockerfile, `docker compose up -d --build`.

## Precauciones
Este `compose.yaml` es de DESARROLLO: usa bind mount para aplicar cambios instantáneos y una contraseña de ejemplo. No utilizarlo en producción, no subir `.env` a Git, y usar credenciales fuertes. Para Render, construir el Dockerfile y crear un **Web Service** con `PORT=10000`, APP_ENV=production, APP_DEBUG=false, APP_KEY seguro, APP_URL, y variables DATABASE desde un PostgreSQL administrado en Render. El archivo .env NO se incorpora en la imagen. Configurar almacenamiento persistente para subidas, sesiones/cache en servicios adecuados, y ejecutar migraciones de forma controlada por separado. El Dockerfile asume que `composer.json` tiene dependencias compatibles con PHP 8.3. Laravel 12 funciona con PHP >= 8.2.
