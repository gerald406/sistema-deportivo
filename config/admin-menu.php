<?php

declare(strict_types=1);

/**
 * Estructura del sidebar del panel administrativo.
 *
 * 'route'      => nombre de ruta Named. Si la ruta aun no existe (fases
 *                 futuras), el sidebar la muestra deshabilitada ('#') en
 *                 vez de romper con RouteNotFoundException.
 * 'permission' => permiso Spatie requerido para ver el item (o alguno de
 *                 sus hijos). Si es null, es visible para cualquier
 *                 usuario autenticado.
 * 'role'       => alternativa a 'permission' cuando la regla es "solo
 *                 este rol", como en la sección de Administración.
 * 'children'   => submenu (dropdown). Un padre con hijos se oculta solo
 *                 si TODOS sus hijos quedan ocultos.
 */
return [
    'items' => [
        [
            'label' => 'Dashboard',
            'icon' => 'fa-solid fa-gauge-high',
            'route' => 'dashboard',
        ],
        [
            'label' => 'Olimpiada',
            'icon' => 'fa-solid fa-trophy',
            'children' => [
                ['label' => 'Olimpiadas', 'route' => 'games-editions.index', 'permission' => 'editions.manage'],
                ['label' => 'Series', 'route' => 'edition-groups.index', 'permission' => 'editions.manage'],
                ['label' => 'Delegaciones inscritas', 'route' => 'edition-teams.index', 'permission' => 'editions.manage'],
                ['label' => 'Cuadro de puntaje', 'route' => 'edition-events.index', 'permission' => 'editions.manage'],
                ['label' => 'Temporadas', 'route' => 'seasons.index', 'permission' => 'seasons.manage'],
            ],
        ],
        [
            'label' => 'Catálogos',
            'icon' => 'fa-solid fa-layer-group',
            'children' => [
                ['label' => 'Categorías', 'route' => 'categories.index', 'permission' => 'categories.manage'],
                ['label' => 'Sedes', 'route' => 'venues.index', 'permission' => 'venues.manage'],
            ],
        ],
        [
            'label' => 'Equipos',
            'icon' => 'fa-solid fa-people-group',
            'children' => [
                ['label' => 'Delegaciones', 'route' => 'teams.index', 'permission' => 'teams.manage'],
                ['label' => 'Jugadores', 'route' => 'players.index', 'permission' => 'players.manage'],
                // Sin permission: los 3 roles ven inscripciones (SeasonTeamPolicy::viewAny).
                ['label' => 'Inscripciones', 'route' => 'season-teams.index'],
                ['label' => 'Planteles', 'route' => 'rosters.index'],
                // Los 3 roles: cada uno solo ve las fichas que puede imprimir (printCard).
                ['label' => 'Fichas de inscripción', 'route' => 'registrations.index', 'role' => ['admin', 'organizador', 'delegado']],
            ],
        ],
        [
            'label' => 'Partidos',
            'icon' => 'fa-solid fa-futbol',
            'children' => [
                ['label' => 'Jornadas', 'route' => 'matchdays.index', 'permission' => 'matches.manage'],
                // Delegado: solo lectura, partidos de su equipo (GameMatchPolicy::viewAny).
                ['label' => 'Partidos', 'route' => 'matches.index', 'role' => ['admin', 'organizador', 'delegado']],
                ['label' => 'Parciales', 'route' => 'periods.index', 'permission' => 'matches.manage'],
                ['label' => 'Incidencias', 'route' => 'match-events.index', 'permission' => 'matches.manage'],
                // Delegado: solo lectura, resultados de su equipo (EventParticipantPolicy::viewAny).
                ['label' => 'Resultados', 'route' => 'results.index', 'role' => ['admin', 'organizador', 'delegado']],
                ['label' => 'Sanciones', 'route' => 'suspensions.index', 'permission' => 'matches.manage'],
                // Solo lectura (auditoria). La accion "Reabrir resultado" vive en Resultados.
                ['label' => 'Bitácora de reaperturas', 'route' => 'reopen-logs.index', 'permission' => 'matches.manage'],
                // Sin permission: organizador/admin y delegados (sus equipos). Ver EventLineupPolicy.
                ['label' => 'Relevos de postas', 'route' => 'relays.index'],
            ],
        ],
        [
            'label' => 'Reportes',
            'icon' => 'fa-solid fa-chart-line',
            'children' => [
                ['label' => 'Posiciones y medallero', 'route' => 'reports.index', 'permission' => 'reports.view'],
                // PDFs por temporada: admin y organizador (SeasonPolicy::printReports).
                // Padron: tambien el delegado, solo de su equipo (SeasonTeamPolicy::printRoster).
                ['label' => 'Padrón de jugadores', 'route' => 'reports.roster', 'role' => ['admin', 'organizador', 'delegado']],
                ['label' => 'Reporte gerencial', 'route' => 'reports.managerial', 'role' => ['admin', 'organizador']],
            ],
        ],
        [
            'label' => 'Administración',
            'icon' => 'fa-solid fa-gears',
            'role' => 'admin',
            'children' => [
                ['label' => 'Deportes y disciplinas', 'route' => 'admin.sports.index'],
                ['label' => 'Usuarios', 'route' => 'admin.users.index'],
                ['label' => 'Roles y permisos', 'route' => 'admin.roles.index'],
            ],
        ],
    ],
];
