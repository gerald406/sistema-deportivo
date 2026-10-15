# Sistema de Gestión Deportiva Integral

Laravel 12 + PHP 8.2 (XAMPP) + Jetstream (stack Livewire) + **Livewire 3 (NUNCA 4)** + TailwindCSS
+ Alpine.js (incluido con Livewire 3) + SweetAlert2 + Font Awesome + MySQL/MariaDB 10.4 (XAMPP).

## Qué es esto

Sistema a medida de una olimpiada multi-deporte: fútbol/vóley (enfrentamiento), atletismo
(individual con ranking), postas (equipos, relay), ajedrez. La olimpiada (`GamesEdition`) es el
eje: tiene un organizador y agrupa las competencias (`Season`) de todos los deportes.
Cero duplicación por deporte: NO existen `FootballTeam`,
`VolleyPlayer`, etc. Un solo modelo de dominio para todos los deportes.

## Reglas de negocio confirmadas (NO renegociar sin preguntar)

1. Exactamente 3 roles: `admin`, `organizador`, `delegado`. No existe rol "jugador" ni login
   de jugadores.
2. Un usuario puede tener varios roles a la vez.
3. **Cuentas de usuario.** El admin crea cuentas de cualquier rol (CRUD de Usuarios,
   `UserAccountService`). El organizador que tiene una olimpiada a cargo crea cuentas **solo con
   rol `delegado`** (alta rápida en /delegaciones-inscritas, `DelegateAccountService`, que fuerza
   el rol y nunca lo lee del formulario). Nadie más crea cuentas. Todo se revalida **en el
   Service**, nunca confiar solo en que el botón esté oculto en el Blade — cualquier método
   público de un componente Livewire es invocable desde la consola del navegador.
4. **Sin soft deletes en ningún lado.** Se usa `is_active` boolean. Los borrados físicos deben
   verificar antes si el registro tiene historial dependiente (FKs con `restrictOnDelete()`) y
   devolver un error claro en vez de dejar que MySQL truene con un 1451.
5. **Delegados: una sola fuente.** El delegado titular y el suplente de una delegación viven en
   su inscripción en la olimpiada (`games_edition_teams.delegate_id` y
   `substitute_delegate_id`) y tienen los mismos permisos. `teams.delegate_id` y
   `season_team.delegate_id` ya NO existen. Para preguntar "¿es delegado?" se usa
   `SeasonTeam::hasDelegate()` / `delegates()` y, en SQL, `SeasonTeamPolicy::delegateConstraint()`;
   ningún Service reimplementa la regla.
6. `Player` (participante) es un catálogo global: no pertenece a una delegación hasta que entra
   a un plantel (`season_team_player`). Por eso `players.created_by` existe: reconoce al delegado
   que lo registró antes de tener plantel. Lleva género, tipo (docente/administrativo) y condición
   (nombrado/contratado). `documents_verified_at` solo lo fija el organizador o el admin
   (`PlayerRegistrationService::setDocumentsVerified()`), nunca el delegado ni el formulario.
7. **Reglas de plantel = datos.** Género, edad por AÑO de nacimiento (año de la olimpiada − año
   de nacimiento ≥ edad mínima del nivel; sin edad máxima), tope de plantel y "una sola
   delegación por olimpiada" salen de la competencia (`seasons.category_id`, `seasons.gender`) y
   de su fila del cuadro. Las evalúa `RosterRuleService`; **advierten** salvo que la fila tenga
   `rules_blocking`. Ningún otro Service reimplementa estas reglas.

