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
            'label' => 'Torneos',
            'icon' => 'fa-solid fa-trophy',
            'children' => [
                ['label' => 'Torneos', 'route' => 'tournaments.index', 'permission' => 'tournaments.manage'],
                ['label' => 'Temporadas', 'route' => 'seasons.index', 'permission' => 'seasons.manage'],
            ],
        ],
        [
            'label' => 'Catálogos',
            'icon' => 'fa-solid fa-layer-group',
            'children' => [
                ['label' => 'Categorías', 'route' => 'categories.index', 'permission' => 'categories.manage'],
            ],
        ],
        [
            'label' => 'Equipos',
            'icon' => 'fa-solid fa-people-group',
            'children' => [
                ['label' => 'Equipos', 'route' => 'teams.index', 'permission' => 'teams.manage'],
                ['label' => 'Jugadores', 'route' => 'players.index', 'permission' => 'players.manage'],
            ],
        ],
        [
            'label' => 'Partidos',
            'icon' => 'fa-solid fa-futbol',
            'children' => [
                ['label' => 'Jornadas', 'route' => 'matchdays.index', 'permission' => 'matches.manage'],
                ['label' => 'Partidos', 'route' => 'matches.index', 'permission' => 'matches.manage'],
            ],
        ],
        [
            'label' => 'Reportes',
            'icon' => 'fa-solid fa-chart-line',
            'route' => 'reports.index',
            'permission' => 'reports.view',
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
