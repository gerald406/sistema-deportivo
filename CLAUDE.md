# Sistema de Gestión Deportiva Integral

Laravel 12 + PHP 8.2 (XAMPP) + Jetstream (stack Livewire) + **Livewire 3 (NUNCA 4)** + TailwindCSS
+ Alpine.js (incluido con Livewire 3) + SweetAlert2 + Font Awesome + MySQL/MariaDB 10.4 (XAMPP).

## Qué es esto

Gestión de torneos multi-deporte: fútbol/vóley (enfrentamiento), atletismo (individual con
ranking), postas (equipos, relay), ajedrez, y una capa opcional "Olimpiadas" (`GamesEdition`)
que agrupa varios deportes. Cero duplicación por deporte: NO existen `FootballTeam`,
`VolleyPlayer`, etc. Un solo modelo de dominio para todos los deportes.

## Reglas de negocio confirmadas (NO renegociar sin preguntar)

1. Exactamente 3 roles: `admin`, `organizador`, `delegado`. No existe rol "jugador" ni login
   de jugadores.
2. Un usuario puede tener varios roles a la vez.
3. **Solo el admin crea cuentas de usuario.** Cualquier feature que cree un `User` (alta rápida
   de delegado desde el modal de Team, CRUD de Usuarios) debe revalidar `hasRole('admin')`
   **en el Service**, nunca confiar solo en que el botón esté oculto en el Blade — cualquier
   método público de un componente Livewire es invocable desde la consola del navegador.
4. **Sin soft deletes en ningún lado.** Se usa `is_active` boolean. Los borrados físicos deben
   verificar antes si el registro tiene historial dependiente (FKs con `restrictOnDelete()`) y
   devolver un error claro en vez de dejar que MySQL truene con un 1451.
5. `SeasonTeam::effectiveDelegate()`: el delegado de la temporada (si existe) tiene precedencia
   sobre el delegado general del club (`Team.delegate_id`).
6. `Player` es un catálogo global (no pertenece a un solo Team hasta que se inscribe vía
   `season_team_player`, que es Fase 5). Por eso `players.created_by` existe: reconoce
   propiedad de un jugador recién creado antes de tener roster.

## Arquitectura (no negociable sin pasar por diseño primero)

- **Lógica de negocio SOLO en `app/Services`.** Los componentes Livewire autorizan
  (`$this->authorize()` o `abort_unless(hasRole(...))`) y delegan al Service. Nunca lógica de
  negocio en el modelo ni en el componente.
- **Autorización vía `app/Policies`** (Tournament, Season, Team, Player ya existen) + un
  `Gate::before` en `AppServiceProvider` que da bypass total al rol `admin`.
- **Enums en `app/Enums`** (backed string enums, `declare(strict_types=1)`), nunca strings
  mágicos.
- **`Relation::enforceMorphMap()`** en `AppServiceProvider::boot()` — CUALQUIER modelo nuevo
  que participe en una relación polimórfica debe agregarse ahí o revienta con
  `ClassMorphViolationException` (ya pasó una vez con `User` en `model_has_roles` de Spatie).
- **Livewire\Form NO se usa** en este proyecto — se usa un array público `$form` +
  `$this->validate($this->rules())` con reglas `'form.campo' => [...]`. Sigue este patrón en
  CRUDs nuevos para mantener consistencia.

## El patrón de CRUD ya establecido — SIEMPRE úsalo para módulos nuevos

Ver el skill `laravel-crud-livewire` (en `.claude/skills/`). Resumen: un componente
`Livewire\Component` con `WithPagination` + trait `Sortable` (`app/Livewire/Concerns/Sortable.php`)
para orden de columnas, filtros de búsqueda/estado con `wire:model.live.debounce`, modal
crear/editar con Alpine (`x-data="{ show: @entangle('showModal') }"`), confirmación de borrado
con SweetAlert2 (`@script` + `Swal.fire` + `$wire.delete(id)`), y un `Service` dedicado con los
métodos `register/update/delete` (delete con chequeo de RESTRICT). Los CRUD ya construidos que
sirven de referencia exacta: `app/Livewire/Teams/Index.php`, `app/Livewire/Players/Index.php`,
`app/Livewire/Admin/Sports/Index.php`, `app/Livewire/Admin/Users/Index.php`.

