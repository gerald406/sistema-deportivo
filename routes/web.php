<?php

use App\Http\Controllers\GeneralRegistrationFormController;
use App\Http\Controllers\ManagerialReportController;
use App\Http\Controllers\PlayerCardController;
use App\Http\Controllers\PlayerRegistrationController;
use App\Http\Controllers\PlayerResolutionController;
use App\Http\Controllers\RosterReportController;
use App\Livewire\Admin\Roles\Index as AdminRolesIndex;
use App\Livewire\Admin\Sports\Index as AdminSportsIndex;
use App\Livewire\Admin\Users\Index as AdminUsersIndex;
use App\Livewire\Categories\Index as CategoriesIndex;
use App\Livewire\Dashboard;
use App\Livewire\DelegationEnrollment\Index as DelegationEnrollmentIndex;
use App\Livewire\EditionEvents\Index as EditionEventsIndex;
use App\Livewire\EditionGroups\Index as EditionGroupsIndex;
use App\Livewire\EditionTeams\Index as EditionTeamsIndex;
use App\Livewire\GamesEditions\Index as GamesEditionsIndex;
use App\Livewire\MatchEvents\Index as MatchEventsIndex;
use App\Livewire\Matchdays\Index as MatchdaysIndex;
use App\Livewire\Matches\Index as MatchesIndex;
use App\Livewire\Periods\Index as PeriodsIndex;
use App\Livewire\PlayerRegistrationSelector;
use App\Livewire\Public\PublicResults;
use App\Livewire\Players\Index as PlayersIndex;
use App\Livewire\Relays\Index as RelaysIndex;
use App\Livewire\ReopenLogs\Index as ReopenLogsIndex;
use App\Livewire\Reports\GeneralScore;
use App\Livewire\Reports\Index as ReportsIndex;
use App\Livewire\Reports\ManagerialReport;
use App\Livewire\Reports\RosterReport;
use App\Livewire\Results\Index as ResultsIndex;
use App\Livewire\Rosters\Index as RostersIndex;
use App\Livewire\SeasonStages\Index as SeasonStagesIndex;
use App\Livewire\SeasonTeams\Index as SeasonTeamsIndex;
use App\Livewire\Seasons\Index as SeasonsIndex;
use App\Livewire\Suspensions\Index as SuspensionsIndex;
use App\Livewire\Teams\Index as TeamsIndex;
use App\Livewire\Venues\Index as VenuesIndex;
use Illuminate\Support\Facades\Route;

// Publica (sin login): SOLO la programacion de partidos y los resultados de las
// competencias visibles (PublicResultsService). /resultados es el modulo interno.
Route::get('/', PublicResults::class)->name('home');

// Direccion anterior de la pagina publica: se conserva para los enlaces ya compartidos.
Route::redirect('/resultados-publicos', '/')->name('public.results');

