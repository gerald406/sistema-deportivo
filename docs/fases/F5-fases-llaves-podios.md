# F5 — Fases, llaves, podios y sanciones

**Objetivo:** cada competencia se juega como dicen las bases: series por eliminación simple, fase
final con partido por el tercer puesto y desempates; o podio directo donde solo se registra
1.º, 2.º y 3.º. Las amarillas se acumulan.

**Decisiones que implementa:** D13, D14. **Requiere:** F4 cerrada.

**Orden interno obligado por el calendario:** la tarea 5.1 (podio directo) debe estar en
producción antes del ciclismo del 23/10.

## Archivos

| Acción | Ruta |
|---|---|
| Crear | Migraciones M11, M12, M13, M14 |
| Crear | `app/Enums/StageMode.php`, `app/Enums/TiebreakType.php` |
| Crear | `app/Models/SeasonStage.php`, `app/Models/SeasonStageResult.php` y sus factories |
| Crear | `app/Services/StageService.php`, `BracketService.php`, `DirectPodiumService.php` |
| Crear | `app/Livewire/SeasonStages/Index.php`, `resources/views/livewire/season-stages/index.blade.php` |
| Crear | `tests/Feature/Stages/DirectPodiumTest.php`, `BracketTest.php`, `TiebreakTest.php`, `YellowCardSuspensionTest.php` |
| Modificar | `app/Enums/RoundType.php`, `app/Models/GameMatch.php`, `Season.php`, `GamesEdition.php`, `EventParticipant.php` |
| Modificar | `app/Services/ResultService.php`, `SuspensionService.php`, `GameMatchService.php`, `GamesEditionService.php` |
| Modificar | `app/Livewire/Results/Index.php`, `GamesEditions/Index.php` y vistas |
| Modificar | `database/seeders/Puno2026Seeder.php`, `routes/web.php`, `config/admin-menu.php` |

Leer antes de empezar, completos: `app/Services/ResultService.php`, `GameMatchService.php`,
`MatchdayService.php`, `app/Livewire/Results/Index.php`, y la migración de triggers
`2026_09_26_092500_add_discipline_sport_validation_triggers.php`.

## Interfaces

**Consume:** `Season::event()`, `SeasonTeam` (F4); `GamesEditionGroup` (F2); `SeasonAccess::manages()` (F1).

**Produce:**

- `SeasonStage`: `season()`, `group()`, `matches()`, `results()`; cast `mode` → `StageMode`.
- `Season::stages(): HasMany` ordenado por `sort_order`.
- `SeasonStageResult`: `stage()`, `team()`, `seasonTeamPlayer()`, `recorder()`.
- `GameMatch`: `$fillable` + columnas de M12; `stage()`, `nextMatch()`, `loserNextMatch()`; cast `tiebreak_type` → `TiebreakType`.
- `StageService`, `BracketService`, `DirectPodiumService` con las firmas del plan maestro.
- `StageService::winners(SeasonStage $stage): Collection` — ganadores de la última ronda de una fase de partidos (los clasificados de una serie).
- `ResultService`: el cierre de un resultado acepta `tiebreak_type` y `tiebreak_winner_side` (`home`/`away`) y, opcionales, `tiebreak_home` y `tiebreak_away`.
- `GamesEdition`: `yellow_cards_limit`, `captain_red_matches` (enteros, nulos = regla desactivada).
- Ruta `/fases` → `season-stages.index`.

## Reglas

**Fases estándar** (`createStandardStages`), solo si la competencia no tiene fases:

- Formato efectivo de enfrentamiento: una fase de partidos por cada serie de la olimpiada (nombre de la serie, `group_id`, `qualifiers = 1`) y una fase `Fase final` de partidos con `has_third_place = true`.
- Cualquier otro formato: una sola fase `Final` de podio directo.

El organizador puede editarlas, borrarlas (si no tienen partidos ni podio) o cambiar el modo;
por ejemplo, ajedrez a podio directo si lleva el suizo en papel (D14).

**Llave** (`BracketService::generate`), con `N` participantes en el orden que da el organizador:

