# Especificación — Sistema de la Olimpiada Puno 2026

Fuente: `docs/BasesOlimpiadasYunguyo2026.pdf` (XXVIII Juegos Deportivos Regionales de Docentes y
Administrativos de IES/EES de la Región Puno, Yunguyo, 28–30/10/2026). Este archivo es lo que el
sistema debe cumplir. Los planes de `docs/fases/F*.md` se escriben contra él.

Regla de oro: **no se asume ninguna regla que no esté en las bases.** Donde las bases se
contradicen o callan, el dato es configurable y queda vacío hasta que la comisión decida.

## 1. Vocabulario (interfaz ↔ código)

| En pantalla | En código | Tabla |
|---|---|---|
| Olimpiada | `GamesEdition` | `games_editions` |
| Delegación | `Team` | `teams` |
| Delegación inscrita | `GamesEditionTeam` | `games_edition_teams` |
| Serie | `GamesEditionGroup` | `games_edition_groups` |
| Nivel (Libre, Máster, Súper Máster) | `Category` | `categories` |
| Cuadro de puntaje (fila) | `GamesEditionEvent` | `games_edition_events` |
| Competencia | `Season` | `seasons` |
| Fase de una competencia | `SeasonStage` | `season_stages` |
| Participante | `Player` | `players` |
| Plantel | `SeasonTeamPlayer` | `season_team_player` |

"Torneo" y "Temporada" **no existen** en la interfaz. `Tournament` no existe en el código.

## 2. Decisiones del cliente

| # | Decisión | Fecha |
|---|---|---|
| D1 | `Tournament` se elimina ya (tabla, CRUD, Policy). La olimpiada es el eje y lleva el organizador | 12/10 |
| D2 | Solo el admin crea la olimpiada y le asigna organizador | 12/10 |
| D3 | El organizador crea cuentas, **solo con rol delegado**. El resto de cuentas las crea el admin | 12/10 |
| D4 | El delegado solo registra participantes; no edita nombre ni logo de su delegación | 12/10 |
| D5 | Cada casillero de las series es UNA delegación combinada (18 en total) | 12/10 |
| D6 | Puntos de Estudiantina, Danza, ciclismo damas y futsal damas Máster, y el desempate del puntaje general: vacíos hasta que la comisión decida | 12/10 |
| D7 | El cuadro "Puntaje total por categorías" (pág. 9) es una tabla que **configura el admin** | 12/10 |
| D8 | Software a medida: una sola estructura (Olimpiada); las reglas de reglamento son datos editables | 12/10 |
| D9 | Delegado titular y suplente por delegación, con los mismos permisos | 29/09 |
| D10 | Edad por año de nacimiento: año de la olimpiada − año de nacimiento ≥ edad mínima del nivel. Se puede jugar en un nivel menor, y en varios niveles y deportes | 29/09 |
| D11 | Las reglas de reglamento advierten; solo bloquean si se marcan como bloqueantes (edad y género: bloquean siempre, D26) | 29/09 |
| D12 | Fuera del sistema: jurados, Comisión de Justicia, ventana de reclamos, sanción de 2 años, intentos y marcas de lanzamientos, pase de lista | 29/09 |
| D13 | Lanzamientos, salto, sapo, ciclismo, danza, estudiantina y puntualidad: solo se registra 1.º, 2.º y 3.º | 29/09 |
| D14 | Ajedrez: emparejamiento manual por el organizador | 29/09 |
| D15 | Se publica en un hosting (proveedor por definir) | 12/10 |
| D16 | El buscador de participantes al armar un plantel se limita a los de las delegaciones del usuario. Titular y suplente comparten los participantes que registran; el organizador ve los que registraron los delegados de su olimpiada | 13/10 |
| D17 | La olimpiada guarda solo nombre, organizador, fechas de inicio y fin (obligatorias), logo y estado: sin sede principal. La acumulación de amarillas (Art. 15) y la sanción de 2 partidos al capitán (Art. 14) se manejan **fuera del sistema**; dentro, toda roja da 1 partido | 14/10 |
| D18 | Fases estándar por deporte (dato `sports.standard_stages`): fútbol, futsal, básquetbol, voleibol y **tenis de mesa** en 4 series de eliminación simple + fase final con 3.er puesto (Art. 8 y 10). **Ajedrez** (sistema suizo, bases 2.2, fuera del sistema): 4 series de podio directo (clasifica el 1.º) + final de podio. El resto: solo final de podio. Un botón en /fases genera las fases de todas las competencias | 14/10 |
| D19 | Las competencias no se registran una a una: salen del cuadro de puntaje (se crean solas al guardarlo y con el botón "Generar competencias", que se conserva). Al editar solo cambian nombre, fechas, estado, activa y fases de puntaje. Atletismo: las 8 pruebas en los 3 niveles, damas y varones | 14/10 |
| D20 | Cada participante puede tener su resolución de nombramiento o contrato en PDF (opcional, con aviso "Sin resolución"), para verificación y reclamos. La ven su delegación, el organizador y el admin. Verificados los documentos, el delegado ya no la reemplaza (solo el organizador o el admin) | 14/10 |
| D21 | La ficha de inscripción es **general por delegación y disciplina** (bases VII.c): lista todo el plantel, sale por duplicado e incluye la declaración jurada de la Dirección (VII.d). Firman solo el delegado y el director(a) (`teams.director_name`), no cada participante. La ficha individual queda como opción | 14/10 |
| D22 | El delegado inscribe a su delegación en las disciplinas (colectivas e individuales) en una sola pantalla, y desde cada una arma el plantel. Cierre de inscripciones por olimpiada (`registration_deadline`; Puno: 23/10, bases VII.a): después solo el organizador inscribe o retira | 14/10 |
| D23 | El plantel se arma por selección múltiple: lista de participantes de la delegación con su aptitud (apto, con observaciones, no puede), "seleccionar todos los aptos" y alta en lote con camiseta y posición vacías; se agregan los válidos y se informa el resto. Camiseta, posición y capitán se editan en línea en la tabla (marcar un capitán se la quita al anterior) | 14/10 |
| D24 | /partidos solo programa: jornada, fecha y hora, sede y estado. Los partidos nacen en /fases al generar la llave, con sus participantes; ya no se crean partidos sueltos ni se eligen participantes o carriles en el modal | 15/10 |
| D25 | /inscripcion-disciplinas es la única puerta para inscribir a una delegación en una disciplina, para todos los roles. /planteles solo ofrece y admite las disciplinas marcadas (inscripción activa) y, en cada una, las delegaciones inscritas; agregar un participante ya no crea la inscripción. Reemplaza a S3 | 15/10 |
| D26 | Edad (por año de nacimiento) y género del nivel y de la competencia **bloquean siempre**: quien no los cumple no entra al plantel, ni por el alta individual ni por la múltiple (sin fecha de nacimiento o sin género tampoco). El tope de plantel y "una sola delegación por olimpiada" siguen advirtiendo salvo fila bloqueante (D11). Los ya inscritos no se quitan solos: se listan para que decida la organización | 15/10 |
| D27 | /jornadas no crea jornadas: nacen con la llave en /fases, una por ronda. Solo se editan sus fechas (acotan la programación de sus partidos), observaciones y si está completada; la competencia y el nombre los fija la llave. Se borran solo las vacías | 15/10 |
| D28 | La serie de una delegación se agrega, cambia o quita en /delegaciones-inscritas (campo Serie). /series solo crea, renombra y ordena las series, y su conteo de delegaciones lleva a /delegaciones-inscritas filtrado por esa serie. Si la delegación ya está en la llave o el podio de su serie, cambiarla avisa (no bloquea): la llave no se rehace sola | 15/10 |

