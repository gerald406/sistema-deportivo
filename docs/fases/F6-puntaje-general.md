# F6 — Puntaje general

**Objetivo:** cada podio suma a su delegación los puntos de su fila del cuadro, y el sistema
muestra la clasificación general por delegación, pública y siempre reconstruible.

**Decisiones que implementa:** D6; supuestos S4 y S5. **Requiere:** F5 cerrada.

## Archivos

| Acción | Ruta |
|---|---|
| Crear | Migraciones M15, M16 |
| Crear | `app/Models/EditionAward.php`, `database/factories/EditionAwardFactory.php` |
| Crear | `app/Services/AwardService.php` |
| Crear | `app/Livewire/Reports/GeneralScore.php`, `resources/views/livewire/reports/general-score.blade.php` |
| Crear | `tests/Feature/Scores/AwardServiceTest.php` |
| Eliminar | `app/Services/MedalService.php` (su papel lo cumple `AwardService`) |
| Modificar | `app/Models/GamesEditionMedal.php`, `GamesEdition.php` |
| Modificar | `app/Services/ResultService.php`, `DirectPodiumService.php`, `EditionEventService.php`, `EditionTeamService.php`, `PublicResultsService.php` |
| Modificar | `app/Livewire/Reports/Index.php`, `Public/PublicResults.php` y vistas |
| Modificar | `routes/web.php`, `config/admin-menu.php` |

Leer antes de empezar: `app/Services/MedalService.php`, `app/Livewire/Reports/Index.php`,
`app/Livewire/Public/PublicResults.php` y cada uso de `MedalService`
(`grep -rn MedalService app`).

## Interfaces

**Consume:** `SeasonStage`, `SeasonStageResult`, `GameMatch::stage()`, `RoundType::ThirdPlace` (F5);
`GamesEditionEvent::pointsFor()` (F3); `GamesEditionTeam` con su estado (F2).

**Produce:**

- `EditionAward`: `edition()`, `season()`, `event()`, `team()`.
- `AwardService::rebuild(GamesEdition $edition): void` — transaccional; borra y recrea `edition_awards` y `games_edition_medals` de esa olimpiada.
- `AwardService::table(GamesEdition $edition): Collection` — una fila por delegación inscrita (también las que tienen 0), con `position`, `team`, `group`, `gold`, `silver`, `bronze`, `total_points`, `disqualified`.
- `AwardService::detail(GamesEdition $edition, Team $team): Collection` — sus premios con competencia, puesto y puntos.
- Ruta `/reportes/puntaje-general` → `reports.general-score`.

## Reglas del cálculo

Para cada competencia activa de la olimpiada, el podio sale de su **última fase** (`sort_order` mayor):

| Caso | 1.º | 2.º | 3.º |
|---|---|---|---|
| Última fase de podio directo | Filas de `season_stage_results` con ese puesto | ídem | ídem |
| Última fase de partidos | Ganador del partido `final` cerrado | Perdedor de ese partido | Ganador del partido `third_place` cerrado; si no existe o no se jugó, nadie |
| Competencia sin fases, formato ranking o posta | Participantes con `position` 1, 2 o 3 en partidos jugados de ronda `final` o `regular` | | |
| Competencia sin fases, formato enfrentamiento | Ganador y perdedor del partido `final`; sin 3.º | | |

- La delegación del premio es la del plantel del participante, o la delegación misma.
- Puntos = `event.pointsFor(place)`; 0 si la competencia no tiene fila o la fila no tiene puntos.
- Un empate compartido da el puesto y los puntos completos a cada uno.
- `games_edition_medals`: por delegación, conteo de oros, platas y bronces y la suma de puntos.
- Orden de la tabla (S4): puntos, luego oros, luego platas, luego bronces, luego nombre. Mismos valores = misma posición.
- Una delegación descalificada (S5) conserva sus premios en el detalle, se marca "Descalificada" y va al final sin posición.

`rebuild()` se llama desde: `ResultService::afterResultChanged()` (cierre y reapertura),
`DirectPodiumService::save()`, `EditionEventService::update()` y `delete()` (cambio de puntos),
`EditionTeamService::update()` (cambio de estado).

## Tarea 6.1 — Cálculo

- [x] **Paso 1.** Migraciones M15 y M16, modelo y factory.
- [x] **Paso 2.** Escribir `AwardServiceTest`:

| Prueba | Afirmación |
|---|---|
| `test_direct_podium_awards_row_points` | Ciclismo Libre varones (20/16/12): las tres delegaciones reciben 20, 16 y 12 |
| `test_bracket_final_and_third_place` | Fútbol (24/20/16): campeón 24, subcampeón 20, ganador del tercer puesto 16; el perdedor del tercer puesto, nada |
| `test_no_bronze_without_third_place_match` | Final jugada y tercer puesto pendiente: solo 1.º y 2.º |
| `test_series_stage_does_not_award` | Ganar la final de una serie no da premio: solo cuenta la última fase |
| `test_row_without_points_counts_medal_but_zero_points` | Danza sin puntos: la delegación suma 1 oro y 0 puntos, sin error |
| `test_competition_without_row_awards_zero_points` | Ídem para una competencia manual sin fila |
| `test_shared_place_gives_full_points_to_each` | Dos terceros: ambos con los puntos del 3.º |
| `test_individual_award_goes_to_the_player_delegation` | El podio de un participante suma a su delegación |
| `test_ranked_competition_without_stages_uses_positions` | 100 m sin fases, tiempos cargados: posiciones 1–3 premiadas |
| `test_totals_accumulate_across_competitions` | Una delegación con oro en fútbol (24) y plata en vóley Libre (16): 40 puntos, 1 oro, 1 plata |
| `test_table_order_and_ties` | Orden por puntos, luego oros, platas y bronces; dos delegaciones idénticas comparten posición |
| `test_table_lists_delegations_with_zero` | Las 18 inscritas aparecen aunque no tengan premios |
| `test_disqualified_delegation_goes_last_without_position` | Marcada y al final |
| `test_reopening_a_final_recalculates_without_duplicates` | Tras reabrir la final, los premios de esa competencia desaparecen; al cerrar con otro ganador, los puntos cambian de delegación y el total global no se duplica |
| `test_changing_row_points_recalculates` | Editar la fila a 30/20/10 actualiza el total |
| `test_rebuild_is_idempotent` | Dos `rebuild()` seguidos dejan el mismo contenido |
| `test_inactive_competition_is_ignored` | Una competencia con `is_active = false` no suma |