- Tamaño = menor potencia de 2 ≥ `N`. Byes = tamaño − `N`. Reciben bye los primeros de la lista (C12: el organizador decide el orden).
- Emparejamiento de primera ronda: 1.º vs. último, 2.º vs. penúltimo, y así. Un cruce con bye no crea partido: el participante queda colocado en su casillero de la ronda siguiente.
- Se crean todos los partidos de todas las rondas, enlazados con `next_match_id` y `next_match_side`. Los de rondas futuras nacen sin participantes.
- `round_type`: última ronda `final`; la anterior `semifinal`; la anterior `quarterfinal`; el resto `regular`. Si la fase tiene `has_third_place` y hay semifinales, se crea un partido `third_place` y cada semifinal lo apunta con `loser_next_match_id`.
- Cada ronda usa una jornada propia `{Fase} — {Ronda}`, creada si no existe (`matches.matchday_id` es obligatorio).
- Exige `N ≥ 2`, participantes de la competencia, y que la fase no tenga partidos.

**Avance** (`BracketService::advance`), llamado desde `ResultService::afterResultChanged()`:

- Al cerrar con ganador: el ganador entra al partido siguiente en su lado; el perdedor, al de tercer puesto si existe.
- Al reabrir: se retira a ambos de los partidos siguientes si están `pending`; si alguno ya se jugó, la reapertura se rechaza con "La ronda siguiente ya se jugó: reábrela primero."

**Desempate:** en un partido con fase de modo partidos, un marcador empatado no se puede cerrar
sin `tiebreak_type` y lado ganador. El ganador queda con `position = 1`. En partidos sin fase, el
empate sigue permitido como hoy.

**Podio directo:** puestos 1 a 3; cada puesto admite varias filas (empate compartido); la
delegación debe estar inscrita con estado `registered`; el participante individual es opcional y
debe ser del plantel de esa delegación en la competencia. Guardar reemplaza el podio completo de
la fase.

**Sanciones:**

- Si `yellow_cards_limit` tiene valor: al cerrar un partido, cada participante cuyo total de `yellow_card` en la competencia alcanza un múltiplo del límite recibe una sanción de 1 partido con motivo `Acumulación de tarjetas amarillas`. Idempotente al re-cerrar.
- Si `captain_red_matches` tiene valor y el expulsado es capitán: la sanción automática por roja dura ese número de partidos.
- `Puno2026Seeder` fija `yellow_cards_limit = 2` y `captain_red_matches = 2` (Art. 14 y 15).

## Tarea 5.1 — Fases y podio directo

- [x] **Paso 1.** Migraciones M11 y M13, enum `StageMode`, modelos y factories.
- [x] **Paso 2.** Escribir `DirectPodiumTest`:

| Prueba | Afirmación |
|---|---|
| `test_standard_stages_for_ranked_sport` | Ciclismo: una fase `Final` de podio directo |
| `test_standard_stages_for_head_to_head_sport` | Fútbol con 4 series: 4 fases de partidos con su `group_id` y `qualifiers = 1`, más `Fase final` con tercer puesto |
| `test_standard_stages_are_not_duplicated` | Segunda llamada no crea nada |
| `test_manager_records_a_podium` | Guarda 1.º, 2.º y 3.º con `recorded_by` |
| `test_saving_again_replaces_the_podium` | Tras guardar otro podio, solo quedan las filas nuevas |
| `test_shared_place_is_allowed` | Dos delegaciones en el 3.er puesto |
| `test_team_must_be_registered_in_the_edition` | Delegación no inscrita o descalificada: `ValidationException` |
| `test_player_must_belong_to_that_team_roster` | Participante de otro plantel: `ValidationException` |
| `test_podium_is_rejected_on_a_matches_stage` | `ValidationException` |
| `test_only_edition_manager_records` | Delegado y otro organizador: `AuthorizationException` |
| `test_stage_with_podium_is_not_deleted` | `StageService::delete()` devuelve un string |

- [x] **Paso 3.** Ejecutar: falla. Implementar `StageService` y `DirectPodiumService`. En verde.
- [x] **Paso 4.** `SeasonStages\Index`: selector de competencia; lista de fases; botones "Crear fases estándar", "Nueva fase", "Registrar podio" (modal con tres bloques de puesto y búsqueda de delegación y participante). Ruta, menú "Fases y llaves", prueba de humo.
- [x] **Paso 5.** `php artisan migrate`; `php artisan test`. Commit: `F5: Fases de competencia y podio directo`.

## Tarea 5.2 — Llaves

- [x] **Paso 1.** Migración M12 (incluye ampliar el enum `round_type` y revisar que los triggers de disciplina sigan válidos), enum `RoundType::ThirdPlace`, modelo `GameMatch`.
- [x] **Paso 2.** Escribir `BracketTest`:

| Prueba | Afirmación |
|---|---|
| `test_four_participants` | 2 semifinales + final (+ tercer puesto si la fase lo pide); 4 partidos con `has_third_place` |
| `test_five_participants_get_three_byes` | Tamaño 8, 3 byes para los 3 primeros; primera ronda con 1 partido (4.º vs. 5.º); los 3 primeros ya figuran en la ronda siguiente |
| `test_three_participants_get_one_bye` | Tamaño 4; 1 partido en primera ronda (2.º vs. 3.º); el 1.º ya está en la final |
| `test_two_participants` | Un solo partido `final` |
| `test_no_match_pairs_two_byes` | Para `N` de 2 a 16, ningún partido de primera ronda queda sin participantes |
| `test_winner_advances_and_loser_goes_to_third_place` | Tras cerrar una semifinal, el ganador está en la final en el lado esperado y el perdedor en el partido de tercer puesto |
| `test_reopening_removes_advanced_participants` | Reabrir la semifinal vacía esos casilleros |
| `test_reopening_is_blocked_when_next_round_was_played` | Devuelve el mensaje de la sección Reglas |
| `test_stage_with_matches_cannot_be_regenerated` | `ValidationException` |
| `test_participants_must_belong_to_the_competition` | `ValidationException` |
| `test_series_winner_is_reported` | `StageService::winners()` devuelve al ganador de la final de la serie |
| `test_only_edition_manager_generates` | `AuthorizationException` |

- [x] **Paso 3.** Ejecutar: falla. Implementar `BracketService` y enganchar `advance()` en `ResultService::afterResultChanged()`. En verde.
- [x] **Paso 4.** `SeasonStages\Index`: botón "Generar llave" (lista ordenable de participantes; para una serie, precargada con las delegaciones de esa serie que tienen plantel; para la fase final, con `winners()` de cada serie) y vista de la llave por rondas.
- [x] **Paso 5.** `php artisan migrate`; `php artisan test`. Commit: `F5: Llaves de eliminación simple con byes, avance y tercer puesto`.

## Tarea 5.3 — Desempates

- [x] **Paso 1.** Escribir `TiebreakTest`:

| Prueba | Afirmación |
|---|---|
| `test_tied_bracket_match_requires_a_tiebreak` | Cerrar 1–1 sin desempate: `ValidationException` |
| `test_penalties_decide_the_winner` | 1–1 con `penalties`, gana `away` 4–3: `away` queda con `position = 1`, `tiebreak_score` guardado, y avanza en la llave |
| `test_coin_toss_needs_no_score` | `coin_toss` con lado ganador y sin marcador de desempate: válido |
| `test_tiebreak_is_rejected_when_not_tied` | 2–1 con desempate: `ValidationException` |
| `test_draw_is_still_allowed_outside_brackets` | Partido sin fase, 1–1: se cierra como empate |
| `test_reopening_clears_the_tiebreak` | Tras reabrir, `tiebreak_type` y `tiebreak_score` quedan nulos |

- [x] **Paso 2.** Ejecutar: falla. Implementar en `ResultService` y en el modal de `Results\Index` (bloque "Desempate" visible solo si el marcador está empatado y el partido es de llave). En verde.
- [x] **Paso 3.** Commit: `F5: Desempate por tiempo extra, penales, tiros libres o sorteo`.

## Tarea 5.4 — Sanciones

- [x] **Paso 1.** Migración M14; campos en `GamesEditions\Index` ("Amarillas para suspensión", "Partidos de sanción al capitán expulsado"), ambos opcionales.
- [x] **Paso 2.** Escribir `YellowCardSuspensionTest`:

| Prueba | Afirmación |
|---|---|
| `test_second_yellow_in_the_competition_suspends_one_match` | Límite 2: amarilla en el partido 1 y en el 2 → una sanción de 1 partido tras cerrar el 2 |
| `test_first_yellow_does_not_suspend` | Sin sanción |
| `test_fourth_yellow_suspends_again` | Límite 2: a la cuarta hay 2 sanciones en total |
| `test_no_accumulation_when_limit_is_empty` | Límite nulo: nunca hay sanción por amarillas |
| `test_yellows_from_another_competition_do_not_count` | Una amarilla en fútbol y otra en futsal: sin sanción |
| `test_reclosing_a_match_does_not_duplicate` | Reabrir y cerrar el partido 2 deja una sola sanción |
| `test_captain_red_card_uses_configured_matches` | Capitán con roja y `captain_red_matches = 2`: sanción de 2; no capitán: 1 |
| `test_red_card_default_is_one_match_for_everyone` | Con el campo nulo, capitán y no capitán: 1 |