Supuestos míos (cada uno es un valor por defecto editable). **Todos (S1 a S6) confirmados por el
cliente el 13/10:**

| # | Supuesto | Fase |
|---|---|---|
| S1 | Una fila "(damas y varones)" genera dos podios, uno por género, cada uno con los puntos de la fila | F3 |
| S2 | Las filas de Atletismo generan una competencia por prueba, cada una con los puntos de la fila | F3 |
| S3 | ~~La inscripción de una delegación a una competencia se crea sola al registrar su primer participante~~ Reemplazado por D25 (15/10) | F4 |
| S4 | Desempate del puntaje general: más oros, luego platas, luego bronces | F6 |
| S5 | Una delegación descalificada conserva sus premios en el detalle, pero va al final del puntaje general sin posición | F6 |
| S6 | Puntualidad, Estudiantina y Danza se cargan en el catálogo como "actividades" (un deporte de formato ranking) para poder tener competencia y podio | F3 |

## 3. Reglas de las bases que el sistema implementa

| Regla | Artículo | Fase |
|---|---|---|
| Niveles: Libre 25+ (nacidos hasta 2001), Máster 40+ (1986), Súper Máster 55+ (1971); damas y varones | VI, Esp. I | F3, F4 |
| Delegado titular y suplente por delegación | VII.b, Art. 18 | F2 |
| Inscripción de S/ 800 por institución hasta el 23/10 (solo se registra si pagó) | VII.a | F2 |
| Participantes: docentes y administrativos, nombrados o contratados | VI | F4 |
| 4 series por sorteo; eliminación simple; pasa el 1.º de cada serie | Art. 8, 10 | F2, F5 |
| Fase final: ganadores juegan por 1.º/2.º, perdedores por 3.º/4.º | Art. 10 | F5 |
| W.O. por no presentarse | Art. 12 | ya existe |
| Roja: 1 partido (el capitán: 2, fuera del sistema, D17) | Art. 14 | F5 |
| 2 amarillas acumuladas: 1 partido (fuera del sistema, D17) | Art. 15 | — |
| Reprogramación por fuerza mayor | Art. 16 | ya existe |
| Equipo descalificado por mala inscripción | Art. 22 | F2 (estado) |
| Medallas a 1.º, 2.º y 3.º; puntaje general por delegación | Art. 28 | F6 |

