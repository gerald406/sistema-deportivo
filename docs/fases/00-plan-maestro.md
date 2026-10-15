# Plan maestro — Olimpiada Puno 2026

> **Para quien ejecute:** usar la skill del proyecto `implementar-fase`
> (`.claude/skills/implementar-fase/SKILL.md`). Una fase a la vez, en orden. No se empieza una
> fase si la anterior no pasó su puerta. Los pasos usan casillas `- [ ]`.

**Objetivo:** convertir el sistema multi-torneo en el sistema a medida de los XXVIII Juegos de
Puno 2026, con la olimpiada como único eje.

**Arquitectura:** `GamesEdition` (olimpiada) lleva el organizador y de ella cuelgan series,
delegaciones inscritas, cuadro de puntaje y competencias (`Season`). Los módulos de partidos,
resultados, sanciones, carnets y reportes se conservan y pasan a autorizar contra la olimpiada.
Los podios alimentan un puntaje general derivado y reconstruible.

**Stack:** Laravel 12, PHP 8.2, Livewire 3 (nunca 4), Jetstream, Tailwind, Alpine, SweetAlert2,
MySQL/MariaDB 10.4, Spatie Permission, PHPUnit 11.

**Especificación:** `docs/fases/00-especificacion.md`

**Rama:** `feature/olimpiada-puno-2026` (creada desde `main`).

## Restricciones globales

- Lógica de negocio solo en `app/Services`. El componente Livewire autoriza y delega.
- Toda regla de permisos se revalida en el Service, no solo en la Policy ni en el Blade.
- Enums con respaldo string y `declare(strict_types=1)`; nunca strings mágicos.
- Sin soft deletes: `is_active`. `Service::delete()` devuelve `true` o el mensaje del toast.
- No se usa `Livewire\Form`: array público `$form` + `$this->validate($this->rules())`.
- Listados: `sortableFields()` como lista blanca, `sortColumn()`, `sortOrder()`, `perPageLimit()`.
- Borrado en Blade: `x-on:click="confirmDelete(@js($m->name), () => $wire.delete({{ $m->id }}))"`.
- Ids que llegan del navegador se buscan acotados a su padre, nunca con `find($id)` suelto.
- Modelo nuevo en una relación polimórfica: agregarlo a `Relation::enforceMorphMap()`.
- Migraciones aditivas. Nunca `migrate:fresh` en la base de trabajo (`laravel_pichangav2`).
- Reglas de reglamento: campo vacío = sin restricción; advierten salvo que estén marcadas como bloqueantes.
- Palabras prohibidas en `app/`, `resources/`, `routes/`, `config/`: `Tournament`, `tournament`, `Torneo`, `torneo` (desde F1); `Temporada`, `temporada` en `resources/views` y `config` (desde F4).
- Un commit por tarea terminada y probada, mensaje en español, sin `git add -A`.

## Estado

Actualizar esta tabla al cerrar cada fase. `Puerta` = resultado de la puerta de la fase.

| Fase | Archivo | Fechas | Estado | Puerta |
|---|---|---|---|---|
| F0 Base de pruebas | `F0-base-de-pruebas.md` | 12/10 | ✅ | 2026-10-12 · 26 pruebas, 0 fallos (10 omitidas por funciones de Jetstream desactivadas); G1–G4 aprobadas |
| F1 Olimpiada como eje | `F1-olimpiada-como-eje.md` | 13–14/10 | ✅ | 2026-10-12 · 45 pruebas, 0 fallos (10 omitidas de Jetstream); G1–G6 aprobadas |
| F2 Delegaciones y series | `F2-delegaciones-y-series.md` | 14–16/10 | ✅ | 2026-10-12 · 90 pruebas, 0 fallos (10 omitidas de Jetstream); G1–G6 aprobadas |
| F3 Catálogo y cuadro de puntaje | `F3-catalogo-y-puntajes.md` | 16–17/10 | ✅ | 2026-10-13 · 109 pruebas, 0 fallos (10 omitidas de Jetstream); G1–G6 aprobadas. S1, S2 y S6 confirmados por el cliente |
| F4 Competencias y plantel | `F4-competencias-y-plantel.md` | 17–19/10 | ✅ | 2026-10-13 · 145 pruebas, 0 fallos (10 omitidas de Jetstream); G1–G6 aprobadas. S3 confirmado el 13/10 |
| F5 Fases, llaves y podios | `F5-fases-llaves-podios.md` | 20–24/10 | ✅ | 2026-10-13 · 196 pruebas, 0 fallos (10 omitidas de Jetstream); G1–G6 aprobadas |
| F6 Puntaje general | `F6-puntaje-general.md` | 24–26/10 | ✅ | 2026-10-13 · 220 pruebas, 0 fallos (10 omitidas de Jetstream); G1–G6 aprobadas; suma a mano de 3 podios de Puno verificada en prueba. S4 y S5 confirmados |
| F7 Producción | `F7-produccion.md` | desde el 17/10, cierre 27/10 | 🟡 parcial | 2026-10-13 · 229 pruebas, 0 fallos (10 omitidas de Jetstream); G1–G4 y cachés de producción aprobados en local; respaldo y restauración probados en local. **Falta, y depende del hosting:** desplegar, repetir la restauración allí y los 10 pasos del simulacro |

