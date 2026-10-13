# F2 — Delegaciones, delegados y series

**Objetivo:** el organizador conforma las series A–D, inscribe las delegaciones en la olimpiada y
les asigna delegado titular y suplente. El delegado queda definido en un solo lugar.

**Decisiones que implementa:** D3, D4, D5, D9. **Requiere:** F1 cerrada.

## Archivos

| Acción | Ruta |
|---|---|
| Crear | Migraciones M4, M5, M6, M7 |
| Crear | `app/Enums/EditionTeamStatus.php` |
| Crear | `app/Models/GamesEditionGroup.php`, `app/Models/GamesEditionTeam.php` |
| Crear | `app/Services/EditionGroupService.php`, `app/Services/EditionTeamService.php` |
| Crear | `app/Livewire/Concerns/SelectsEdition.php` |
| Crear | `app/Livewire/EditionGroups/Index.php`, `resources/views/livewire/edition-groups/index.blade.php` |
| Crear | `app/Livewire/EditionTeams/Index.php`, `resources/views/livewire/edition-teams/index.blade.php` |
| Crear | `database/factories/GamesEditionGroupFactory.php`, `database/factories/GamesEditionTeamFactory.php` |
| Crear | `tests/Feature/Editions/EditionGroupTest.php`, `EditionTeamTest.php`, `DelegateAccountTest.php`, `DelegateScopeTest.php` |
| Modificar | `app/Models/GamesEdition.php`, `Team.php`, `SeasonTeam.php` |
| Modificar | `app/Services/DelegateAccountService.php`, `TeamRegistrationService.php`, `SeasonTeamService.php`, `SeasonTeamPlayerService.php`, `PlayerCardService.php`, `RegistrationFormService.php`, `RosterReportService.php`, `DashboardService.php`, `GameMatchService.php`, `EventLineupService.php`, `PlayerRegistrationService.php` |
| Modificar | `app/Policies/TeamPolicy.php`, `SeasonTeamPolicy.php`, `SeasonTeamPlayerPolicy.php`, `EventLineupPolicy.php` |
| Modificar | `app/Livewire/Teams/Index.php` y vista; `app/Livewire/SeasonTeams/Index.php` y vista |
| Modificar | `routes/web.php`, `config/admin-menu.php`, `database/seeders/RolePermissionSeeder.php`, `database/factories/TeamFactory.php` |

Antes de empezar: `grep -rn "delegate" app resources/views --include=*.php` y anotar en el commit
de la tarea 2.3 cada archivo tocado. Ninguna referencia a `teams.delegate_id`,
`season_team.delegate_id` ni `effectiveDelegate` puede sobrevivir a la fase.

## Interfaces

**Consume:** `GamesEditionService::manages()`, `scopeManagedBy()`; `GamesEditionPolicy::manage` (F1).

**Produce:**

- `GamesEdition::groups(): HasMany`, `GamesEdition::editionTeams(): HasMany`.
- `GamesEditionGroup`: `edition()`, `editionTeams()`.
- `GamesEditionTeam`: `edition()`, `team()`, `group()`, `delegate()`, `substituteDelegate()`; cast `status` → `EditionTeamStatus`; `hasDelegate(User $user): bool` (verdadero si es titular o suplente).
- `SeasonTeam::editionTeam(): ?GamesEditionTeam` (por `season.games_edition_id` + `team_id`); `SeasonTeam::delegates(): Collection` (titular y suplente, sin nulos); `SeasonTeam::hasDelegate(User $user): bool`.
- `EditionGroupService`, `EditionTeamService`, `DelegateAccountService` con las firmas del plan maestro.
- `SeasonTeamPolicy::delegateConstraint(User $user): Closure` — versión SQL de `SeasonTeam::hasDelegate()`; reemplaza a `effectiveDelegateConstraint()`.
- `EditionTeamService::assignableTeams(User $actor): Builder` — el organizador solo inscribe delegaciones a su cargo (decisión tomada al implementar: el catálogo de delegaciones es global y, sin esto, un organizador veía e inscribía las de otro).
- `Tests\Concerns\BuildsCompetition::registerDelegation(GamesEdition, Team, ?User $delegate, ?User $substitute, array $attributes = []): GamesEditionTeam`.
- `EditionTeamService::scopeDelegatedTo(Builder $teams, User $actor): Builder`: delegaciones donde el usuario es titular o suplente en alguna olimpiada.
- Trait `SelectsEdition`: `public string $editionId = ''` con `#[Url(as: 'olimpiada')]`; `mountSelectsEdition(): void` preselecciona la olimpiada activa más reciente que el usuario gestiona; `edition(): ?GamesEdition` la busca con `scopeManagedBy()`; `editionOptions(): Collection`.
- Rutas `/series` → `edition-groups.index`, `/delegaciones-inscritas` → `edition-teams.index`.

