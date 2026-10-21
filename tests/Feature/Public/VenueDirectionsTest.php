<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Livewire\Public\PublicResults;
use App\Models\Venue;
use App\Support\VenueMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsCompetition;
use Tests\Concerns\CreatesActors;
use Tests\TestCase;

/** "Cómo llegar" de la sede en la pagina publica: Google Maps en modo ruta y OpenStreetMap. */
class VenueDirectionsTest extends TestCase
{
    use BuildsCompetition, CreatesActors, RefreshDatabase;

    public function test_with_coordinates_links_go_to_the_exact_point(): void
    {
        $venue = new Venue(['name' => 'Coliseo', 'latitude' => '-16.24310000', 'longitude' => '-69.09120000']);

        $this->assertSame('https://www.google.com/maps/dir/?api=1&destination=-16.2431%2C-69.0912', VenueMap::directions($venue));
        $this->assertSame('https://www.openstreetmap.org/directions?route=%3B-16.2431%2C-69.0912', VenueMap::openStreetMap($venue));
    }

    public function test_without_coordinates_links_search_by_name_and_address(): void
    {
        $venue = new Venue(['name' => 'Coliseo Municipal', 'address' => 'Jr. Lima, Yunguyo']);

        $this->assertSame('https://www.google.com/maps/dir/?api=1&destination=Coliseo+Municipal%2C+Jr.+Lima%2C+Yunguyo', VenueMap::directions($venue));
        $this->assertSame('https://www.openstreetmap.org/search?query=Coliseo+Municipal%2C+Jr.+Lima%2C+Yunguyo', VenueMap::openStreetMap($venue));
    }

    public function test_public_schedule_shows_how_to_get_to_the_venue(): void
    {
        $c = $this->buildCompetition();
        $venue = Venue::factory()->create(['name' => 'Estadio de Yunguyo', 'latitude' => -16.2431, 'longitude' => -69.0912]);
        $c->match->update(['venue_id' => $venue->id]);
        $c->season->update(['status' => 'active', 'is_active' => true]);

        Livewire::test(PublicResults::class)->call('setView', 'programacion')
            ->assertSee('Estadio de Yunguyo')
            ->assertSee('Cómo llegar')
            ->assertSeeHtml('href="https://www.google.com/maps/dir/?api=1&amp;destination=-16.2431%2C-69.0912"');
    }
}