Hitos externos: publicar y abrir a delegados al cerrar F4 (19/10); ciclismo y cierre de
inscripciones el 23/10 (necesita podio directo de F5); evento 28–30/10.

## La puerta (se ejecuta al final de cada fase)

Una fase está cerrada solo si **todos** estos comandos terminan bien:

| # | Comando | Resultado exigido |
|---|---|---|
| G1 | `php artisan test` | 0 fallos, 0 errores |
| G2 | `php artisan view:cache` y luego `php artisan view:clear` | Sin errores de compilación de Blade |
| G3 | `php artisan route:list --except-vendor` | Sin excepción; contiene las rutas de la fase |
| G4 | `php artisan migrate:status` | Ninguna migración `Pending` |
| G5 | Búsqueda de palabras prohibidas (ver Restricciones globales) | 0 coincidencias |
| G6 | Tabla "Reglas que cumple" de la fase | Cada regla con su prueba en verde |

Si algo falla: se corrige dentro de la misma fase y se repite la puerta completa. No se pasa a
la siguiente con una prueba omitida ni marcada como pendiente.

## Inventario de migraciones (en orden de ejecución)

| # | Fase | Archivo | Qué hace |
|---|---|---|---|
| M1 | F1 | `2026_10_12_090000_add_organizer_and_logo_to_games_editions_table` | `logo`, `organizer_id` (FK `users`, RESTRICT, obligatorio tras rellenar) |
| M2 | F1 | `2026_10_12_090100_replace_tournament_with_games_edition_on_seasons_table` | Quita `tournament_id`; `games_edition_id` obligatorio y RESTRICT; único (`games_edition_id`, `name`) |
| M3 | F1 | `2026_10_12_090200_drop_tournaments_table` | Borra `tournaments`; renombra el permiso `tournaments.manage` → `editions.manage` |
| M4 | F2 | `2026_10_13_090000_create_games_edition_groups_table` | Series |
| M5 | F2 | `2026_10_13_090100_create_games_edition_teams_table` | Delegaciones inscritas con titular y suplente |
| M6 | F2 | `2026_10_13_090200_drop_delegate_id_from_teams_and_season_team` | El delegado vive solo en M5 |
| M7 | F2 | `2026_10_13_090300_adjust_role_permissions_for_delegations` | Delegado pierde `teams.manage`; organizador gana `categories.manage` |
| M8 | F3 | `2026_10_14_090000_create_games_edition_events_table` | Cuadro de puntaje |
| M9 | F4 | `2026_10_15_090000_add_competition_fields_to_seasons_table` | `games_edition_event_id`, `category_id`, `gender`, `discipline_id` |
| M10 | F4 | `2026_10_15_090100_add_person_fields_to_players_table` | `gender`, `staff_type`, `employment_type`, `documents_verified_at` |
| M11 | F5 | `2026_10_19_090000_create_season_stages_table` | Fases de una competencia |
| M12 | F5 | `2026_10_19_090100_add_stage_and_bracket_fields_to_matches_table` | `season_stage_id`, llave, desempate, `third_place` en `round_type`; `event_participants.tiebreak_score` |
| M13 | F5 | `2026_10_19_090200_create_season_stage_results_table` | Podio directo |
| M14 | F5 | `2026_10_19_090300_add_discipline_rules_to_games_editions_table` | `yellow_cards_limit`, `captain_red_matches` |
| M15 | F6 | `2026_10_24_090000_create_edition_awards_table` | Podios con puntos (derivado) |
| M16 | F6 | `2026_10_24_090100_add_total_points_to_games_edition_medals_table` | `total_points` |
| M17 | D17 | `2026_10_25_090000_simplify_games_editions_table` | − `host_venue_id`, − `yellow_cards_limit`, − `captain_red_matches`; fechas obligatorias |
| M18 | D18 | `2026_10_25_090100_add_standard_stages_to_sports_table` | `sports.standard_stages`; ajedrez = `podium_series` |
| M19 | D20 | `2026_10_25_090200_add_resolution_to_players_table` | `resolution_path`, `resolution_uploaded_at`, `resolution_uploaded_by` |
| M20 | D21 | `2026_10_25_090300_add_director_name_to_teams_table` | `teams.director_name` |
| M21 | D22 | `2026_10_25_090400_add_registration_deadline_to_games_editions_table` | `games_editions.registration_deadline` |