## Tarea 2.1 — Series

- [x] **Paso 1.** Migración M4 y modelo `GamesEditionGroup` con su factory.
- [x] **Paso 2.** Escribir `EditionGroupTest`:

| Prueba | Afirmación |
|---|---|
| `test_organizer_creates_groups_in_own_edition` | El organizador asignado crea "Serie A" con `sort_order` 1 |
| `test_other_organizer_cannot_create_groups` | `register()` de otro organizador lanza `AuthorizationException` |
| `test_group_name_is_unique_per_edition` | Segunda "Serie A" en la misma olimpiada lanza `ValidationException` en `form.name`; en otra olimpiada se crea |
| `test_group_with_delegations_is_not_deleted` | `delete()` devuelve un string si hay delegaciones en la serie (esta prueba se completa en la tarea 2.2) |
| `test_empty_group_is_deleted` | `delete()` devuelve `true` |

- [x] **Paso 3.** Ejecutar: falla. Implementar `EditionGroupService`. Ejecutar: en verde (salvo la que depende de 2.2).
- [x] **Paso 4.** Trait `SelectsEdition`, componente `EditionGroups\Index` (patrón de CRUD del proyecto: listado ordenable por `sort_order` y `name`, modal crear/editar, borrado con `confirmDelete`), vista, ruta y entrada de menú "Series".
- [x] **Paso 5.** Agregar `edition-groups.index` a `RoutesSmokeTest::adminRoutes()`.
- [x] **Paso 6.** `php artisan migrate`; `php artisan test`. Commit: `F2: Series de la olimpiada`.

## Tarea 2.2 — Delegaciones inscritas con titular y suplente

- [x] **Paso 1.** Migración M5, enum `EditionTeamStatus`, modelo `GamesEditionTeam` y factory.
- [x] **Paso 2.** Escribir `EditionTeamTest`:

| Prueba | Afirmación |
|---|---|
| `test_organizer_registers_a_delegation_with_both_delegates` | Crea la fila con serie, titular y suplente; estado `registered` |
| `test_delegation_is_registered_once_per_edition` | La segunda inscripción de la misma delegación lanza `ValidationException` en `form.team_id` |
| `test_delegates_must_have_the_delegate_role` | Un titular o suplente sin rol `delegado` lanza `ValidationException` |
| `test_titular_and_substitute_must_differ` | El mismo usuario en ambos campos lanza `ValidationException` en `form.substitute_delegate_id` |
| `test_group_must_belong_to_the_same_edition` | Una serie de otra olimpiada lanza `ValidationException` en `form.group_id` |
| `test_other_organizer_cannot_register` | `AuthorizationException` |
| `test_delegation_with_rosters_is_not_deleted` | Con algún `SeasonTeam` de esa delegación en la olimpiada, `delete()` devuelve un string |
| `test_delegation_can_be_disqualified` | `update()` con estado `disqualified` lo guarda |

- [x] **Paso 3.** Ejecutar: falla. Implementar `EditionTeamService`. Ejecutar: en verde. Completar `test_group_with_delegations_is_not_deleted`.
- [x] **Paso 4.** Componente `EditionTeams\Index`: listado con filtros por serie y estado; modal con delegación (selector de `assignableTeams()`), serie, titular y suplente (selectores de `delegateOptions()`), estado, "pago registrado" y observaciones. Vista, ruta y menú "Delegaciones inscritas".
- [x] **Paso 5.** Agregar `edition-teams.index` a la prueba de humo. `php artisan migrate`; `php artisan test`. Commit: `F2: Delegaciones inscritas con delegado titular y suplente`.

## Tarea 2.3 — El delegado en un solo lugar

- [x] **Paso 1.** Escribir `DelegateScopeTest`:

| Prueba | Afirmación |
|---|---|
| `test_titular_manages_the_roster_of_his_delegation` | `SeasonTeamPlayerPolicy::managesSeasonTeam()` es verdadero para el titular |
| `test_substitute_has_the_same_access` | Ídem para el suplente |
| `test_foreign_delegate_is_forbidden` | Falso para un delegado de otra delegación; `SeasonTeamPlayerService::register()` lanza `AuthorizationException` |
| `test_removed_substitute_loses_access` | Tras quitarlo con `EditionTeamService::update()`, `managesSeasonTeam()` es falso |
| `test_delegate_cannot_edit_or_create_delegations` | `can('update', $team)` y `can('create', Team::class)` son falsos para el delegado; `teams.index` le devuelve 403 |
| `test_user_with_both_roles_gets_the_union` | Un usuario organizador de la olimpiada X y delegado en la olimpiada Y ve en `/planteles` los planteles de ambas |
| `test_delegate_sees_only_his_delegation_rosters` | En `rosters.index`, el delegado ve su plantel y no el de otra delegación |

