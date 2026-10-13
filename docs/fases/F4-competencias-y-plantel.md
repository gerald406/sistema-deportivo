# F4 — Competencias y plantel

**Objetivo:** las competencias se generan solas desde el cuadro de puntaje y el delegado registra
a sus participantes con las reglas de las bases (género, edad por año, topes) aplicadas como
datos. Al cerrar esta fase el sistema se puede abrir a los delegados.

**Decisiones que implementa:** D10, D11; supuesto S3. **Requiere:** F3 cerrada.

## Archivos

| Acción | Ruta |
|---|---|
| Crear | Migraciones M9, M10 |
| Crear | `app/Enums/StaffType.php`, `app/Enums/EmploymentType.php` |
| Crear | `app/Services/CompetitionGeneratorService.php`, `app/Services/RosterRuleService.php` |
| Crear | `tests/Feature/Competitions/CompetitionGeneratorTest.php`, `tests/Feature/Competitions/RosterRuleTest.php` |
| Modificar | `app/Models/Season.php`, `Player.php`, `GamesEditionEvent.php` |
| Modificar | `app/Services/SeasonService.php`, `SeasonTeamService.php`, `SeasonTeamPlayerService.php`, `PlayerRegistrationService.php`, `EditionEventService.php` |
| Modificar | `app/Livewire/Seasons/Index.php`, `Rosters/Index.php`, `Players/Index.php` y sus vistas |
| Modificar | `database/factories/PlayerFactory.php`, `SeasonFactory.php` |
| Modificar | `routes/web.php`, `config/admin-menu.php`, vistas con la palabra "Temporada" |

Leer antes de empezar: `app/Livewire/Rosters/Index.php`, `app/Livewire/Players/Index.php`,
`app/Services/PlayerRegistrationService.php`, `app/Services/SeasonTeamService.php` completos.

## Interfaces

**Consume:** `GamesEditionEvent::genders()`, `pointsFor()` (F3); `GamesEditionTeam::hasDelegate()`,
`SeasonTeam::hasDelegate()` (F2); `GamesEditionService::manages()` (F1).

**Produce:**

- `Season`: `$fillable` + `games_edition_event_id`, `category_id`, `gender`, `discipline_id`; `event(): BelongsTo`, `category(): BelongsTo`, `discipline(): BelongsTo`; cast `gender` → `Gender`.
- `Player`: `$fillable` + `gender`, `staff_type`, `employment_type`, `documents_verified_at`; casts a `Gender`, `StaffType`, `EmploymentType`, `datetime`.
- `CompetitionGeneratorService::generate(GamesEdition $edition, User $actor): array{created: int, existing: int}`.
- `RosterRuleService::evaluate(Season $season, Team $team, Player $player): array{errors: string[], warnings: string[]}`.
- `RosterRuleService::referenceYear(Season $season): int` — año de `gamesEdition.start_date`; si no hay, el año actual.
- `SeasonTeamService::ensureFor(Season $season, Team $team): SeasonTeam`.
- `SeasonTeamPlayerService::registerInCompetition(Season $season, Team $team, array $data, User $actor): SeasonTeamPlayer`.
- Ruta `/competencias` con el nombre `seasons.index` (el nombre no cambia).

## Reglas del generador

Para cada fila activa del cuadro con deporte:

- Un `Season` por cada género de `genders()`; si `per_discipline` es verdadero, además uno por cada disciplina activa del deporte.
- Identidad (para no duplicar): `games_edition_id` + `games_edition_event_id` + `gender` + `discipline_id`.
- Nombre: `{Deporte}[ — {Prueba}] {Nivel} {Damas|Varones|Mixto}`, sin la parte que no aplique. Ejemplos: `Fútbol Libre Varones`, `Atletismo — 100 metros Máster Damas`, `Danza Libre`.
- Estado `active`, `is_active` verdadero, `start_date` y `end_date` de la olimpiada, y una fase de puntaje `Fase única` con los valores por defecto de la tabla.
- Una fila sin deporte no genera competencia y se cuenta aparte en el mensaje al usuario (las 26 filas de Puno 2026 tienen deporte o actividad, S6).
- Nunca borra ni modifica una competencia existente. El Art. 1 de las bases no ofrece todas las pruebas de atletismo en todos los niveles: el organizador **desactiva** las que no corresponden (no las borra, o el generador las volvería a crear).

## Reglas del plantel (`RosterRuleService::evaluate`)