Después de cada migración nueva: `php artisan migrate` en la base de trabajo.

## Inventario de tablas y columnas nuevas

| Tabla | Columnas (tipo; nulo; FK y borrado) | Únicos |
|---|---|---|
| `games_editions` (+/−) | `logo` string nulo; `organizer_id` → `users` RESTRICT; `start_date` y `end_date` obligatorias; − `host_venue_id` (M17). `yellow_cards_limit` y `captain_red_matches` (M14) se quitaron en M17 | — |
| `sports` (+) | `standard_stages` enum `bracket_series`, `podium_series`, `podium_only` nulo (M18, D18) | — |
| `seasons` (−/+) | − `tournament_id`; `games_edition_id` obligatorio RESTRICT; `games_edition_event_id` nulo → `games_edition_events` RESTRICT; `category_id` nulo → `categories` RESTRICT; `gender` enum `M`,`F`,`X` nulo; `discipline_id` nulo → `disciplines` RESTRICT | (`games_edition_id`, `name`) |
| `games_edition_groups` | `games_edition_id` CASCADE; `name` string(50); `sort_order` int def. 0 | (`games_edition_id`, `name`) |
| `games_edition_teams` | `games_edition_id` RESTRICT; `team_id` RESTRICT; `group_id` nulo → `games_edition_groups` RESTRICT; `delegate_id` nulo → `users` SET NULL; `substitute_delegate_id` nulo → `users` SET NULL; `status` enum def. `registered`; `fee_paid` bool def. false; `observations` text nulo | (`games_edition_id`, `team_id`) |
| `teams` (−) | − `delegate_id` | — |
| `season_team` (−) | − `delegate_id` | — |
| `games_edition_events` | `games_edition_id` CASCADE; `sort_order` int; `label` string(150); `sport_id` nulo RESTRICT; `category_id` nulo RESTRICT; `gender_scope` enum def. `none`; `separate_podiums` bool def. true; `per_discipline` bool def. false; `pts_first`, `pts_second`, `pts_third` int nulos; `min_players`, `max_players` int nulos; `rules_blocking` bool def. false; `is_active` bool def. true | (`games_edition_id`, `label`) |
| `players` (+) | `gender` enum `M`,`F` nulo; `staff_type` enum nulo; `employment_type` enum nulo; `documents_verified_at` timestamp nulo; `resolution_path` string nulo, `resolution_uploaded_at` timestamp nulo, `resolution_uploaded_by` nulo → `users` RESTRICT (M19, D20) | — |
| `season_stages` | `season_id` CASCADE; `name` string(80); `sort_order` int; `mode` enum; `group_id` nulo → `games_edition_groups` SET NULL; `qualifiers` int nulo; `has_third_place` bool def. false | (`season_id`, `name`) |
| `matches` (+) | `season_stage_id` nulo → `season_stages` RESTRICT; `bracket_round` int nulo; `bracket_slot` int nulo; `next_match_id` nulo → `matches` SET NULL; `next_match_side` enum `home`,`away` nulo; `loser_next_match_id` nulo → `matches` SET NULL; `tiebreak_type` enum nulo; `round_type` + `third_place` | — |
| `event_participants` (+) | `tiebreak_score` int nulo | — |
| `season_stage_results` | `season_stage_id` CASCADE; `place` tinyint (1–3); `team_id` RESTRICT; `season_team_player_id` nulo RESTRICT; `recorded_by` → `users` RESTRICT | (`season_stage_id`, `place`, `team_id`) |
| `edition_awards` | `games_edition_id` CASCADE; `season_id` CASCADE; `games_edition_event_id` nulo SET NULL; `team_id` RESTRICT; `place` tinyint; `points` int def. 0 | — |
| `games_edition_medals` (+) | `total_points` int def. 0 | — |