8. **Fases, llaves y podios.** Una competencia tiene fases (`SeasonStage`): de **partidos**
   (llave de eliminación simple) o de **podio directo** (solo 1.º, 2.º y 3.º, `SeasonStageResult`).
   - `StageService::createStandardStages()` arma la estructura de `sports.standard_stages`
     (enum `StandardStages`; vacío = según el formato): `bracket_series` → una fase de partidos
     por serie + "Fase final" con tercer puesto (fútbol, futsal, básquet, vóley, tenis de mesa);
     `podium_series` → una fase de podio por serie (clasifica 1) + "Final" de podio (ajedrez,
     suizo fuera del sistema, D18); `podium_only` → solo "Final" de podio (lo demás). En /fases,
     "Generar fases de todas" lo aplica a cada competencia activa sin fases de la olimpiada
     elegida (`createMissingStandardStages()`). Sin competencia elegida, /fases lista todas las
     fases de la olimpiada (paginado, filtros por deporte, nivel, género, modo, serie y avance,
     Excel) y avisa qué competencias activas no tienen fases. El podio de una fase con serie solo admite delegaciones
     de esa serie (`DirectPodiumService`).
   - `BracketService::generate()` arma la llave con byes para los primeros de la lista y crea
     todos los partidos enlazados (`next_match_id`); `advance()` mueve al ganador (y al perdedor
     al partido por el tercer puesto) desde `ResultService::afterResultChanged()`.
   - Un partido de llave (`matches.season_stage_id`) **no admite empate**: exige vía de desempate
     y lado ganador (`TiebreakType`). Fuera de una llave el empate sigue permitido.
   - No se reabre un partido si la ronda siguiente ya se jugó (`BracketService::ensureCanReopen()`).
   - Las gestiona quien gestiona la competencia (`SeasonAccess::manages()`), pantalla /fases.
9. **Sanciones automáticas: solo rojas.** Al cerrar un partido, cada roja (directa o por doble
   amarilla) da 1 partido de sanción, sea o no capitán el expulsado (`SuspensionService`). La
   acumulación de amarillas y la sanción especial al capitán se manejan **fuera del sistema**
   (decisión del cliente); si se decide una, el organizador la registra como sanción manual.
   La olimpiada guarda solo nombre, organizador, fechas de inicio y fin (obligatorias), logo y
   estado: no tiene sede principal ni reglas disciplinarias.

10. **Puntaje general = dato derivado.** `AwardService::rebuild()` borra y recalcula
   `edition_awards` y `games_edition_medals` de la olimpiada: cada podio (de la ÚLTIMA fase de
   cada competencia activa) suma los puntos de su fila del cuadro; sin fila o sin puntos suma 0.
   Orden: puntos, luego 1.º, 2.º y 3.º puestos; una delegación descalificada va al final sin
   posición. Se llama de forma síncrona desde `ResultService`, `DirectPodiumService`,
   `StageService`, `SeasonService` y `EditionEventService`. `MedalService` ya NO existe. Nunca
   se escribe en esas tablas desde otro sitio. Pantalla: /reportes/puntaje-general.

### Flujo de permisos (confirmado por el negocio; el admin conserva bypass total vía `Gate::before`)

Cada punto se revalida **en el Service**, no solo en la Policy ni ocultando botones en Blade.

1. **Olimpiadas (`GamesEdition`)**: solo el ADMIN las crea (y las borra) y asigna su organizador
   (`organizer_id`). El organizador asignado edita los datos de SU olimpiada, pero no cambia
   `organizer_id` (`GamesEditionPolicy`, `GamesEditionService`). `Tournament` ya NO existe: la
   olimpiada es el unico eje. La regla "quien gestiona la olimpiada" vive en un solo sitio:
   `GamesEditionService::manages()`.
2. **Competencias (`Season`, pantalla /competencias)**: deporte × nivel × género (y prueba en
   atletismo). **Nunca se crean a mano** (D19): salen del cuadro de puntaje
   (`CompetitionGeneratorService::generate()`, idempotente: solo agrega, nunca modifica ni
   reactiva), que corre solo cada vez que el admin guarda una fila (`EditionEventService`) y
   también con el botón "Generar competencias". `SeasonService::update()` solo cambia nombre,
   fechas, estado, activa y fases de puntaje (`SeasonService::EDITABLE`); deporte, prueba,
   nivel, género y fila del cuadro no se editan. Solo quien gestiona la olimpiada
   (`SeasonPolicy`, `SeasonService::ensureCanUseEdition()`). Una competencia que no corresponde
   se **desactiva**, no se borra (el generador la volvería a crear).