## Estado del proyecto (10 fases)

| # | Fase | Estado |
|---|---|---|
| 1 | Arquitectura y BD | ✅ Completa (26 migraciones + triggers de validación disciplina/deporte) |
| 2 | Autenticación y Roles | ✅ Completa (Spatie roles/permisos, Policies, Gate::before) |
| 3 | Equipos y Jugadores | ✅ Completa (CRUD, fotos, filtros, orden, alta rápida de delegado) |
| — | Módulo Administración | ✅ Completa (Deportes/Disciplinas, Usuarios, Roles y Permisos) |
| 4 | Torneos y Temporadas | ⬜ Pendiente — CRUD de `Tournament` y `Season` (depende de Sport, ya listo) |
| 5 | Inscripciones | ⬜ Pendiente — `SeasonTeam` / `SeasonTeamPlayer` (roster por temporada) |
| 6 | Jornadas y Partidos | ⬜ Pendiente — `Matchday`, `GameMatch` |
| 7 | Resultados y Estadísticas | ⬜ Pendiente — `EventParticipant`, `MatchEvent`, `SeasonStanding` |
| 8 | Reportes y Dashboard | ⬜ Pendiente — ampliar `app/Livewire/Dashboard.php` |
| 9 | Optimización y Seguridad | ⬜ Pendiente |
| 10 | Pruebas y Producción | ⬜ Pendiente |

**Sigue este orden**: dentro de cada fase, implementa primero las tablas independientes/catálogo
antes que las que dependen de ellas (ya se hizo así con Sport/Discipline antes de Season).

## Modelos y ubicación (dónde mirar antes de escribir código nuevo)

- Migraciones: `database/migrations/` (26 archivos con comentarios en español + 2 migraciones
  de ajuste posteriores: `add_created_by_to_players_table`, `add_is_active_to_users_table`)
- Modelos: `app/Models/` (Sport, Discipline, SportPosition, EventType, Category, Venue,
  Tournament, GamesEdition, Season, ScoringConfig, Team, Player, Matchday, GameMatch,
  EventParticipant, EventLineup, MatchPeriod, MatchEvent, Suspension, MatchReopenLog,
  SeasonStanding, GamesEditionMedal, SeasonTeam, SeasonTeamPlayer)
- Enums: `app/Enums/`
- Policies: `app/Policies/`
- Services: `app/Services/`
- CRUDs Livewire: `app/Livewire/{Teams,Players,Admin/Sports,Admin/Users,Admin/Roles}`
- Vistas: `resources/views/livewire/...`, layout en `resources/views/layouts/admin.blade.php`
- Menú del sidebar: `config/admin-menu.php` + `app/Support/Menu/AdminMenu.php` — **cada CRUD
  nuevo debe agregar su entrada aquí** (con `permission` o `role`) para aparecer en el sidebar.

## Flujo de trabajo esperado (Windows 10 + XAMPP + Git)

1. Antes de tocar código: leer este archivo completo, la carpeta `database/migrations/`,
   `app/Models/`, y los 4 CRUDs de referencia mencionados arriba.
2. Un CRUD nuevo = una Policy (si aplica) + un Service + un componente Livewire + una vista
   Blade + entrada de ruta en `routes/web.php` + entrada en `config/admin-menu.php`.
3. **Un commit de git por CRUD terminado y probado**, mensaje en español describiendo qué se
   agregó (ej: `git commit -m "Fase 4: CRUD de Tournament con Policy y filtros"`).
4. Nunca `git add -A` a ciegas — revisar `git status` primero (no commitear `.env`, `vendor/`,
   `node_modules/`, `storage/logs/`).
5. Después de cada migración nueva: `php artisan migrate` (nunca `migrate:fresh` sin
   preguntar, hay datos de prueba).
6. Antes de dar por terminado un CRUD: probarlo manualmente en el navegador (crear, editar,
   eliminar, filtrar, ordenar) y confirmar que el Policy bloquea a quien no debe tener acceso.