## Inventario de código

### Enums (`app/Enums`)

| Enum | Casos (valor) | Fase |
|---|---|---|
| `EditionTeamStatus` | `Registered` (`registered`), `Withdrawn` (`withdrawn`), `Disqualified` (`disqualified`) | F2 |
| `GenderScope` | `Male` (`male`), `Female` (`female`), `Both` (`both`), `None` (`none`) | F3 |
| `StaffType` | `Teacher` (`teacher`), `Administrative` (`administrative`) | F4 |
| `EmploymentType` | `Appointed` (`appointed`), `Contracted` (`contracted`) | F4 |
| `StageMode` | `Matches` (`matches`), `DirectPodium` (`direct_podium`) | F5 |
| `StandardStages` | `BracketSeries` (`bracket_series`), `PodiumSeries` (`podium_series`), `PodiumOnly` (`podium_only`); `forFormat()` | D18 |
| `TiebreakType` | `ExtraTime` (`extra_time`), `Penalties` (`penalties`), `FreeThrows` (`free_throws`), `CoinToss` (`coin_toss`) | F5 |
| `RoundType` (+) | `ThirdPlace` (`third_place`) | F5 |

Todos con `label(): string` en español.

### Modelos (`app/Models`)

| Modelo | Tabla | Fase | Acción |
|---|---|---|---|
| `Tournament` | `tournaments` | F1 | Eliminar |
| `GamesEdition` | `games_editions` | F1 | + `organizer()`, `groups()`, `editionTeams()`, `events()` |
| `Season` | `seasons` | F1, F4 | − `tournament()`; + `event()`, `category()`, `discipline()` (F4), `stages()` (F5) |
| `Player` | `players` | F4 | + `gender`, `staff_type`, `employment_type` en `$fillable`; `documents_verified_at` solo con cast (fuera de `$fillable`) |
| `PlayerPolicy` | — | F4 | + `verifyDocuments(User, Player)` |
| `GamesEditionGroup` | `games_edition_groups` | F2 | Nuevo |
| `GamesEditionTeam` | `games_edition_teams` | F2 | Nuevo; `delegates(): Collection`, `hasDelegate(User): bool` |
| `Team`, `SeasonTeam` | — | F2 | − `delegate()`; + `Team::editionTeams()`; `SeasonTeam::effectiveDelegate()` se reemplaza por `editionTeam()`, `delegates(): Collection` y `hasDelegate(User): bool`. Versión SQL: `SeasonTeamPolicy::delegateConstraint(User)` |
| `GamesEditionEvent` | `games_edition_events` | F3 | Nuevo |
| `SeasonStage` | `season_stages` | F5 | Nuevo |
| `SeasonStageResult` | `season_stage_results` | F5 | Nuevo |
| `EditionAward` | `edition_awards` | F6 | Nuevo |

Ninguno de los modelos nuevos participa en una relación polimórfica. Si eso cambia, agregarlo a
`Relation::enforceMorphMap()` en `AppServiceProvider`.

### Services (`app/Services`) — firmas que otras fases consumen

