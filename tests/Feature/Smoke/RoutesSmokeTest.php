<?php

declare(strict_types=1);

namespace Tests\Feature\Smoke;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesActors;
use Tests\TestCase;

/**
 * Prueba de humo: toda pantalla del panel carga para el admin y exige
 * sesion. Cada fase que agrega o quita una ruta actualiza adminRoutes().
 */
class RoutesSmokeTest extends TestCase
{
    use CreatesActors, RefreshDatabase;

    /** @return list<string> */
    private function adminRoutes(): array
    {
        return [
            'dashboard',
            'teams.index',
            'players.index',
            'tournaments.index',
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

    public function test_public_pages_load(): void
    {
        $this->get(route('home'))->assertOk();
        $this->get(route('public.results'))->assertOk();
    }

    public function test_delegate_cannot_open_admin_screens(): void
    {
        $delegate = $this->delegate();

        foreach (['admin.sports.index', 'admin.users.index', 'admin.roles.index'] as $name) {
            $this->actingAs($delegate)->get(route($name))->assertForbidden();
        }
    }
}