3. **Delegaciones (`Team`)**: las crea, edita y borra el ORGANIZADOR o el ADMIN (`teams.manage`).
   El delegado NO entra a /equipos ni edita su delegación: solo registra participantes
   (`TeamPolicy`, `TeamRegistrationService`). El organizador solo ve y gestiona las delegaciones
   **a su cargo**: inscritas en una olimpiada suya o creadas por él (`teams.created_by`) — ver
   `TeamRegistrationService::scopeManagedBy()`.
3b. **Series y delegaciones inscritas (`GamesEditionGroup`, `GamesEditionTeam`)**: las gestiona
   quien gestiona la olimpiada. Ahí se asignan la serie, el delegado titular y el suplente, el
   estado (inscrita / retirada / descalificada) y el pago. El organizador solo inscribe
   delegaciones a su cargo (`EditionGroupService`, `EditionTeamService`). Las pantallas usan el
   trait `App\Livewire\Concerns\SelectsEdition` (olimpiada en `?olimpiada=`, siempre buscada
   dentro de las que el usuario gestiona).
3c. **Cuadro de puntaje (`GamesEditionEvent`, pantalla /puntajes)**: lo edita SOLO el admin
   (`EditionEventService` revalida `hasRole('admin')`); el organizador de la olimpiada solo lo ve.
   Puntos y topes de plantel son opcionales: vacío = sin puntos / sin restricción. Los datos
   reales de Puno 2026 se cargan con `php artisan db:seed --class=Puno2026Seeder` (no destructivo).
4. **Inscripciones a una competencia (`SeasonTeam`)**: nacen solas cuando se registra el primer
   participante de la delegación en esa competencia (`SeasonTeamService::ensureFor()`, que exige
   que la delegación esté inscrita en la olimpiada y no retirada ni descalificada). El delegado
   NUNCA crea ni edita `SeasonTeam` directamente; el ORGANIZADOR o el ADMIN las editan o retiran
   en /inscripciones (`SeasonTeamPolicy`, `SeasonTeamService`).
5. **Participantes en el plantel (`SeasonTeamPlayer`)**: los inscribe el DELEGADO (titular o
   suplente) eligiendo competencia y delegación
   (`SeasonTeamPlayerService::registerInCompetition()`, que autoriza con `canRegisterFor()`),
   y nunca en delegaciones ajenas. El organizador de la olimpiada y el admin conservan acceso de supervisión
   (`SeasonTeamPlayerPolicy::managesSeasonTeam()`).

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

## Plan vigente: Olimpiada Puno 2026 (rama `feature/olimpiada-puno-2026`)

**El plan que manda esta en `docs/fases/`** y reemplaza a la tabla R1-R6 de mas abajo:

- `docs/fases/00-especificacion.md`: que exigen las bases, decisiones del cliente (D1-D15) y
  supuestos por confirmar.
- `docs/fases/00-plan-maestro.md`: estado de cada fase e inventarios de migraciones, tablas,
  enums, Services, Policies, rutas, permisos, menu y pruebas.
- `docs/fases/F0..F7-*.md`: una fase por archivo, con tareas, pruebas y su puerta.

Para implementar o retomar una fase se usa la skill `implementar-fase`
(`.claude/skills/implementar-fase/SKILL.md`): una fase a la vez y no se avanza si la puerta
(`php artisan test` en verde, Blade compilando, rutas, migraciones al dia) no pasa.

Las pruebas corren sobre MySQL en la base `laravel_pichangav2_test` (`phpunit.xml`);
`tests/TestCase.php` aborta si la base no termina en `_test`.

Producción (F7, parcial): guía en `docs/despliegue.md`. Respaldo con `php artisan db:backup`
(`storage/app/backups/`, nunca en git: tiene datos personales). La aplicación corre en hora
`America/Lima` y fuerza HTTPS cuando `APP_ENV=production`. Falta desplegar y el simulacro: están
bloqueados hasta que el cliente elija hosting.

## Rediseño "Ediciones flexibles" — diseño original (referencia historica)

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
  GamesEdition, GamesEditionGroup, GamesEditionTeam, GamesEditionEvent, Season, SeasonStage,
  SeasonStageResult, EditionAward, ScoringConfig, Team, Player, Matchday, GameMatch,
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