| Service | Firma | Fase |
|---|---|---|
| `GamesEditionService` | `register(array $data, User $actor): GamesEdition` | F1 |
| | `scopeVisibleTo()` incluye desde F6 las olimpiadas que el usuario gestiona y aquellas donde es delegado de una delegación inscrita | F6 |
| | `update(GamesEdition $edition, array $data, User $actor): GamesEdition` | F1 |
| | `delete(GamesEdition $edition): bool\|string` | F1 |
| | `organizerCandidates(): Collection` | F1 |
| | `manages(User $actor, GamesEdition $edition): bool` | F1 |
| | `scopeManagedBy(Builder $query, User $actor): Builder` | F1 |
| | `scopeVisibleTo(Builder $query, User $actor): Builder` (ya existe) | F1 |
| `SeasonService` | `editionsFor(User $actor): Collection` (antes `tournamentsFor`); sin `register()` y `update()` limitado a `EDITABLE` (D19) | F1, D19 |
| `EditionGroupService` | `register(GamesEdition $edition, array $data, User $actor): GamesEditionGroup`; `update(GamesEditionGroup $group, array $data, User $actor): GamesEditionGroup`; `delete(GamesEditionGroup $group, User $actor): bool\|string` | F2 |
| `EditionTeamService` | `register(GamesEdition $edition, array $data, User $actor): GamesEditionTeam`; `update(GamesEditionTeam $entry, array $data, User $actor): GamesEditionTeam`; `delete(GamesEditionTeam $entry, User $actor): bool\|string`; `delegateOptions(): Collection`; `assignableTeams(User $actor): Builder`; `scopeDelegatedTo(Builder $teams, User $actor): Builder` | F2 |
| `DelegateAccountService` | `createQuickAccount(array $data, User $actor): User` (admin u organizador con olimpiada; rol forzado `delegado`) | F2 |
| `EditionEventService` | `register(GamesEdition $edition, array $data, User $actor): GamesEditionEvent`; `update(GamesEditionEvent $event, array $data, User $actor): GamesEditionEvent`; `delete(GamesEditionEvent $event, User $actor): bool\|string` (solo admin); `register()` y `update()` generan las competencias que falten (D19) | F3, D19 |
| `CompetitionGeneratorService` | `generate(GamesEdition $edition, User $actor): array{created: int, existing: int, skipped: int}` (`skipped` = filas activas sin deporte); constante `DEFAULT_PHASE` | F4 |
| `RosterRuleService` | `evaluate(Season $season, Team $team, Player $player): array{errors: string[], warnings: string[]}` (ya clasifica: `errors` si la fila es bloqueante, `warnings` si no); `violations(...): string[]` (sin clasificar); `isBlocking(Season): bool`; `referenceYear(Season): int`; `missingForMinimum(Season, int $activeCount): int` | F4 |
| `SeasonTeamService` | `ensureFor(Season $season, Team $team): SeasonTeam` | F4 |
| `SeasonTeamPlayerService` | `registerInCompetition(Season $season, Team $team, array $data, User $actor): SeasonTeamPlayer`; `canRegisterFor(User, Season, Team): bool`; `competitionsFor(User): Collection`; `delegationsFor(User, Season): Collection`. Se eliminan `seasonTeamsFor()` y `ageAtSeason()` | F4 |
| `PlayerRegistrationService` | `setDocumentsVerified(Player $player, bool $verified, User $actor): Player`; `canVerifyDocuments(User, Player): bool`. `register()`/`update()` solo aceptan los campos del formulario; `attachResolution(Player, UploadedFile, User): Player`, `removeResolution(Player, User): Player`, `canManageResolution(User, Player): bool` (D20) | F4, D20 |
| `StageService` | `register(Season $season, array $data, User $actor): SeasonStage`; `update(SeasonStage $stage, array $data, User $actor): SeasonStage`; `delete(SeasonStage $stage, User $actor): bool\|string`; `createStandardStages(Season $season, User $actor): Collection` (estructura según `standardStagesFor()`, D18); `createMissingStandardStages(iterable $seasons, User $actor): array`; `standardStagesFor(Season $season): StandardStages`; `winners(SeasonStage $stage): Collection` | F5, D18 |
| `BracketService` | `generate(SeasonStage $stage, array $seasonTeamIdsInSeedOrder, User $actor): Collection`; `advance(GameMatch $match): void`; `clear(SeasonStage $stage, User $actor): bool\|string`; `ensureCanReopen(GameMatch $match): void`; `seedOrder(int $size): array`; constante `REOPEN_BLOCKED` | F5 |
| `DirectPodiumService` | `save(SeasonStage $stage, array $places, User $actor): void` (`$places`: por puesto, una fila `['team_id' => int, 'season_team_player_id' => ?int]` o una lista de filas si el puesto es compartido; guardar reemplaza el podio; en una fase con serie solo admite delegaciones de esa serie, D18). Constante `PLACES` | F5 |
| `SuspensionService` | `generateForMatch()`: toda roja da 1 partido (D17 quitó la acumulación de amarillas y la sanción del capitán) | F5 |
| `ResultService` | `close()` acepta `tiebreak_type`, `tiebreak_winner_side`, `tiebreak_home`, `tiebreak_away`; errores de desempate en la clave `tiebreak` | F5 |
| `DelegationEnrollmentService` | `editionsFor(User)`, `delegationsFor(User, GamesEdition)`, `board(GamesEdition, Team)`, `kindOf(Season)`, `canEnroll()`/`ensureCanEnroll(User, Season, Team)`, `enroll(Season, Team, User): SeasonTeam`, `withdraw(SeasonTeam, User): bool\|string` | D22 |
| `GeneralRegistrationFormService` | `prepare(SeasonTeam): array`; `seasonTeamsOf(GamesEdition, Team): Collection`; `canPrint(User, SeasonTeam): bool` (= `SeasonTeamPlayerPolicy::managesSeasonTeam`); constante `COPIES` | D21 |
| `AwardService` | `rebuild(GamesEdition $edition): void`; `table(GamesEdition $edition): Collection`; `detail(GamesEdition $edition, Team $team): Collection` | F6 |

