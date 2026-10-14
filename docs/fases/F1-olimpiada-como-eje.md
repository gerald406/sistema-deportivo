# F1 — Olimpiada como eje (Torneo eliminado)

**Objetivo:** que `Tournament` deje de existir y la olimpiada (`GamesEdition`) lleve el
organizador. Al cerrar la fase todas las pantallas actuales funcionan autorizando contra la
olimpiada.

**Decisiones que implementa:** D1, D2. **Requiere:** F0 cerrada.

**Trabajo previo recuperable:** `git stash list` muestra `WIP F1: eliminar Torneo`. Contiene las
migraciones M1–M3 y el renombrado masivo. Se recupera con `git stash pop` al empezar la tarea 1.1
y se completa siguiendo este plan; si el `pop` da conflicto, se descarta el stash y se rehace
desde los pasos de abajo, que son reproducibles.

## Archivos

| Acción | Ruta |
|---|---|
| Crear | Migraciones M1, M2, M3 (ver plan maestro) |
| Crear | `app/Policies/GamesEditionPolicy.php` |
| Crear | `database/factories/GamesEditionFactory.php`, `database/factories/SeasonFactory.php`, `database/factories/SportFactory.php` |
| Crear | `tests/Feature/Editions/GamesEditionAuthorizationTest.php`, `tests/Feature/Editions/SeasonBelongsToEditionTest.php` |
| Eliminar | `app/Models/Tournament.php`, `app/Services/TournamentService.php`, `app/Policies/TournamentPolicy.php`, `app/Livewire/Tournaments/`, `resources/views/livewire/tournaments/`, `database/factories/TournamentFactory.php` |
| Modificar | `app/Models/GamesEdition.php`, `app/Models/Season.php`, `app/Models/User.php` |
| Modificar | `app/Services/GamesEditionService.php`, `app/Services/SeasonService.php`, `app/Support/SeasonAccess.php` |
| Modificar | `app/Livewire/GamesEditions/Index.php`, `resources/views/livewire/games-editions/index.blade.php` |
| Modificar | `app/Livewire/Seasons/Index.php`, `resources/views/livewire/seasons/index.blade.php` |
| Modificar | `routes/web.php`, `config/admin-menu.php`, `database/seeders/RolePermissionSeeder.php` |
| Modificar | Los ~70 archivos con referencias a `tournament` (lista: `grep -rli tournament app resources/views config routes database`) |

## Interfaces

**Consume:** `Tests\Concerns\CreatesActors` (F0).

**Produce:**

- `GamesEdition`: `$fillable` + `logo`, `organizer_id`; `organizer(): BelongsTo` (User).
- `Season::gamesEdition(): BelongsTo` es la única relación hacia arriba. `Season::tournament()` no existe.
- `User::gamesEditions(): HasMany` (por `organizer_id`).
- `GamesEditionService` con las firmas del plan maestro.
- `SeasonService::editionsFor(User $actor): Collection` (id, name, is_active).
- `SeasonAccess::manages(User, Season)`: admin, u organizador con `matches.manage` y `season->gamesEdition->organizer_id === user->id`.
- `GamesEditionPolicy` con `viewAny`, `view`, `create`, `update`, `delete`, `manage`.
- Permiso `editions.manage`. Ruta `/olimpiadas` → `games-editions.index`.
- `GamesEditionFactory` (con `organizer_id` de un `User::factory()`), `SeasonFactory`, `SportFactory`.

## Tarea 1.1 — Migraciones y modelos

- [x] **Paso 1.** `git stash pop`. Verificar que existen M1, M2 y M3 en `database/migrations/`.
- [x] **Paso 2.** Escribir `SeasonBelongsToEditionTest`:

| Prueba | Afirmación |
|---|---|
| `test_tournaments_table_is_gone` | `Schema::hasTable('tournaments')` es falso |
| `test_season_requires_an_edition` | Crear un `Season` sin `games_edition_id` lanza `QueryException` |
| `test_season_name_is_unique_within_an_edition` | Dos `Season` con el mismo nombre en la misma olimpiada lanzan `QueryException`; en olimpiadas distintas, no |
| `test_edition_with_seasons_cannot_be_deleted` | `GamesEditionService::delete()` devuelve un string y la olimpiada sigue existiendo |
| `test_tournaments_permission_was_renamed` | Existe el permiso `editions.manage` y no existe `tournaments.manage` |