- [x] **Paso 3.** Ejecutar: falla. Implementar `AwardService` y los cinco puntos de llamada. Reemplazar cada uso de `MedalService` y borrarlo. En verde.
- [x] **Paso 4.** `php artisan migrate`; `php artisan test`. Commit: `F6: Puntaje general derivado de los podios y del cuadro`.

## Tarea 6.2 — Pantallas

- [x] **Paso 1.** `Reports\GeneralScore`: selector de olimpiada acotado por rol (`GamesEditionService::scopeVisibleTo()`), tabla Pos., Delegación, Serie, Oro, Plata, Bronce, Puntos; al abrir una fila, el detalle de premios. Botón "Recalcular" solo para quien gestiona la olimpiada. Estilos de impresión para sacar la tabla en papel.
- [x] **Paso 2.** `Reports\Index`: la sección de medallero enlaza a la pantalla nueva.
- [x] **Paso 3.** `Public\PublicResults`: pestaña "Puntaje general" de la olimpiada elegida, sin datos personales.
- [x] **Paso 4.** Ruta, menú, prueba de humo. Pruebas añadidas a `AwardServiceTest`:

| Prueba | Afirmación |
|---|---|
| `test_delegate_sees_the_general_score_of_his_edition_only` | 200 con su olimpiada; no puede elegir otra |
| `test_public_page_shows_general_score` | La página pública contiene el nombre de la delegación líder y su total |
| `test_only_manager_can_recalculate` | Llamar a `recalculate` como delegado devuelve 403 |

- [x] **Paso 5.** `php artisan test`. Commit: `F6: Pantalla y vista pública del puntaje general`.

## Tarea 6.3 — Documentación

- [x] `CLAUDE.md`: reemplazar la descripción del medallero; fase marcada. Commit.

## Reglas que cumple

| Regla | Prueba |
|---|---|
| Medallas a 1.º, 2.º y 3.º (Art. 28) | `test_direct_podium_awards_row_points`, `test_bracket_final_and_third_place` |
| "Puntaje total por categorías" (Art. 28, pág. 9) | `test_totals_accumulate_across_competitions` |
| 3.º y 4.º se definen jugando (Art. 10) | `test_no_bronze_without_third_place_match` |
| Puntos vacíos hasta que la comisión decida (D6) | `test_row_without_points_counts_medal_but_zero_points` |
| El cuadro manda: cambiarlo recalcula (D7) | `test_changing_row_points_recalculates` |
| Descalificación (Art. 22) | `test_disqualified_delegation_goes_last_without_position` |
| Foco 5 y 6 del plan maestro | `test_reopening_a_final_recalculates_without_duplicates`, `test_row_without_points_counts_medal_but_zero_points` |

## Puerta de F6

G1–G6. Además, comparar a mano: cargar con `Puno2026Seeder` tres podios de prueba y verificar que
el total de la pantalla coincide con la suma hecha con el cuadro de la pág. 9. Registrar en "Estado".

## Notas de cierre (2026-10-13)

Cambios respecto al plan, hechos al implementar:

- **Llave de 3 participantes.** No tiene partido por el tercer puesto (F5), así que el 3.º es
  quien perdió la única semifinal. En cualquier otra llave, sin partido por el tercer puesto
  jugado no hay 3.º (Art. 10).
- **Puntos de llamada de `rebuild()`.** Además de los del plan: `StageService` (crear, editar o
  borrar una fase cambia cuál es la última) y `SeasonService::update()` y `delete()`
  (activar/desactivar una competencia o cambiar su fila). No se llama desde
  `EditionTeamService::update()` ni desde `EditionEventService::delete()`: el estado de la
  delegación lo lee `table()` en vivo, y una fila con competencias no se puede borrar.
- **`GamesEditionService::scopeVisibleTo()`** ahora incluye las olimpiadas que el usuario gestiona
  y aquellas donde es delegado, aunque su delegación aún no tenga plantel.
- **`/reportes`** conserva la tabla de posiciones por competencia; el medallero se reemplazó por
  un enlace a `/reportes/puntaje-general`. Menú: "Puntaje general" y "Posiciones".
- **Vista pública**: pestaña "Puntaje general" dentro de la competencia elegida (muestra el de
  su olimpiada). Solo delegaciones y puntos.
- La comparación a mano que pide la puerta quedó como prueba: `test_puno_table_matches_page_9_sums`
  (tres podios con los datos de `Puno2026Seeder`: 62, 54, 34 y 12 puntos).
- Pruebas añadidas fuera de las tablas: llave de 3, enfrentamiento sin fases, solo cuenta la
  última fase cuando cambian las fases.
- Los dos commits de la fase no separan limpio cálculo y pantallas (el primero trae solo los
  archivos nuevos; el segundo, las modificaciones y la baja de `MedalService`). El estado final
  es el verificado por la puerta.