| Código | Regla | Mensaje |
|---|---|---|
| R1 | Si la competencia tiene género `M` o `F`, el participante debe tener ese género. Sin género registrado, falla | "La competencia es de {Damas/Varones} y el participante figura como {…}." |
| R2 | Si el nivel tiene edad mínima: `referenceYear − año de nacimiento ≥ min_age`. Sin fecha de nacimiento, falla | "Nacido en {año}: tendría {n} en {ref}; {Nivel} exige {min}." |
| R3 | Si la fila tiene `max_players`: el plantel activo de la delegación en la competencia no lo supera | "El plantel ya tiene {n} de {max} permitidos." |
| R4 | El participante no está en el plantel de otra delegación en ninguna competencia de la misma olimpiada | "Ya está inscrito con {Delegación} en esta olimpiada." |

Todas van a `errors`. Quien llama decide: si `event.rules_blocking` es verdadero (o la competencia
no tiene fila), lanza `ValidationException`; si es falso, registra al participante y muestra los
mensajes como advertencia. `min_players` nunca bloquea: `Rosters\Index` muestra un aviso
"Faltan {n} para el mínimo" por plantel. Se conservan como bloqueos fijos, por integridad: un
participante no puede estar dos veces en la misma competencia, camiseta única y un solo capitán.