- [x] **Paso 3.** Ejecutar la prueba. Esperado: falla (aún sin factories ni modelos ajustados).
- [x] **Paso 4.** Ajustar modelos: `GamesEdition` (`$fillable`, `organizer()`), `Season` (quitar `tournament_id` y `tournament()`), `User` (`gamesEditions()`). Crear las tres factories.
- [x] **Paso 5.** `RolePermissionSeeder`: `tournaments.manage` → `editions.manage` en la lista y en el rol organizador.
- [x] **Paso 6.** `php artisan migrate` en la base de trabajo. Esperado: M1–M3 aplicadas; `games_editions.organizer_id` con valor en la olimpiada existente.
- [x] **Paso 7.** Ejecutar la prueba. Esperado: 5 en verde.
- [x] **Paso 8.** Commit: `F1: Olimpiada con organizador; tabla tournaments eliminada`.

## Tarea 1.2 — Renombrado de referencias y autorización

El stash ya trae este renombrado. Si se rehace, el orden de sustitución sobre los archivos de
`grep -rli tournament app resources/views config routes database/seeders` es:

1. `'tournaments.index'` → `'games-editions.index'`
2. `tournaments.manage` → `editions.manage`
3. `exists('tournaments'` → `exists('games_editions'`
4. `tournament_id` → `games_edition_id`
5. `Tournaments` → `GamesEditions`, `Tournament` → `GamesEdition`
6. `tournaments` → `gamesEditions`, `tournament` → `gamesEdition`
7. Textos: `Torneo(s)` → `Olimpiada(s)` cuidando el género (`del torneo` → `de la olimpiada`, `el torneo` → `la olimpiada`, `un torneo` → `una olimpiada`, `sus torneos` → `sus olimpiadas`, `otro torneo` → `otra olimpiada`).

- [x] **Paso 1.** Escribir `GamesEditionAuthorizationTest`:

| Prueba | Afirmación |
|---|---|
| `test_only_admin_creates_an_edition` | `register()` con un organizador como actor lanza `AuthorizationException`; con el admin crea la fila con el `organizer_id` elegido |
| `test_organizer_must_be_an_active_organizer_or_admin` | `register()` con `organizer_id` de un delegado lanza `ValidationException` en `form.organizer_id` |
| `test_assigned_organizer_updates_but_cannot_reassign` | El organizador asignado cambia el nombre; si envía otro `organizer_id`, se conserva el original |
| `test_other_organizer_is_forbidden` | Otro organizador: `can('update', $edition)` es falso y `SeasonAccess::manages()` sobre una competencia de esa olimpiada es falso |
| `test_only_admin_deletes_an_edition` | `can('delete', $edition)` es falso para el organizador asignado y verdadero para el admin |
| `test_season_can_only_be_created_in_own_edition` | `SeasonService::register()` de un organizador con el `games_edition_id` de otra olimpiada lanza `AuthorizationException` |
| `test_user_organizing_an_edition_cannot_be_deleted` | `UserAccountService::delete()` devuelve un string para ese usuario |

- [x] **Paso 2.** Ejecutar. Esperado: falla.
- [x] **Paso 3.** Crear `GamesEditionPolicy` y `GamesEditionService` según las firmas. `register()` revalida `hasRole('admin')`. `update()` ignora `organizer_id` si el actor no es admin. `delete()` conserva los chequeos actuales y borra el logo del disco `public` al terminar.
- [x] **Paso 4.** Corregir a mano los archivos donde el renombrado deja duplicados:
  - `app/Models/Season.php`: una sola clave `games_edition_id` en `$fillable`, una sola `gamesEdition()`.
  - `app/Services/SeasonService.php`: `editionsFor()`, `ensureCanUseEdition()`, docblock de `register()`.
  - `app/Livewire/Seasons/Index.php` y su vista: un solo campo "Olimpiada" obligatorio (el selector opcional de edición desaparece); filtro `editionFilter`; `with()` sin relación repetida.
  - `app/Services/PublicResultsService.php`, `app/Livewire/Public/PublicResults.php`, `app/Livewire/Reports/RosterReport.php`, `app/Livewire/PlayerRegistrationSelector.php`: comprobar que propiedad, método `updated...` y vista usan el mismo nombre.