### Policies (`app/Policies`)

| Policy | Reglas | Fase |
|---|---|---|
| `TournamentPolicy` | Eliminar | F1 |
| `GamesEditionPolicy` | `viewAny`: admin, organizador. `view`/`update`: organizador asignado. `create`/`delete`: nadie (solo admin por `Gate::before`). `manage`: igual que `update`; la usan series, delegaciones y cuadro | F1 |
| `SeasonPolicy`, `SeasonTeamPolicy`, `SeasonTeamPlayerPolicy` | Pasan de `season->tournament->organizer_id` a `season->gamesEdition->organizer_id`; el delegado se resuelve con `SeasonTeam::hasDelegate()` | F1, F2 |
| `TeamPolicy` | El delegado ya no actualiza la delegación | F2 |

### Rutas (`routes/web.php`, dentro del grupo autenticado)

| URI | Nombre | Componente | Acceso | Fase |
|---|---|---|---|---|
| `/torneos` | `tournaments.index` | — | **Eliminar** | F1 |
| `/ediciones` | (antiguo `games-editions.index`, solo admin) | — | **Eliminar** | F1 |
| `/olimpiadas` | `games-editions.index` | `GamesEditions\Index` | `GamesEditionPolicy::viewAny` | F1 |
| `/series` | `edition-groups.index` | `EditionGroups\Index` | `manage` de la olimpiada elegida | F2 |
| `/delegaciones-inscritas` | `edition-teams.index` | `EditionTeams\Index` | `manage` de la olimpiada elegida | F2 |
| `/equipos` | `teams.index` | `Teams\Index` | Se rotula "Delegaciones"; sin delegado | F2 |
| `/puntajes` | `edition-events.index` | `EditionEvents\Index` | Ver: admin y organizador de la olimpiada. Editar: solo admin | F3 |
| `/temporadas` → `/competencias` | `seasons.index` (el nombre no cambia) | `Seasons\Index` | Usa `SelectsEdition`: solo las competencias de una olimpiada que el usuario gestiona | F4 |
| `/jugadores` → `/participantes` | `players.index` (el nombre no cambia) | `Players\Index` | Igual que antes | F4 |
| `/fases` | `season-stages.index` | `SeasonStages\Index` | `SeasonAccess::manages` | F5 |
| `/reportes/puntaje-general` | `reports.general-score` | `Reports\GeneralScore` | `reports.view`, acotado por rol | F6 |

