# F0 — Base de pruebas

**Objetivo:** que `php artisan test` corra en verde sobre MySQL y exista una prueba de humo que
recorra todas las pantallas. Sin esto ninguna fase posterior puede demostrar que cumple.

**Por qué:** hoy la suite falla 31 de 32 pruebas porque corre en SQLite y las migraciones usan
`ALTER TABLE ... ADD CONSTRAINT CHECK` y triggers de MySQL. No hay ninguna prueba de dominio.

## Archivos

| Acción | Ruta |
|---|---|
| Modificar | `phpunit.xml` |
| Modificar | `tests/TestCase.php` |
| Crear | `tests/Concerns/CreatesActors.php` |
| Crear | `tests/Feature/Smoke/RoutesSmokeTest.php` |

## Interfaces que produce

- Base de datos de pruebas `laravel_pichangav2_test` (MySQL local de XAMPP, usuario `root`).
- `Tests\Concerns\CreatesActors`: `admin(): User`, `organizer(): User`, `delegate(): User`. Cada
  uno crea un usuario activo y verificado con ese rol. El trait siembra `RolePermissionSeeder`
  una vez por prueba (`seedRoles(): void`, llamado desde los tres métodos).
- `Tests\TestCase::setUp()` aborta si el nombre de la base no termina en `_test`.

## Tarea 0.1 — Suite en MySQL

- [x] **Paso 1.** Crear la base: `/c/xampp/mysql/bin/mysql -u root -e "CREATE DATABASE IF NOT EXISTS laravel_pichangav2_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"`.
- [x] **Paso 2.** En `phpunit.xml`: `DB_CONNECTION` = `mysql`, `DB_DATABASE` = `laravel_pichangav2_test`. Quitar `DB_URL`.
- [x] **Paso 3.** En `tests/TestCase.php`, `setUp()`: tras `parent::setUp()`, si
  `config('database.connections.'.config('database.default').'.database')` no termina en `_test`,
  `$this->fail('Las pruebas solo corren sobre una base *_test.')`. Protege la base de trabajo
  de `RefreshDatabase`.
- [x] **Paso 4.** Ejecutar `php artisan test`. Esperado: las 32 pruebas de Jetstream en verde. Si
  alguna falla por una función desactivada (registro público, borrado de cuenta), corregir la
  prueba para que se salte con el mismo criterio `Features::enabled(...)` que usan las demás; no
  se borra ninguna prueba.
- [x] **Paso 5.** Commit: `Pruebas: suite sobre MySQL con base laravel_pichangav2_test`.

## Tarea 0.2 — Actores y prueba de humo

- [x] **Paso 1.** Crear `tests/Concerns/CreatesActors.php` con la interfaz de arriba.
- [x] **Paso 2.** Escribir `tests/Feature/Smoke/RoutesSmokeTest.php` (usa `RefreshDatabase` y `CreatesActors`):

| Prueba | Afirmación |
|---|---|
| `test_guest_is_redirected_to_login` | Para cada ruta de `adminRoutes()`, un invitado recibe redirección a `login` |
| `test_admin_can_open_every_screen` | Para cada ruta de `adminRoutes()`, el admin recibe 200 |
| `test_public_pages_load` | `home` y `public.results` devuelven 200 sin sesión |
| `test_delegate_cannot_open_admin_screens` | El delegado recibe 403 en `admin.sports.index`, `admin.users.index`, `admin.roles.index` |

  `adminRoutes(): array` devuelve, en F0: `dashboard`, `teams.index`, `players.index`,
  `tournaments.index`, `seasons.index`, `season-teams.index`, `rosters.index`,
  `registrations.index`, `matchdays.index`, `matches.index`, `relays.index`, `periods.index`,
  `results.index`, `match-events.index`, `suspensions.index`, `reopen-logs.index`,
  `reports.index`, `reports.roster`, `reports.managerial`, `categories.index`, `venues.index`,
  `admin.sports.index`, `admin.users.index`, `admin.roles.index`, `games-editions.index`.
  **Cada fase que agrega o quita una ruta actualiza esta lista.**
- [x] **Paso 3.** Ejecutar `php artisan test --filter=RoutesSmokeTest`. Esperado: 4 pruebas en verde.
  Si una pantalla falla con el admin, es un defecto existente: se corrige aquí.
- [x] **Paso 4.** Commit: `Pruebas: actores por rol y prueba de humo de rutas`.

## Reglas que cumple

| Regla | Prueba |
|---|---|
| N4: pruebas automatizadas ejecutables | `php artisan test` en verde |
| N5: la base de trabajo no se toca | Guarda de `TestCase::setUp()` |
| Toda pantalla exige sesión | `test_guest_is_redirected_to_login` |

## Puerta de F0

Ejecutar G1–G4 del plan maestro (G5 y G6 aún no aplican). Registrar el resultado en la tabla
"Estado" de `00-plan-maestro.md`.
