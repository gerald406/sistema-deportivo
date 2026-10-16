<?php

declare(strict_types=1);

namespace Tests\Feature\Stages;

use App\Livewire\Matchdays\Index as MatchdaysIndex;
use App\Models\GameMatch;
use App\Models\Matchday;
use App\Services\MatchdayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsBracket;
use Tests\Concerns\BuildsCompetition;
use Tests\Concerns\CreatesActors;
use Tests\TestCase;

/** D27: las jornadas nacen con la llave (/fases); /jornadas solo ajusta fechas, observaciones y cierre. */
class MatchdayScreenTest extends TestCase
{
    use BuildsBracket, BuildsCompetition, CreatesActors, RefreshDatabase;

    private Matchday $matchday;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootCompetition();
        $this->season->update(['start_date' => '2026-10-28', 'end_date' => '2026-10-30', 'status' => 'active']);
        $this->matchday = Matchday::create(['season_id' => $this->season->id, 'name' => 'Fase final — Final']);
    }

    private function screen()
    {
        return Livewire::actingAs($this->organizer)->test(MatchdaysIndex::class);
    }

    public function test_there_is_no_manual_creation(): void
    {
        $this->screen()
            ->assertDontSee('Nueva jornada')
            ->assertSee('Generar llave')
            ->assertSee(route('season-stages.index'), false);

        $this->assertFalse(method_exists(MatchdaysIndex::class, 'create'));
        $this->assertFalse(method_exists(MatchdayService::class, 'register'));

        // Sin una jornada en edicion, guardar desde el navegador no crea nada.
        $this->screen()
            ->set('form.season_id', $this->season->id)
            ->set('form.name', 'Fecha 9')
            ->call('save')
            ->assertNotFound();
        $this->assertSame(1, Matchday::count());
    }

    public function test_edits_dates_and_observations_but_not_the_name(): void
    {
        $this->screen()
            ->call('edit', $this->matchday->id)
            ->assertSee('Fase final — Final')
            ->set('form.name', 'Otro nombre')
            ->set('form.start_date', '2026-10-30')
            ->set('form.end_date', '2026-10-30')
            ->set('form.observations', 'Estadio principal')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast', type: 'success');

        $this->matchday->refresh();
        $this->assertSame(
            ['Fase final — Final', '2026-10-30', '2026-10-30', 'Estadio principal'],
            [$this->matchday->name, $this->matchday->start_date->format('Y-m-d'), $this->matchday->end_date->format('Y-m-d'), $this->matchday->observations],
        );

        // El Service tampoco renombra aunque el nombre llegue en los datos.
        app(MatchdayService::class)->update($this->matchday, ['name' => 'Forzado', 'start_date' => null, 'end_date' => null, 'is_completed' => false, 'observations' => null], $this->organizer);
        $this->assertSame('Fase final — Final', $this->matchday->refresh()->name);
    }

    public function test_dates_must_fall_within_the_competition(): void
    {
        $this->screen()
            ->call('edit', $this->matchday->id)
            ->set('form.start_date', '2026-11-05')
            ->call('save')
            ->assertHasErrors('form.start_date');
    }

    public function test_cannot_complete_with_pending_matches(): void
    {
        GameMatch::create(['season_id' => $this->season->id, 'matchday_id' => $this->matchday->id, 'round_type' => 'final', 'status' => 'pending']);

        $this->screen()
            ->call('edit', $this->matchday->id)
            ->set('form.is_completed', true)
            ->call('save')
            ->assertHasErrors('form.is_completed');

        $this->assertFalse($this->matchday->refresh()->is_completed);
    }
}