- [x] **Paso 3.** Ejecutar: falla. Implementar en `SuspensionService`. En verde. Actualizar `Puno2026Seeder`.
- [x] **Paso 4.** `php artisan migrate`; `php artisan test`. Commit: `F5: Acumulación de amarillas y sanción al capitán, configurables`.

## Tarea 5.5 — Documentación

- [x] `CLAUDE.md`: fases, llaves, desempates y la nota de `SuspensionService`. Commit.

## Reglas que cumple

| Regla | Prueba |
|---|---|
| Series por eliminación simple, pasa el 1.º (Art. 8, 10) | `test_standard_stages_for_head_to_head_sport`, `test_series_winner_is_reported` |
| Ganadores por 1.º/2.º, perdedores por 3.º/4.º (Art. 10) | `test_winner_advances_and_loser_goes_to_third_place` |
| Series de 4 y 5 (bases; C12) | `test_five_participants_get_three_byes`, `test_four_participants` |
| Desempates (Esp. 6.2, 8.1, 9) | `TiebreakTest` |
| Roja: 1 partido; capitán: 2 (Art. 14) | `test_captain_red_card_uses_configured_matches` |
| 2 amarillas: 1 partido (Art. 15) | `test_second_yellow_in_the_competition_suspends_one_match` |
| Solo 1.º, 2.º y 3.º donde no hay partidos (D13) | `test_manager_records_a_podium` |
| Foco 4 del plan maestro | `test_no_match_pairs_two_byes`, `test_three_participants_get_one_bye` |

## Puerta de F5

G1–G6. Registrar en "Estado".

## Notas de cierre (2026-10-13)

Cambios respecto al plan, hechos al implementar:

- **Orden de los cruces.** Se usa el reparto habitual de cabezas de serie (`seedOrder()`:
  1-8, 4-5, 2-7, 3-6) en vez de emparejar en fila: el 1.º y el 2.º de la lista no se cruzan hasta
  la final y quien descansa enfrenta al ganador de un partido previo. Con 5 participantes: juegan
  4.º y 5.º; el ganador va contra el 1.º; el 2.º y el 3.º se enfrentan.
- **Tercer puesto con 3 participantes.** No se crea partido: solo hay una semifinal real y el
  tercero es quien la perdió. El partido por el tercer puesto existe desde 4 participantes.
- **`BracketService::clear()`** (no previsto): borra la llave de una fase si ningún partido tiene
  resultado, incidencias ni sanciones, para poder rehacer el sorteo.
- **Participantes de la llave = delegaciones** (`season_team`), también en ajedrez y tenis de
  mesa (1 participante por delegación según el cuadro).
- **`winners()`** devuelve al ganador de la final de la fase y, si `qualifiers >= 2`, también al
  finalista. Más de 2 clasificados por serie no está soportado en la precarga.
- **Medallero existente (`MedalService`)**: con fases, solo la última fase de la competencia da
  medallas (la final de una serie clasifica, no premia), el partido por el tercer puesto da el
  bronce y, si existe, perder la semifinal ya no lo da. F6 lo reemplaza por el puntaje general.
- **Amarillas**: se compara lo que el participante debe (`total / límite`) con las sanciones por
  acumulación que ya tiene en la competencia; así no duplica al re-cerrar ni depende del orden.
- En `/resultados`, un partido de llave que aún espera a un participante no abre el formulario.
- El mensaje del trigger de disciplina ya no se muestra tal cual (decía "temporada").
- La olimpiada de Puno ya existente en la base de trabajo recibió `yellow_cards_limit = 2` y
  `captain_red_matches = 2` con una actualización puntual (el seeder solo los fija al crearla).
- Pruebas añadidas fuera de las tablas: reglas de las fases, misma delegación dos veces en el
  podio, pantallas con una llave a medio jugar, medallas solo de la fase final, marcador del
  desempate coherente con el ganador, dos participantes contados por separado, y las de pantalla.

Limitaciones conocidas:

- Los podios directos todavía no suman a ningún medallero ni puntaje: eso es F6.
- La llave no programa fecha, hora ni sede: se editan en /partidos.