Las pantallas que dependen de una olimpiada no llevan el id en la URI: usan el trait
`App\Livewire\Concerns\SelectsEdition` (F2), con la propiedad `public string $editionId` enlazada a
`?olimpiada=` y preseleccionada con la olimpiada activa más reciente que el usuario gestiona.

### Permisos (Spatie) — estado final

| Permiso | Admin | Organizador | Delegado | Cambio |
|---|---|---|---|---|
| `sports.manage` | ✅ | | | — |
| `venues.manage` | ✅ | ✅ | | — |
| `categories.manage` | ✅ | ✅ | | Organizador lo gana en F2 |
| `editions.manage` | ✅ | ✅ | | Renombrado de `tournaments.manage` en F1 |
| `seasons.manage` | ✅ | ✅ | | — |
| `teams.manage` | ✅ | ✅ | | Delegado lo pierde en F2 |
| `players.manage` | ✅ | | ✅ | — |
| `matches.manage` | ✅ | ✅ | | — |
| `matches.reopen` | ✅ | ✅ | | — |
| `reports.view` | ✅ | ✅ | ✅ | — |

Cada cambio de permisos va en **dos** sitios: `RolePermissionSeeder` y una migración de datos.

### Menú (`config/admin-menu.php`) — estado final

| Grupo | Entradas (ruta; visibilidad) |
|---|---|
| Dashboard | `dashboard` |
| Olimpiada | Olimpiadas (`games-editions.index`; `editions.manage`), Series (`edition-groups.index`; `editions.manage`), Delegaciones inscritas (`edition-teams.index`; `editions.manage`), Cuadro de puntaje (`edition-events.index`; `editions.manage`), Competencias (`seasons.index`; `seasons.manage`), Fases y llaves (`season-stages.index`; `matches.manage`) |
| Catálogos | Niveles (`categories.index`; `categories.manage`), Sedes (`venues.index`; `venues.manage`) |
| Participantes | Delegaciones (`teams.index`; `teams.manage`), Participantes (`players.index`; `players.manage`), Inscripciones (`season-teams.index`; se conserva para que el organizador retire o desactive una inscripción), Planteles (`rosters.index`), Fichas de inscripción (`registrations.index`) |
| Partidos | Sin cambios |
| Reportes | + Puntaje general (`reports.general-score`; `reports.view`) |
| Administración | Sin cambios |

### Seeders (`database/seeders`)

| Seeder | Contenido | Fase |
|---|---|---|
| `RolePermissionSeeder` | Matriz de permisos final | F1, F2 |
| `CategorySeeder` | Libre 25, Máster 40, Súper Máster 55 (género `X`). Reutiliza la fila más antigua con ese nombre o un nombre anterior (Master, Senior) y desactiva duplicados por género y categorías heredadas | F3 |
| `SportSeeder`, `DisciplineSeeder`, `EventTypeSeeder` | Catálogo de la especificación §5.4 (9 deportes y 3 actividades) | F3 |
| `Puno2026Seeder` | Olimpiada, 8 sedes, 4 series, 18 delegaciones, 26 filas del cuadro. Idempotente y no destructivo (`firstOrCreate`: no pisa lo que el admin ya cambió). No está en `DatabaseSeeder`: se corre a mano, después de los de catálogo. Constante `Puno2026Seeder::EDITION_NAME` | F3 |

### Pruebas (`tests/`)