- [x] **Paso 5.** Ejecutar `GamesEditionAuthorizationTest`. Esperado: 7 en verde.
- [x] **Paso 6.** Commit: `F1: Autorización contra la olimpiada en policies y services`.

## Tarea 1.3 — Pantalla de Olimpiadas, rutas y menú

- [x] **Paso 1.** `app/Livewire/GamesEditions/Index.php`: quitar el `abort_unless` de `boot()`; `authorize('viewAny', GamesEdition::class)` en `mount()` y `authorize()` en `create`, `edit`, `save`, `delete`. Agregar al formulario `organizer_id` (solo lo ve el admin) y logo con `WithFileUploads` (carpeta `editions` del disco `public`, máx. 2 MB). El listado usa `GamesEditionService::scopeManagedBy()`.
- [x] **Paso 2.** Vista: título "Olimpiadas", columnas Nombre (con logo), Organizador, Inicio, Fin, Sede principal (quitada después, D17), Competencias, Estado. Botón "Nueva olimpiada" bajo `@can('create', GamesEdition::class)`.
- [x] **Paso 3.** `routes/web.php`: una sola ruta `/olimpiadas` → `games-editions.index`, fuera del grupo `role:admin`. Quitar `/torneos` y `/ediciones`.
- [x] **Paso 4.** `config/admin-menu.php`: grupo "Olimpiada" con "Olimpiadas" (`editions.manage`) y "Temporadas" (`seasons.manage`). Quitar las entradas duplicadas.
- [x] **Paso 5.** `resources/views/livewire/dashboard.blade.php` y `DashboardService`: la métrica es `activeGamesEditions`, etiqueta "Olimpiadas activas", ruta `games-editions.index`, permiso `editions.manage`.
- [x] **Paso 6.** `RoutesSmokeTest::adminRoutes()`: quitar `tournaments.index`. Agregar la prueba `test_organizer_sees_only_own_editions`: con dos olimpiadas de organizadores distintos, la respuesta de `games-editions.index` contiene el nombre de la propia y no el de la ajena.
- [x] **Paso 7.** Revisar textos: `grep -rnE "otro olimpiada|del Olimpiada|los suyos" app resources` y corregir concordancias.
- [x] **Paso 8.** Ejecutar `php artisan test`. Esperado: todo en verde.
- [x] **Paso 9.** Commit: `F1: Pantalla de Olimpiadas con organizador y logo; rutas y menú`.

## Tarea 1.4 — Documentación

- [x] **Paso 1.** `CLAUDE.md`: reemplazar el punto 1 y 2 del "Flujo de permisos" (olimpiada en lugar de torneo), quitar `Tournament` de "Modelos y ubicación", y marcar la fase en la tabla del rediseño.
- [x] **Paso 2.** Commit: `Docs: CLAUDE.md refleja la olimpiada como eje`.

## Reglas que cumple

| Regla | Prueba |
|---|---|
| D1: no existe Torneo | `test_tournaments_table_is_gone`; puerta G5 |
| D2: solo el admin crea la olimpiada y asigna organizador | `test_only_admin_creates_an_edition`, `test_only_admin_deletes_an_edition` |
| El organizador no se reasigna a sí mismo ni a otro | `test_assigned_organizer_updates_but_cannot_reassign` |
| Un organizador no toca la olimpiada de otro | `test_other_organizer_is_forbidden`, `test_season_can_only_be_created_in_own_edition`, `test_organizer_sees_only_own_editions` |
| Sin borrado con historial | `test_edition_with_seasons_cannot_be_deleted`, `test_user_organizing_an_edition_cannot_be_deleted` |
| Las pantallas existentes siguen funcionando | `RoutesSmokeTest` |

## Puerta de F1

G1–G6 del plan maestro. G5 busca `Tournament|tournament|Torneo|torneo` en `app`, `resources`,
`routes`, `config` y `database/seeders`: 0 coincidencias. Registrar el resultado en la tabla
"Estado".