- [x] **Paso 2.** Ejecutar: falla.
- [x] **Paso 3.** Implementar `SeasonTeam::editionTeam()`, `delegates()`, `hasDelegate()`. Reemplazar cada uso de `effectiveDelegate()`, `team.delegate`, `teams.delegate_id` y `season_team.delegate_id` por ellos o por `EditionTeamService::scopeDelegatedTo()`. Los PDF (carnet, ficha, padrón) muestran al titular.
- [x] **Paso 4.** `Teams\Index` y su vista: quitar el selector de delegado y el alta rápida (se mueve a `EditionTeams\Index`); título "Delegaciones". `SeasonTeams\Index`: quitar el delegado de temporada.
- [x] **Paso 5.** `TeamPolicy`: `update` solo para organizador con `teams.manage` que gestiona la delegación. `SeasonTeamPlayerPolicy`: la rama del delegado exige `players.manage` y `SeasonTeam::hasDelegate()`.
- [x] **Paso 6.** Migraciones M6 y M7; `RolePermissionSeeder` con la matriz final; `TeamFactory` sin `delegate_id`.
- [x] **Paso 7.** `php artisan migrate`; `php artisan test`. Esperado: todo en verde, incluida la prueba de humo.
- [x] **Paso 8.** `grep -rnE "effectiveDelegate|delegate_id" app resources/views | grep -v "substitute_delegate_id\|games_edition_teams\|GamesEditionTeam\|EditionTeam"`: 0 coincidencias fuera de la inscripción de la delegación.
- [x] **Paso 9.** Commit: `F2: Delegado titular y suplente como única fuente; el delegado solo registra participantes`.

## Tarea 2.4 — El organizador crea cuentas de delegado

- [x] **Paso 1.** Escribir `DelegateAccountTest`:

| Prueba | Afirmación |
|---|---|
| `test_admin_creates_a_delegate_account` | El usuario creado tiene exactamente el rol `delegado` |
| `test_organizer_with_an_edition_creates_a_delegate_account` | Ídem con un organizador que tiene una olimpiada asignada |
| `test_organizer_without_edition_is_forbidden` | `AuthorizationException` |
| `test_delegate_cannot_create_accounts` | `AuthorizationException` |
| `test_role_cannot_be_injected` | Enviando `role => admin` en `$data`, el usuario creado solo tiene `delegado` |
| `test_organizer_cannot_use_the_users_crud` | `admin.users.index` devuelve 403 al organizador; `UserAccountService::register()` con el organizador como actor lanza `AuthorizationException` |

- [x] **Paso 2.** Ejecutar: falla. Modificar `DelegateAccountService::createQuickAccount()`. Conectar el alta rápida en el modal de `EditionTeams\Index` (nombre, correo, contraseña), visible para admin y organizador.
- [x] **Paso 3.** `php artisan test`: en verde. Commit: `F2: El organizador crea cuentas solo de delegado`.

## Tarea 2.5 — Documentación

- [x] `CLAUDE.md`: regla 3 (el organizador crea cuentas de delegado), regla 5 (titular y suplente de la delegación inscrita), puntos 3 a 5 del "Flujo de permisos", modelos nuevos. Commit: `Docs: reglas de delegados y series`.

## Reglas que cumple

| Regla | Prueba |
|---|---|
| Titular y suplente por delegación (VII.b, Art. 18, D9) | `test_organizer_registers_a_delegation_with_both_delegates`, `test_substitute_has_the_same_access` |
| Series por olimpiada (Art. 8) | `EditionGroupTest` |
| Una delegación, una inscripción (D5) | `test_delegation_is_registered_once_per_edition` |
| Descalificación (Art. 22) | `test_delegation_can_be_disqualified` |
| El organizador crea cuentas solo de delegado (D3) | `DelegateAccountTest` |
| El delegado solo registra participantes (D4) | `test_delegate_cannot_edit_or_create_delegations` |
| El delegado nunca toca otra delegación | `test_foreign_delegate_is_forbidden`, `test_delegate_sees_only_his_delegation_rosters` |
| Foco 1 y 2 del plan maestro | `test_user_with_both_roles_gets_the_union`, `test_removed_substitute_loses_access` |

## Puerta de F2

G1–G6. Además el paso 8 de la tarea 2.3 con 0 coincidencias. Registrar en "Estado".