| Archivo | Fase |
|---|---|
| `tests/Concerns/CreatesActors.php` (trait: `admin()`, `organizer()`, `delegate()`) | F0 |
| `tests/Concerns/BuildsCompetition.php` (trait: `buildCompetition()`, `registerDelegation()`, `rosterEntry()`) | F1, F2 |
| `tests/Feature/Smoke/RoutesSmokeTest.php` | F0, se amplía en cada fase |
| `tests/Feature/Editions/GamesEditionAuthorizationTest.php` | F1 |
| `tests/Feature/Editions/SeasonBelongsToEditionTest.php` | F1 |
| `tests/Feature/Editions/EditionGroupTest.php` | F2 |
| `tests/Feature/Editions/EditionTeamTest.php` | F2 |
| `tests/Feature/Editions/DelegateAccountTest.php` | F2 |
| `tests/Feature/Editions/DelegateScopeTest.php` | F2 |
| `tests/Feature/Editions/EditionScreensTest.php` (acciones Livewire de series y delegaciones) | F2 |
| `tests/Feature/Editions/EditionEventTest.php` | F3 |
| `tests/Feature/Editions/Puno2026SeederTest.php` | F3 |
| `tests/Feature/Competitions/CompetitionGeneratorTest.php` | F4 |
| `tests/Feature/Competitions/RosterRuleTest.php` | F4 |
| `tests/Concerns/BuildsBracket.php` (trait: `bootCompetition()`, `stage()`, `entrants()`, `bracket()`, `close()`, `reopen()`, `seats()`, `cardEvent()`) | F5 |
| `tests/Feature/Stages/BracketTest.php` | F5 |
| `tests/Feature/Stages/DirectPodiumTest.php` | F5 |
| `tests/Feature/Stages/TiebreakTest.php` | F5 |
| `tests/Feature/Stages/StandardStagesTest.php` | D18 |
| `tests/Feature/Stages/StageListingTest.php` (listado de fases en /fases) | D18 |
| `tests/Feature/Editions/PlayerResolutionTest.php` | D20 |
| `tests/Feature/Editions/GeneralRegistrationFormTest.php` | D21 |
| `tests/Feature/Editions/DelegationEnrollmentTest.php` | D22 |
| `tests/Feature/Stages/RedCardSuspensionTest.php` (antes `YellowCardSuspensionTest`, D17) | F5 |
| `tests/Feature/Scores/AwardServiceTest.php` | F6 |
| `tests/Feature/Smoke/ProductionConfigTest.php` | F7 |

### Comandos y configuración de producción (F7)

| Elemento | Qué hace |
|---|---|
| `php artisan db:backup [--prune-only]` (`app/Console/Commands/BackupDatabase.php`) | Volcado con `mysqldump` a `storage/app/backups/respaldo-AAAA-MM-DD_HHMMSS.sql.gz`; conserva los `BACKUP_KEEP` más recientes |
| `config/backup.php` | `mysqldump` (`MYSQLDUMP_PATH`), `directory`, `keep` |
| `scripts/backup-db.sh` | Envoltura para el cron del hosting |
| `routes/console.php` | `Schedule::command('db:backup')->dailyAt('23:00')` |
| `config/app.php` | `timezone` = `America/Lima` (`APP_TIMEZONE`); `trusted_proxies` (`TRUSTED_PROXIES`); `locale` por defecto `es` |
| `AppServiceProvider::configureProduction()` | HTTPS forzado en producción; proxies de confianza |
| `docs/despliegue.md` | Guía de despliegue, respaldo, restauración y tabla del simulacro |

## Foco de revisión

Lo que las bases implican y es más probable que falle en uso real. Cada línea tiene su prueba
en la fase indicada.

1. **Usuario con dos roles** (organizador y delegado a la vez): ve la unión de ambos alcances, no solo uno. Prueba en F2 (`DelegateScopeTest`).
2. **Delegado suplente**: puede todo lo que el titular; al quitarlo de la delegación pierde el acceso de inmediato. Prueba en F2.
3. **Participante nacido el 31/12/2001 y el 01/01/2002**: el primero entra a Libre en 2026, el segundo no (edad por año, no por fecha). Prueba en F4.
4. **Serie con 5 delegaciones y con 3**: la llave se genera con byes y ningún bye enfrenta a dos byes. Prueba en F5.
5. **Reabrir un resultado de una final ya puntuada**: el puntaje general se recalcula y no duplica puntos. Prueba en F6.
6. **Fila del cuadro sin puntos** (Danza, Estudiantina): el podio se registra y suma 0, sin error. Prueba en F6.
7. **Generar competencias dos veces, y tras agregar una fila**: no duplica y solo crea las nuevas. Prueba en F4.
