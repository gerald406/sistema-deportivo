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

### Flujo de permisos (confirmado por el negocio; el admin conserva bypass total vía `Gate::before`)

Cada punto se revalida **en el Service**, no solo en la Policy ni ocultando botones en Blade.

1. **Torneos (`Tournament`)**: solo el ADMIN los crea (y los borra) y asigna su organizador
   (`organizer_id`). El organizador asignado edita los datos de SU torneo, pero no cambia
   `organizer_id` (`TournamentPolicy`, `TournamentService`).
2. **Temporadas (`Season`)**: las crea el ORGANIZADOR, pero solo dentro de un torneo que le fue
   asignado (`tournament.organizer_id === auth()->id()`) (`SeasonPolicy`,
   `SeasonService::ensureCanUseTournament()`).
3. **Equipos (`Team`) y su delegado (`delegate_id`)**: los crea el ORGANIZADOR o el ADMIN. El
   delegado NUNCA crea ni borra equipos; conserva la edición de nombre/logo de SU equipo sin
   poder reasignar `delegate_id` (`TeamPolicy`, `TeamRegistrationService`). El organizador tiene
   el permiso `teams.manage` para esto. Crear la *cuenta* de un delegado sigue siendo solo del
   admin (regla 3 de arriba). En /equipos el organizador solo ve y gestiona los equipos **a su
   cargo**: con al menos una inscripción en una temporada de un torneo suyo, o creados por él
   (`teams.created_by`) — ver `TeamRegistrationService::scopeManagedBy()`.
4. **Inscripciones (`SeasonTeam`)**: las crean, editan y retiran solo el ORGANIZADOR del torneo o
   el ADMIN, en Borrador o En curso (nadie en Cerrada). El delegado NUNCA crea ni edita
   `SeasonTeam` en ningún estado; solo las ve (`SeasonTeamPolicy`, `SeasonTeamService`).
   *Reemplaza la regla obsoleta de Fase 5 "el delegado inscribe sus equipos en Borrador".*
5. **Jugadores en el plantel (`SeasonTeamPlayer`)**: los inscribe el DELEGADO en los equipos que
   tiene asignados (`SeasonTeam::effectiveDelegate()`), y nunca en equipos ajenos. El organizador
   del torneo y el admin conservan acceso de supervisión (`SeasonTeamPlayerPolicy::managesSeasonTeam()`).

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
`Livewire\Component` con `WithPagination` + traits `Sortable` y `LimitsPerPage`
(`app/Livewire/Concerns/`) para orden de columnas y paginación, filtros de búsqueda/estado con
`wire:model.live.debounce`, modal crear/editar con Alpine
(`x-data="{ show: @entangle('showModal') }"`), confirmación de borrado con SweetAlert2 vía el
helper global del layout, y un `Service` dedicado con los métodos `register/update/delete`
(delete con chequeo de RESTRICT).

Reglas del listado (seguridad — propiedades públicas son manipulables desde el navegador):
- Implementar `sortableFields()` (lista blanca) y en `render()` usar
  `orderBy($this->sortColumn(), $this->sortOrder())` y `paginate($this->perPageLimit())`,
  **nunca** `$this->sortField` / `$this->perPage` directo.
- Borrado: `x-on:click="confirmDelete(@js($model->name), () => $wire.delete({{ $model->id }}))"`.
  **Nunca** `onclick="fn('{{ $model->name }}')"` (XSS almacenado) ni `function` dentro de
  `@script` (Livewire 3 no la deja global).
- Autorización en **cada** acción, no solo en `mount()` (el middleware de la ruta no se
  reaplica en `/livewire/update`): con Policy, `$this->authorize()` en cada método (Teams,
  Players); si el módulo es solo por rol, `abort_unless(...)` en `boot()` (Sports, Users).
- `Service::delete()` devuelve `bool|string`: `true` o el mensaje exacto para el toast.
- Ids que llegan del navegador (p. ej. `editingId`) se buscan acotados a su padre
  (`Discipline::where('sport_id', ...)->findOrFail($id)`), nunca con `find($id)` suelto. Los CRUD ya construidos que
sirven de referencia exacta: `app/Livewire/Teams/Index.php`, `app/Livewire/Players/Index.php`,
`app/Livewire/Admin/Sports/Index.php`, `app/Livewire/Admin/Users/Index.php`.

## Estado del proyecto (10 fases)

| # | Fase | Estado |
|---|---|---|
| 1 | Arquitectura y BD | ✅ Completa (26 migraciones + triggers de validación disciplina/deporte) |
| 2 | Autenticación y Roles | ✅ Completa (Spatie roles/permisos, Policies, Gate::before) |
| 3 | Equipos y Jugadores | ✅ Completa (CRUD, fotos, filtros, orden, alta rápida de delegado) |
| — | Módulo Administración | ✅ Completa (Deportes/Disciplinas, Usuarios, Roles y Permisos) |
| 4 | Torneos y Temporadas | ✅ Completa (Category, Venue, GamesEdition, Tournament, Season con fases de ScoringConfig en el modal). Season no tiene sede (se elige por partido); `scoring_configs.rules` aún sin editar |
| 5 | Inscripciones | ✅ Completa (SeasonTeam: solo organizador/admin inscriben, en Borrador o En curso — ver "Flujo de permisos" punto 4; SeasonTeamPlayer: edad por categoría, jugador único por temporada, camiseta única y un capitán por equipo) |
| 6 | Jornadas y Partidos | ✅ Completa (Matchday, GameMatch con participantes en el modal según formato efectivo = `discipline.format_type ?? sport.format_type`, EventLineup solo en postas, MatchPeriod solo en enfrentamientos). Disciplina/deporte la valida el trigger; el Service traduce su SQLSTATE 45000 |
| 7 | Resultados y Estadísticas | ✅ Completa (Resultados con reapertura auditada, Incidencias, Sanciones automáticas por roja/doble amarilla, tabla y medallero recalculados de forma síncrona en `ResultService::afterResultChanged()` — ver docblock de `StandingService`, vista de solo lectura de reaperturas) |
| 8 | Reportes y Dashboard | ✅ Completa (Dashboard con métricas acotadas por rol vía `DashboardService`; `/reportes` con tabla de posiciones y medallero de solo lectura) |
| 9 | Optimización y Seguridad | ⬜ Pendiente |
| 10 | Pruebas y Producción | ⬜ Pendiente |

