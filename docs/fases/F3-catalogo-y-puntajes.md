# F3 — Catálogo y cuadro de puntaje

**Objetivo:** el admin configura el cuadro "Puntaje total por categorías" (pág. 9 de las bases) y
el sistema queda cargado con los datos reales de la olimpiada.

**Decisiones que implementa:** D6, D7; supuestos S1, S2 y S6. **Requiere:** F2 cerrada.

## Archivos

| Acción | Ruta |
|---|---|
| Crear | Migración M8 |
| Crear | `app/Enums/GenderScope.php` |
| Crear | `app/Models/GamesEditionEvent.php`, `database/factories/GamesEditionEventFactory.php` |
| Crear | `app/Services/EditionEventService.php` |
| Crear | `app/Livewire/EditionEvents/Index.php`, `resources/views/livewire/edition-events/index.blade.php` |
| Crear | `database/seeders/Puno2026Seeder.php` |
| Crear | `tests/Feature/Editions/EditionEventTest.php`, `tests/Feature/Editions/Puno2026SeederTest.php` |
| Modificar | `database/seeders/CategorySeeder.php`, `SportSeeder.php`, `DisciplineSeeder.php`, `EventTypeSeeder.php` |
| Modificar | `app/Models/GamesEdition.php`, `routes/web.php`, `config/admin-menu.php` |

## Interfaces

**Consume:** `SelectsEdition` (F2), `GamesEditionService::manages()` (F1).

**Produce:**

- `GamesEditionEvent`: `edition()`, `sport()`, `category()`, `seasons()` (F4); casts `gender_scope` → `GenderScope`, booleanos.
  - `genders(): array` — devuelve los géneros para los que genera podio: `Male` → `['M']`; `Female` → `['F']`; `Both` con `separate_podiums` → `['M', 'F']`; `Both` sin él → `['X']`; `None` → `[null]`.
  - `pointsFor(int $place): int` — 0 si el campo está vacío.
- `GamesEdition::events(): HasMany` ordenado por `sort_order`.
- `EditionEventService` (firmas del plan maestro). Las tres operaciones revalidan `hasRole('admin')`.
- Ruta `/puntajes` → `edition-events.index`.
- Slugs de deporte que usa F4: los 12 de la especificación §5.4 (`futbol`, `futsal`, `basquetbol`, `voley`, `ajedrez`, `tenis-de-mesa`, `tiro-al-sapo`, `ciclismo`, `atletismo`, `puntualidad`, `estudiantina`, `danza`).

## Tarea 3.1 — Cuadro de puntaje (solo admin)

- [x] **Paso 1.** Migración M8, enum `GenderScope` (`label()`: Varones, Damas, Damas y varones, Sin género), modelo y factory.
- [x] **Paso 2.** Escribir `EditionEventTest`:

| Prueba | Afirmación |
|---|---|
| `test_only_admin_creates_updates_and_deletes_rows` | Con el organizador asignado como actor, `register()`, `update()` y `delete()` lanzan `AuthorizationException`; con el admin funcionan |
| `test_organizer_can_view_the_table_but_not_edit` | `edition-events.index` devuelve 200 al organizador asignado y no muestra los botones de edición; llamar a `save` por Livewire devuelve 403 |
| `test_other_organizer_cannot_view` | 403 o lista vacía para un organizador de otra olimpiada |
| `test_points_are_optional` | Se guarda una fila con los tres puntajes vacíos; `pointsFor(1)` devuelve 0 |
| `test_points_must_be_non_negative_and_descending_when_present` | `pts_first` < `pts_second` lanza `ValidationException`; un valor negativo también |
| `test_label_is_unique_per_edition` | Etiqueta repetida lanza `ValidationException` en `form.label` |
| `test_min_players_cannot_exceed_max_players` | `ValidationException` en `form.max_players` |
| `test_genders_resolution` | Los cinco casos de `genders()` de la sección Interfaces |
| `test_row_with_competitions_is_not_deleted` | `delete()` devuelve un string si la fila tiene competencias (se completa en F4; hasta entonces verifica que una fila sin competencias devuelve `true`) |

- [x] **Paso 3.** Ejecutar: falla. Implementar `EditionEventService`. Ejecutar: en verde.
- [x] **Paso 4.** Componente `EditionEvents\Index`: tabla con N.º, Etiqueta, Deporte, Nivel, Alcance, 1.º, 2.º, 3.º, Mín., Máx., Bloqueante, y fila final con la suma de cada columna de puntos. Modal de edición con todos los campos del inventario de columnas, más las casillas "Podios separados por género" y "Una competencia por prueba". `boot()` no aborta: `save`, `delete` y `create` hacen `abort_unless(auth()->user()->hasRole('admin'), 403)`.
- [x] **Paso 5.** Ruta, menú "Cuadro de puntaje", prueba de humo. `php artisan migrate`; `php artisan test`.
- [x] **Paso 6.** Commit: `F3: Cuadro de puntaje por categorías, editable solo por el admin`.