Topes de plantel (dato inicial del cuadro; editable):

| Competencia | Mínimo | Máximo |
|---|---|---|
| Fútbol | 7 | 18 |
| Futsal | 3 | 10 |
| Básquetbol | — | 10 |
| Voleibol | 6 | 12 |
| Tiro al sapo | — | 3 |
| Ajedrez, tenis de mesa, ciclismo, pruebas de atletismo | — | 1 |
| Posta 4×100 | 4 | 4 |
| Estudiantina | 10 | — |
| Danza | — (C4: 8 o 12 parejas) | — |

Desempates en partidos: fútbol (penales 5, muerte súbita, moneda; en fase final antes 2×10),
futsal (penales 3, muerte súbita, moneda), básquet (3 lanzamientos, moneda). El sistema registra
**quién ganó y por qué vía**, no simula el desempate.

## 4. Contradicciones de las bases (no se resuelven en código)

| # | Contradicción | Cómo queda en el sistema |
|---|---|---|
| C1 | "Senior" vs. "Súper Máster" | Nombre editable del nivel; se siembra "Súper Máster" |
| C2 | Ciclismo: Libre y Máster / solo Libre / puntaje solo varones | Fila varones Libre con puntos; fila damas Libre sin puntos |
| C3 | Futsal damas Máster sin puntaje | Fila sin puntos |
| C4 | Danza: 8 o 12 parejas | Mínimo vacío |
| C5 | Criterios de Danza suman 110 | Fuera del sistema (jurado) |
| C6 | Estudiantina y Danza sin fila de puntaje | Filas sin puntos |
| C7 | "(damas y varones)" en una fila | Supuesto S1, con casilla "podios separados" |
| C8 | Atletismo 6/4/2 "por categoría" | Supuesto S2, con casilla "una competencia por prueba" |
| C10 | Ciclismo el 23/10, fuera de 28–30 | Las fechas de la olimpiada no limitan la programación |
| C11 | Sin desempate del puntaje general | Supuesto S4 |
| C12 | Series de 4 y 5 sin regla de byes | El organizador elige quién descansa al generar la llave |

## 5. Datos iniciales

### 5.1 Series y delegaciones (bases, "Conformación de los grupos")

| Serie A | Serie B | Serie C | Serie D |
|---|---|---|---|
| JAE Puno | MNB Juliaca | IESP Juli | ESFA Puno |
| Ilave | Pedro Vilcapaza | Ayaviri | Huancané |
| ESFA Pilcuyo-Moho | ESFA Juliaca | Muñani JDCH | Desaguadero |
| Santa Rosa Ñuñoa | Yunguyo Capachica | Santa Lucía Putina | Ácora Zepita |
| Crucero Macusani | | San Juan del Oro Mañazo | |

### 5.2 Niveles

| Nombre | Edad mínima |
|---|---|
| Libre | 25 |
| Máster | 40 |
| Súper Máster | 55 |

### 5.3 Sedes

Plaza de Armas de Yunguyo; Estadio José Carlos Mariátegui; Complejo Deportivo; Coliseo Cerrado de
Yunguyo; Complejo Deportivo Agua Dulce; Ruta C.P. Chimbo – Yunguyo; Teatro Municipal de Yunguyo;
Coliseo Municipal de Yunguyo.

### 5.4 Deportes y pruebas

| Deporte | Formato | Pruebas |
|---|---|---|
| Fútbol, Futsal, Básquetbol, Voleibol | enfrentamiento | — |
| Ajedrez, Tenis de mesa | enfrentamiento (individual) | — |
| Tiro al sapo, Ciclismo | ranking individual | — |
| Atletismo | ranking individual | 100 m, 400 m, 1500 m, 3000 m, Salto largo, Lanzamiento de peso, Lanzamiento de disco, Posta 4×100 (posta) |
| Puntualidad y presentación, Estudiantina, Danza | ranking individual (actividad, S6) | — |

Slugs: `futbol`, `futsal`, `basquetbol`, `voley`, `ajedrez`, `tenis-de-mesa`, `tiro-al-sapo`,
`ciclismo`, `atletismo`, `puntualidad`, `estudiantina`, `danza`.

