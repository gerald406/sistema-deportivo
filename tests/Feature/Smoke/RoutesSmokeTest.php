<?php

declare(strict_types=1);

namespace Tests\Feature\Smoke;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\BuildsCompetition;
use Tests\Concerns\CreatesActors;
use Tests\TestCase;

/**
 * Prueba de humo: toda pantalla del panel carga para el admin y exige
 * sesion. Cada fase que agrega o quita una ruta actualiza adminRoutes().
 */
class RoutesSmokeTest extends TestCase
{
    use BuildsCompetition, CreatesActors, RefreshDatabase;

    /** @return list<string> */
    private function adminRoutes(): array
    {
        return [
            'dashboard',
            'teams.index',
            'players.index',
            'seasons.index',
            'season-teams.index',
            'rosters.index',
            'registrations.index',
            'matchdays.index',
            'matches.index',
            'relays.index',
            'periods.index',
            'results.index',
            'match-events.index',
            'suspensions.index',
            'reopen-logs.index',
            'reports.index',
            'reports.roster',
            'reports.managerial',
            'categories.index',
            'venues.index',
            'admin.sports.index',
            'admin.users.index',
            'admin.roles.index',
            'games-editions.index',
            'edition-groups.index',
            'edition-teams.index',
            'edition-events.index',
            'season-stages.index',
            'reports.general-score',
            'reports.delegations',
        ];
    }

    public function test_guest_is_redirected_to_login(): void
    {
        foreach ($this->adminRoutes() as $name) {
            $this->get(route($name))->assertRedirect(route('login'));
        }
    }

    public function test_admin_can_open_every_screen(): void
    {
        $admin = $this->admin();

        foreach ($this->adminRoutes() as $name) {
            $response = $this->actingAs($admin)->get(route($name));

            $this->assertSame(200, $response->getStatusCode(), "La ruta {$name} devolvió {$response->getStatusCode()}.");
        }
    }

    /**
     * Con una competencia en juego las vistas recorren filas reales: asi se
     * detecta una relacion o variable mal nombrada que una tabla vacia
     * esconderia. Se repite con cada rol porque los alcances por rol usan
     * consultas distintas a las del admin.
     */
    public function test_every_screen_renders_with_data_for_each_role(): void
    {
        $data = $this->buildCompetition();
        $delegate = $this->delegate();
        $this->registerDelegation($data->edition, $data->homeTeam, $delegate);

        $actors = ['admin' => $this->admin(), 'organizador' => $data->organizer, 'delegado' => $delegate];

        foreach ($actors as $role => $actor) {
            foreach ($this->adminRoutes() as $name) {
                $status = $this->actingAs($actor)->get(route($name))->getStatusCode();

                $this->assertContains($status, [200, 403], "La ruta {$name} devolvió {$status} para el rol {$role}.");
            }
        }
    }

    public function test_pdf_documents_render(): void
    {
        $data = $this->buildCompetition();
        $admin = $this->admin();
        $timeLimit = ini_get('max_execution_time');

        $urls = [
            'padrón' => route('reports.roster.pdf', ['season_id' => $data->season->id]),
            'padrón de un equipo' => route('reports.roster.pdf', ['season_id' => $data->season->id, 'season_team_id' => $data->home->id]),
            'reporte gerencial' => route('reports.managerial.pdf', ['season_id' => $data->season->id]),
            'carnet' => route('cards.single', $data->homeEntry),
            'ficha' => route('registrations.single', $data->homeEntry),
            'fichas del equipo' => route('registrations.team', $data->home),
        ];

        foreach ($urls as $label => $url) {
            $response = $this->actingAs($admin)->get($url);

            $this->assertSame(200, $response->getStatusCode(), "El PDF «{$label}» devolvió {$response->getStatusCode()}.");
            $this->assertStringContainsString('pdf', (string) $response->headers->get('content-type'));
        }

        // Generar un PDF no baja el limite de tiempo del proceso (en consola: sin limite).
        $this->assertSame($timeLimit, ini_get('max_execution_time'));
    }

    public function test_public_pages_load(): void
    {
        $data = $this->buildCompetition();

        $this->get(route('home'))->assertOk()->assertSee($data->edition->name);
        $this->get(route('public.results'))->assertRedirect(route('home'));
        $this->get(URL::signedRoute('cards.verify', $data->homeEntry))->assertOk()->assertSee($data->edition->name);
    }

    public function test_delegate_cannot_open_admin_screens(): void
    {
        $delegate = $this->delegate();

        foreach (['admin.sports.index', 'admin.users.index', 'admin.roles.index'] as $name) {
            $this->actingAs($delegate)->get(route($name))->assertForbidden();
        }
    }
}