**Sigue este orden**: dentro de cada fase, implementa primero las tablas independientes/catálogo
antes que las que dependen de ellas (ya se hizo así con Sport/Discipline antes de Season).

## Rediseño "Ediciones flexibles" (rama `feature/ediciones-flexibles`) — ⬜ Diseño aprobado, sin implementar

Motivo: sistematizar un evento real (XXVIII Juegos Deportivos Regionales de Docentes de IES/EES
de la Región Puno 2026, Yunguyo, 28-30/10/2026) **sin atar el código a ese reglamento**. Cada
fase se implementa solo cuando el usuario la pida.

### Principios (confirmados por el usuario)

1. **Genérico, flexible, no estricto.** Toda regla de reglamento (topes de plantel, mínimos,
   edades, clasificados, puntajes, certificado de salud) es un **dato configurable por
   edición**; campo vacío = sin restricción. No hardcodear nada de un reglamento concreto.
2. Las reglas de reglamento **advierten por defecto** y solo bloquean si el organizador las
   marca como bloqueantes (ej. Danza con menos parejas que el mínimo = alerta).
3. **La seguridad NO es flexible**: roles, Policies y revalidación en el Service se mantienen
   estrictas como en el resto del proyecto.
4. Fuera del sistema (decisión del cliente): jurados, Comisión de Justicia, sanciones entre
   ediciones, validación de ventana de reclamos, marcas/intentos de atletismo, pase de lista.

### Modelo objetivo

- **La edición es el eje**: `games_editions.tournament_id` (el organizador del torneo gestiona la
  edición; hoy la pantalla es solo admin) + `GamesEditionPolicy`.
- **Inscripción de la institución en la edición** (`games_edition_teams`): serie, delegado
  titular y **suplente** (mismos permisos que el titular), estado. Institución = `Team`.
  ⚠️ Cambia la regla 5 de "Reglas de negocio": precedencia pasará a ser delegado de temporada →
  titular/suplente de la edición → delegado del club. Actualizar esa regla al implementar la Fase 1.
- **Series** (`games_edition_groups`) a nivel de edición; cada competencia decide si las usa.
- **Tabla genérica de competencias y puntaje** (`games_edition_events`): disciplina o deporte
  (o ítem sin deporte: Estudiantina, Danza, Puntualidad) + nivel + género (vacío = podio mixto)
  + límites opcionales + puntos 1°/2°/3° opcionales. Cualquier granularidad (por prueba o por
  nivel). NO usar `scoring_configs.rules` para esto: `ScoringConfig` es puntaje por partido de
  la tabla de posiciones.
- **Season = competencia** (deporte × nivel × género) de la edición: `seasons.category_id` +
  `seasons.gender`, generadas automáticamente e idempotentes desde la tabla anterior. Así se
  reutilizan planteles, partidos, resultados y sanciones sin copiar datos entre fases.
- **Fases configurables** (`season_stages`): las que el organizador cree (serie, final, fase
  única), con clasificados configurables y dos modos de resultado: **partidos** (flujo actual +
  llaves con byes, avance automático, 3er puesto, desempate penales/tiros libres/sorteo) o
  **podio directo** (el organizador registra clasificados y 1°/2°/3°).
- **Medallero por puntos**: `edition_awards` (destino único de podios, derivado y reconstruible)
  + `games_edition_medals.total_points`; oros/platas/bronces quedan como desempate.
- **Plantel**: `players.gender`; edad por **año de nacimiento** (año de la edición − año de
  nacimiento ≥ `min_age`; hoy `ensureAgeFitsCategory()` usa edad cumplida a `season.start_date`);
  un docente puede jugar en niveles menores y en varios niveles/deportes; una sola institución
  por docente en la edición (configurable); suspendido no puede jugar; amarillas acumulables
  (configurable).

### Fases del rediseño

| # | Fase | Estado |
|---|---|---|
| R1 | Edición como eje: `tournament_id`, `GamesEditionPolicy`, inscripción de instituciones con titular/suplente, actualizar regla 5 | ⬜ |
| R2 | Plantel flexible: género, edad por año, reglas configurables | ⬜ |
| R3 | Tabla de competencias y puntaje + series + generación de competencias | ⬜ |
| R4 | Fases configurables: partidos con llaves o podio directo | ⬜ |
| R5 | Medallero por puntos + reportes | ⬜ |
| R6 | (Opcional, post-evento) pago de inscripción y bitácora de reclamos | ⬜ |

Antes de codificar cada fase: presentar al usuario su plan detallado (migraciones, Service,
Policy, pantalla) y esperar aprobación.

## Modelos y ubicación (dónde mirar antes de escribir código nuevo)

- Migraciones: `database/migrations/` (26 archivos con comentarios en español + 4 migraciones
  de ajuste posteriores: `add_created_by_to_players_table`, `add_is_active_to_users_table`,
  `restrict_roster_history_on_match_events_and_suspensions`, `add_created_by_to_teams_table`)
- `event_participants` es polimórfica **sin FK real** hacia `season_team`/`season_team_player`:
  antes de borrar una inscripción, revisar `eventParticipants()` en el Service.
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