// Publica (sin login): destino del QR del carnet. URL firmada, sin datos sensibles.
Route::get('/verificar-carnet/{seasonTeamPlayer}', [PlayerCardController::class, 'verify'])
    ->middleware(['signed', 'throttle:60,1'])
    ->name('cards.verify');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', Dashboard::class)
        ->name('dashboard');

    Route::get('/equipos', TeamsIndex::class)
        ->name('teams.index');

    Route::get('/participantes', PlayersIndex::class)
        ->name('players.index');

    // Resolucion (PDF) del participante: disco privado, autoriza PlayerPolicy::view.
    Route::get('/participantes/{player}/resolucion', [PlayerResolutionController::class, 'show'])
        ->name('players.resolution');

    Route::get('/olimpiadas', GamesEditionsIndex::class)
        ->name('games-editions.index');

    // Series y delegaciones inscritas de la olimpiada elegida (?olimpiada=ID,
    // ver App\Livewire\Concerns\SelectsEdition). Solo quien gestiona la olimpiada.
    Route::get('/series', EditionGroupsIndex::class)
        ->name('edition-groups.index');
    Route::get('/delegaciones-inscritas', EditionTeamsIndex::class)
        ->name('edition-teams.index');

    // Cuadro de puntaje: lo ven admin y organizador de la olimpiada; lo edita solo el admin.
    Route::get('/puntajes', EditionEventsIndex::class)
        ->name('edition-events.index');

    Route::get('/competencias', SeasonsIndex::class)
        ->name('seasons.index');

    // Fases de una competencia: series, fase final, llaves y podio directo.
    Route::get('/fases', SeasonStagesIndex::class)
        ->name('season-stages.index');

    // Inscripcion por disciplina (D22): el delegado marca sus disciplinas y va a cada plantel.
    Route::get('/inscripcion-disciplinas', DelegationEnrollmentIndex::class)
        ->name('enrollment.index');

    Route::get('/inscripciones', SeasonTeamsIndex::class)
        ->name('season-teams.index');

    Route::get('/planteles', RostersIndex::class)
        ->name('rosters.index');

    // Carnets (PDF). Autorizacion por carnet: SeasonTeamPlayerPolicy::printCard.
    Route::get('/planteles/{seasonTeamPlayer}/carnet', [PlayerCardController::class, 'single'])
        ->name('cards.single');
    Route::get('/carnets/masivo', [PlayerCardController::class, 'massive'])
        ->name('cards.massive');
    Route::post('/carnets/masivo', [PlayerCardController::class, 'massivePost'])
        ->name('cards.massive.post');

    // Fichas de inscripcion (PDF). Misma autorizacion que los carnets: printCard.
    Route::get('/fichas-inscripcion', PlayerRegistrationSelector::class)
        ->name('registrations.index');
    Route::get('/planteles/{seasonTeamPlayer}/ficha', [PlayerRegistrationController::class, 'single'])
        ->name('registrations.single');
    Route::get('/inscripciones/{seasonTeam}/fichas', [PlayerRegistrationController::class, 'byTeam'])
        ->name('registrations.team');
    Route::post('/fichas-inscripcion/masivo', [PlayerRegistrationController::class, 'massivePost'])
        ->name('registrations.massive.post');

    // Ficha de inscripcion GENERAL por delegacion y disciplina (D21, bases VII.c y VII.d).
    Route::get('/inscripciones/{seasonTeam}/ficha-general', [GeneralRegistrationFormController::class, 'single'])
        ->name('registrations.general');
    Route::get('/fichas-generales/{gamesEdition}/{team}', [GeneralRegistrationFormController::class, 'byDelegation'])
        ->name('registrations.general.delegation');

    Route::get('/jornadas', MatchdaysIndex::class)
        ->name('matchdays.index');

    Route::get('/partidos', MatchesIndex::class)
        ->name('matches.index');

    Route::get('/relevos', RelaysIndex::class)
        ->name('relays.index');

    Route::get('/parciales', PeriodsIndex::class)
        ->name('periods.index');

    Route::get('/resultados', ResultsIndex::class)
        ->name('results.index');

    Route::get('/incidencias', MatchEventsIndex::class)
        ->name('match-events.index');

    Route::get('/sanciones', SuspensionsIndex::class)
        ->name('suspensions.index');

    Route::get('/reaperturas', ReopenLogsIndex::class)
        ->name('reopen-logs.index');

    Route::get('/reportes', ReportsIndex::class)
        ->name('reports.index');

    // Puntaje general por delegacion (reports.view; olimpiadas acotadas por rol).
    Route::get('/reportes/puntaje-general', GeneralScore::class)
        ->name('reports.general-score');

    // Padron de Jugadores: filtros acotados por rol (admin/organizador/delegado) y PDF
    // (un equipo: SeasonTeamPolicy::printRoster; todos: SeasonPolicy::printReports).
    Route::get('/reportes/padron', RosterReport::class)
        ->name('reports.roster');
    Route::get('/reportes/padron/pdf', [RosterReportController::class, 'pdf'])
        ->name('reports.roster.pdf');

    // Reporte Gerencial: filtro (solo admin/organizador) y PDF (SeasonPolicy::printReports).
    Route::get('/reportes/gerencial', ManagerialReport::class)
        ->name('reports.managerial');
    Route::get('/reportes/gerencial/pdf', [ManagerialReportController::class, 'pdf'])
        ->name('reports.managerial.pdf');

    Route::get('/categorias', CategoriesIndex::class)
        ->name('categories.index');

    Route::get('/sedes', VenuesIndex::class)
        ->name('venues.index');

    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/deportes', AdminSportsIndex::class)->name('admin.sports.index');
        Route::get('/admin/usuarios', AdminUsersIndex::class)->name('admin.users.index');
        Route::get('/admin/roles', AdminRolesIndex::class)->name('admin.roles.index');
    });
});