### 5.5 Cuadro "Puntaje total por categorías" (pág. 9, literal)

`alcance`: V = varones, D = damas, DV = damas y varones, — = sin género.

| N.º | Etiqueta | Deporte | Nivel | Alcance | 1.º | 2.º | 3.º |
|---|---|---|---|---|---|---|---|
| 1 | Puntualidad y presentación | Puntualidad y presentación | — | — | 18 | 14 | 10 |
| 2 | Ajedrez Libre | Ajedrez | Libre | DV | 8 | 6 | 4 |
| 3 | Ajedrez Máster | Ajedrez | Máster | DV | 8 | 6 | 4 |
| 4 | Ajedrez Súper Máster | Ajedrez | Súper Máster | DV | 8 | 6 | 4 |
| 5 | Atletismo Libre | Atletismo | Libre | DV | 6 | 4 | 2 |
| 6 | Atletismo Máster | Atletismo | Máster | DV | 6 | 4 | 2 |
| 7 | Atletismo Súper Máster | Atletismo | Súper Máster | DV | 6 | 4 | 2 |
| 8 | Básquetbol Libre | Básquetbol | Libre | DV | 20 | 16 | 12 |
| 9 | Básquetbol Máster | Básquetbol | Máster | DV | 20 | 16 | 12 |
| 10 | Ciclismo Libre | Ciclismo | Libre | V | 20 | 16 | 12 |
| 11 | Fútbol Libre | Fútbol | Libre | V | 24 | 20 | 16 |
| 12 | Futsal Libre | Futsal | Libre | DV | 20 | 16 | 12 |
| 13 | Futsal Máster | Futsal | Máster | V | 20 | 16 | 12 |
| 14 | Futsal Súper Máster | Futsal | Súper Máster | V | 28 | 24 | 20 |
| 15 | Sapo Libre | Tiro al sapo | Libre | DV | 10 | 8 | 6 |
| 16 | Sapo Máster | Tiro al sapo | Máster | DV | 8 | 6 | 4 |
| 17 | Sapo Súper Máster | Tiro al sapo | Súper Máster | DV | 10 | 8 | 6 |
| 18 | Tenis de mesa Libre | Tenis de mesa | Libre | DV | 6 | 4 | 2 |
| 19 | Tenis de mesa Máster | Tenis de mesa | Máster | DV | 8 | 6 | 4 |
| 20 | Tenis de mesa Súper Máster | Tenis de mesa | Súper Máster | DV | 24 | 20 | 16 |
| 21 | Voleibol Libre | Voleibol | Libre | DV | 20 | 16 | 12 |
| 22 | Voleibol Máster | Voleibol | Máster | DV | 24 | 20 | 16 |

Filas que el Art. 1 anuncia y el cuadro no puntúa (se siembran con puntos vacíos, D6):

| N.º | Etiqueta | Deporte | Nivel | Alcance |
|---|---|---|---|---|
| 23 | Ciclismo Libre damas | Ciclismo | Libre | D |
| 24 | Futsal Máster damas | Futsal | Máster | D |
| 25 | Estudiantina | Estudiantina | Libre | — |
| 26 | Danza | Danza | Libre | — |

## 6. Roles

| Acción | Admin | Organizador | Delegado |
|---|---|---|---|
| Crear y borrar olimpiada, asignar organizador | ✅ | ❌ | ❌ |
| Editar datos de su olimpiada | ✅ | ✅ | ❌ |
| Configurar el cuadro de puntaje | ✅ | solo ve | ❌ |
| Niveles, sedes, series, delegaciones, delegados | ✅ | ✅ su olimpiada | ❌ |
| Crear cuentas de delegado | ✅ | ✅ | ❌ |
| Crear cuentas de otros roles | ✅ | ❌ | ❌ |
| Registrar participantes y planteles | ✅ | ✅ su olimpiada | ✅ su delegación |
| Programar, resultados, sanciones, podios | ✅ | ✅ su olimpiada | solo ve lo suyo |
| Fichas, carnets, padrón | ✅ | ✅ | ✅ los suyos |

Toda autorización se revalida **en el Service**. El admin conserva el bypass de `Gate::before`.

## 7. Requisitos no funcionales

| # | Requisito |
|---|---|
| N1 | Publicado con HTTPS antes de abrir inscripciones |
| N2 | Usable desde el celular (delegado y mesa de control) |
| N3 | Respaldo diario de la base durante el evento, con restauración probada |
| N4 | Pruebas automatizadas de autorización y de cálculo de puntaje; ninguna fase avanza con pruebas en rojo |
| N5 | Migraciones aditivas; nunca `migrate:fresh` sobre la base de trabajo |
| N6 | Sin soft deletes; borrados con chequeo previo de historial |
