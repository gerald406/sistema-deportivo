<?php

use App\Livewire\Admin\Roles\Index as AdminRolesIndex;
use App\Livewire\Admin\Sports\Index as AdminSportsIndex;
use App\Livewire\Admin\Users\Index as AdminUsersIndex;
use App\Livewire\Categories\Index as CategoriesIndex;
use App\Livewire\Dashboard;
use App\Livewire\GamesEditions\Index as GamesEditionsIndex;
use App\Livewire\Matchdays\Index as MatchdaysIndex;
use App\Livewire\Matches\Index as MatchesIndex;
use App\Livewire\Periods\Index as PeriodsIndex;
use App\Livewire\Players\Index as PlayersIndex;
use App\Livewire\Relays\Index as RelaysIndex;
use App\Livewire\Rosters\Index as RostersIndex;
use App\Livewire\SeasonTeams\Index as SeasonTeamsIndex;
use App\Livewire\Seasons\Index as SeasonsIndex;
use App\Livewire\Teams\Index as TeamsIndex;
use App\Livewire\Tournaments\Index as TournamentsIndex;
use App\Livewire\Venues\Index as VenuesIndex;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', Dashboard::class)
        ->name('dashboard');

    Route::get('/equipos', TeamsIndex::class)
        ->name('teams.index');

    Route::get('/jugadores', PlayersIndex::class)
        ->name('players.index');

    Route::get('/torneos', TournamentsIndex::class)
        ->name('tournaments.index');

    Route::get('/temporadas', SeasonsIndex::class)
        ->name('seasons.index');

    Route::get('/inscripciones', SeasonTeamsIndex::class)
        ->name('season-teams.index');

    Route::get('/planteles', RostersIndex::class)
        ->name('rosters.index');

    Route::get('/jornadas', MatchdaysIndex::class)
        ->name('matchdays.index');

    Route::get('/partidos', MatchesIndex::class)
        ->name('matches.index');

    Route::get('/relevos', RelaysIndex::class)
        ->name('relays.index');

    Route::get('/parciales', PeriodsIndex::class)
        ->name('periods.index');

    Route::get('/categorias', CategoriesIndex::class)
        ->name('categories.index');

    Route::get('/sedes', VenuesIndex::class)
        ->name('venues.index');

    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/deportes', AdminSportsIndex::class)->name('admin.sports.index');
        Route::get('/admin/usuarios', AdminUsersIndex::class)->name('admin.users.index');
        Route::get('/admin/roles', AdminRolesIndex::class)->name('admin.roles.index');
        Route::get('/ediciones', GamesEditionsIndex::class)->name('games-editions.index');
    });
});