`ensureFor()` exige que la delegación esté inscrita en la olimpiada con estado `registered`; si no,
`ValidationException` ("La delegación no está inscrita en la olimpiada" / "…está retirada o
descalificada").

## Tarea 4.1 — Generador de competencias

- [ ] **Paso 1.** Migración M9; modelo `Season`; `SeasonFactory`.
- [ ] **Paso 2.** Escribir `CompetitionGeneratorTest`:

| Prueba | Afirmación |
|---|---|
| `test_row_for_both_genders_creates_two_competitions` | Fila Voleibol Libre `both` + podios separados → 2 competencias (`M` y `F`) |
| `test_row_for_one_gender_creates_one` | Fila Fútbol Libre `male` → 1 competencia con `gender = M` |
| `test_combined_podium_creates_a_mixed_competition` | `both` sin podios separados → 1 competencia con `gender = X` |
| `test_per_discipline_row_creates_one_per_discipline` | Atletismo Libre `both` con 8 pruebas activas → 16 competencias, cada una con su `discipline_id` |
| `test_generation_is_idempotent` | Segunda ejecución: `created = 0`, mismo conteo de `seasons` y de `scoring_configs` |
| `test_new_row_only_adds_its_competitions` | Tras agregar una fila, `created` es solo lo de esa fila |
| `test_deactivated_competition_is_not_recreated_or_reactivated` | Una competencia con `is_active = false` sigue así tras regenerar |
| `test_row_without_sport_is_skipped` | No crea competencia para esa fila |
| `test_only_edition_manager_generates` | Otro organizador: `AuthorizationException` |
| `test_puno_table_generates_expected_total` | Con `Puno2026Seeder`: el total coincide con la suma calculada en la prueba a partir de las filas (no un número fijo escrito a mano) |
| `test_row_with_competitions_is_not_deleted` | `EditionEventService::delete()` devuelve un string |

- [ ] **Paso 3.** Ejecutar: falla. Implementar. Ejecutar: en verde.
- [ ] **Paso 4.** `Seasons\Index`: título "Competencias", selector de olimpiada (`SelectsEdition`), botón "Generar competencias" con confirmación y toast "Creadas: n. Ya existían: m.", filtros por deporte, nivel y género. El formulario manual conserva los campos actuales y agrega nivel, género y fila del cuadro (opcionales).
- [ ] **Paso 5.** Ruta `/competencias`; menú; en vistas y menú, "Temporada(s)" → "Competencia(s)" y "Jugador(es)" → "Participante(s)" en títulos, etiquetas y mensajes visibles.
- [ ] **Paso 6.** `php artisan migrate`; `php artisan test`. Commit: `F4: Competencias generadas desde el cuadro de puntaje`.

## Tarea 4.2 — Datos del participante

- [ ] **Paso 1.** Migración M10, enums (`label()`: Docente, Administrativo; Nombrado, Contratado), modelo, factory.
- [ ] **Paso 2.** Pruebas en `RosterRuleTest` (primera parte):

| Prueba | Afirmación |
|---|---|
| `test_delegate_registers_participant_with_gender_and_staff_data` | `PlayerRegistrationService` guarda `gender`, `staff_type`, `employment_type` |
| `test_only_edition_manager_or_admin_marks_documents_verified` | El delegado no puede fijar `documents_verified_at`; el organizador sí |

- [ ] **Paso 3.** `Players\Index`: campos género (obligatorio al crear), tipo y condición; casilla "Documentos verificados" visible solo para admin y organizador. `php artisan test`. Commit: `F4: Género, tipo y condición del participante`.

## Tarea 4.3 — Reglas del plantel

- [ ] **Paso 1.** Completar `RosterRuleTest`:

| Prueba | Afirmación |
|---|---|
| `test_age_is_computed_by_birth_year` | Olimpiada 2026, nivel Libre (25): nacido el 31/12/2001 pasa; nacido el 01/01/2002 falla R2 |
| `test_older_participant_can_play_a_lower_level` | Nacido en 1970 pasa en Libre, Máster y Súper Máster |
| `test_master_and_super_master_thresholds` | Nacido en 1986 pasa en Máster; en 1987 falla. Nacido en 1971 pasa en Súper Máster; en 1972 falla |
| `test_gender_must_match_the_competition` | Varón en competencia `F` falla R1; en competencia `X` pasa |
| `test_missing_birth_date_or_gender_fails` | Cada ausencia produce su error |
| `test_max_players_is_enforced` | Con tope 3 y 3 activos, el cuarto falla R3; un inactivo no cuenta |
| `test_one_delegation_per_participant_in_the_edition` | Inscrito con la delegación A en fútbol, falla R4 con la delegación B en ajedrez; con la misma A en ajedrez pasa |
| `test_same_participant_in_several_competitions_of_his_delegation` | Mismo participante en fútbol Libre, vóley Libre y 100 m de su delegación: sin errores |
| `test_non_blocking_rules_register_with_warnings` | Con `rules_blocking = false`, `registerInCompetition()` crea la ficha aunque falle R2 |
| `test_blocking_rules_reject` | Con `rules_blocking = true`, lanza `ValidationException` y no crea la ficha |
| `test_first_participant_creates_the_delegation_entry` | `registerInCompetition()` crea el `SeasonTeam` si no existía; la segunda llamada reutiliza el mismo |
| `test_unregistered_or_disqualified_delegation_is_rejected` | `ensureFor()` lanza `ValidationException` |
| `test_foreign_delegate_cannot_register_in_competition` | `AuthorizationException` |
| `test_suspended_participant_keeps_suspended_status` | Un participante con sanción pendiente no puede pasar a `active` (regla existente, se conserva) |

- [ ] **Paso 2.** Ejecutar: falla. Implementar `RosterRuleService`, `ensureFor()`, `registerInCompetition()`. `SeasonTeamPlayerService::register()` deja de llamar a `ensureAgeFitsCategory()` y usa `evaluate()`.
- [ ] **Paso 3.** `Rosters\Index`: el delegado elige competencia (de las olimpiadas donde su delegación está inscrita) y, si tiene más de una delegación, la delegación; agrega participantes; ve advertencias por participante y el aviso de mínimo. Admin y organizador eligen además cualquier delegación inscrita.
- [ ] **Paso 4.** `php artisan test`. Commit: `F4: Reglas de plantel configurables (género, edad por año, topes, una delegación)`.

## Tarea 4.4 — Documentación

- [ ] `CLAUDE.md`: regla 6 y punto 4 del flujo de permisos (la inscripción a una competencia se crea sola); fase marcada. Commit.

## Reglas que cumple

| Regla | Prueba |
|---|---|
| Niveles por año de nacimiento: 2001 / 1986 / 1971 (Esp. I, D10) | `test_age_is_computed_by_birth_year`, `test_master_and_super_master_thresholds` |
| Jugar en nivel menor y en varios deportes (D10) | `test_older_participant_can_play_a_lower_level`, `test_same_participant_in_several_competitions_of_his_delegation` |
| Damas y varones por separado (VI) | `test_gender_must_match_the_competition` |
| Topes de plantel (Esp. 4, 6, 7, 8, 9) | `test_max_players_is_enforced` |
| Participantes de su institución (VII.d, Art. 22) | `test_one_delegation_per_participant_in_the_edition` |
| Advertir, no bloquear, salvo configuración (D11) | `test_non_blocking_rules_register_with_warnings`, `test_blocking_rules_reject` |
| Docentes y administrativos, nombrados o contratados (VI) | `test_delegate_registers_participant_with_gender_and_staff_data` |
| Foco 3 y 7 del plan maestro | `test_age_is_computed_by_birth_year`, `test_generation_is_idempotent`, `test_new_row_only_adds_its_competitions` |

## Puerta de F4

G1–G6. G5 agrega `Temporada|temporada` en `resources/views` y `config`: 0 coincidencias. Registrar
en "Estado". **Hito:** con F4 y la tarea 7.1 cerradas, se publica y se abre a los delegados.