## Tarea 3.2 — Catálogo y datos de Puno 2026

- [x] **Paso 1.** Escribir `Puno2026SeederTest` (corre `DatabaseSeeder` y luego `Puno2026Seeder`):

| Prueba | Afirmación |
|---|---|
| `test_levels_match_the_rules` | Existen exactamente los niveles activos Libre (25), Máster (40) y Súper Máster (55) |
| `test_sports_catalogue_is_complete` | Existen los 12 slugs de la sección Interfaces; atletismo tiene las 8 pruebas de la especificación §5.4 (`100 metros`, `400 metros`, `1500 metros`, `3000 metros`, `Salto largo`, `Lanzamiento de peso`, `Lanzamiento de disco`, `Posta 4x100`) y "Posta 4x100" tiene formato `team_relay` |
| `test_edition_groups_and_delegations` | 1 olimpiada, 4 series, 18 delegaciones inscritas; Serie A y C con 5, B y D con 4 |
| `test_scoring_table_matches_page_9` | 26 filas; las 22 primeras con los puntos exactos de la especificación §5.5 (comprobar al menos: fila 1 = 18/14/10, fila 11 = 24/20/16, fila 14 = 28/24/20, fila 20 = 24/20/16); las filas 23–26 con los tres puntos nulos |
| `test_roster_limits_match_the_rules` | Fútbol 7/18, Futsal 3/10, Básquetbol —/10, Voleibol 6/12, Sapo —/3, Ajedrez —/1 |
| `test_venues_are_loaded` | Las 8 sedes de la especificación §5.3 |
| `test_seeder_is_idempotent` | Correr `Puno2026Seeder` dos veces no cambia ningún conteo |

- [x] **Paso 2.** Ejecutar: falla.
- [x] **Paso 3.** Actualizar los seeders de catálogo con `updateOrCreate`. `CategorySeeder` desactiva (`is_active = false`) Sub-17, Damas y Varones si existen; no las borra. `EventTypeSeeder`: futsal y fútbol comparten los códigos `goal`, `yellow_card`, `red_card`, `red_card_indirect`; básquetbol: `points`, `foul`, `disqualification`.
- [x] **Paso 4.** Escribir `Puno2026Seeder` con los datos de la especificación §5. El organizador de la olimpiada es el primer usuario con rol `organizador`, o el admin si no hay ninguno. Las filas de atletismo llevan `per_discipline = true`.
- [x] **Paso 5.** Ejecutar: en verde. En la base de trabajo: `php artisan db:seed --class=CategorySeeder` (y los otros tres de catálogo) y `php artisan db:seed --class=Puno2026Seeder`.
- [x] **Paso 6.** Commit: `F3: Catálogo de deportes, niveles y datos iniciales de Puno 2026`.

## Reglas que cumple

| Regla | Prueba |
|---|---|
| D7: el cuadro lo configura el admin | `test_only_admin_creates_updates_and_deletes_rows`, `test_organizer_can_view_the_table_but_not_edit` |
| D6: puntos vacíos permitidos | `test_points_are_optional` |
| Cuadro de la pág. 9 cargado tal cual | `test_scoring_table_matches_page_9` |
| Niveles 25/40/55 (VI) | `test_levels_match_the_rules` |
| Series y delegaciones de las bases | `test_edition_groups_and_delegations` |
| Topes de plantel de las bases | `test_roster_limits_match_the_rules` |

## Puerta de F3

G1–G6. Registrar en "Estado". Mostrar al cliente la pantalla `/puntajes` y pedir confirmación de
los supuestos S1, S2 y S6 antes de F4.

## Notas de cierre (2026-10-13)

- S1, S2 y S6 confirmados por el cliente el 13/10.
- Pruebas añadidas fuera de la tabla: `test_admin_edits_a_row_from_the_screen`,
  `test_row_of_another_edition_is_out_of_reach_from_the_screen`,
  `test_legacy_and_duplicated_levels_are_deactivated_not_deleted`.
- La pantalla no usa `Sortable` ni paginación: el cuadro se lee en el orden de las bases.
- `SportSeeder` solo escribe `config` al crear el deporte. La prueba "100 metros llanos" se renombra a
  "100 metros". "Ajedrez clásico" ya no se siembra (ajedrez no compite por prueba).
- Los topes de Atletismo quedan vacíos en el cuadro: dependen de la prueba (1; posta 4). F4 los
  resuelve por formato de la prueba si hace falta.
- En la base de trabajo quedaron sin tocar tres deportes creados a mano que duplican pruebas de
  atletismo (`salto-largo`, `lanzamiento-de-peso`, `lanzamiento-de-disco`) y la olimpiada de prueba
  "Olimpiada Docente". El cliente decide si los borra.
