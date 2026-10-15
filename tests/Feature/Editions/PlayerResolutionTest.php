<?php

declare(strict_types=1);

namespace Tests\Feature\Editions;

use App\Livewire\Players\Index as PlayersIndex;
use App\Models\GamesEdition;
use App\Models\Player;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\Team;
use App\Models\User;
use App\Services\PlayerRegistrationService;
use App\Services\UserAccountService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\BuildsCompetition;
use Tests\Concerns\CreatesActors;
use Tests\TestCase;

/** D20: resolucion de nombramiento o contrato (PDF) del participante, en el disco privado. */
class PlayerResolutionTest extends TestCase
{
    use BuildsCompetition, CreatesActors, RefreshDatabase;

    private User $organizer;

    private User $delegate;

    private Player $player;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        $this->organizer = $this->organizer();
        $edition = GamesEdition::factory()->create(['organizer_id' => $this->organizer->id]);
        $this->delegate = $this->delegate();
        $team = Team::factory()->create();
        $this->registerDelegation($edition, $team, $this->delegate);

        $season = Season::factory()->create(['games_edition_id' => $edition->id]);
        $seasonTeam = SeasonTeam::create(['season_id' => $season->id, 'team_id' => $team->id, 'is_active' => true]);
        $this->player = $this->rosterEntry($seasonTeam, playerAttributes: ['created_by' => $this->delegate->id])->player;
    }

    private function pdf(string $name = 'resolucion.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 120, 'application/pdf');
    }

    private function attach(?User $actor = null, ?UploadedFile $file = null): Player
    {
        return app(PlayerRegistrationService::class)->attachResolution($this->player, $file ?? $this->pdf(), $actor ?? $this->delegate);
    }

    public function test_delegate_uploads_a_pdf_to_the_private_disk(): void
    {
        Livewire::actingAs($this->delegate)->test(PlayersIndex::class)
            ->call('openResolution', $this->player->id)
            ->set('resolutionFile', $this->pdf())
            ->call('saveResolution')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $this->player->refresh();
        $this->assertNotNull($this->player->resolution_path);
        $this->assertSame($this->delegate->id, $this->player->resolution_uploaded_by);
        $this->assertNotNull($this->player->resolution_uploaded_at);
        Storage::disk('local')->assertExists($this->player->resolution_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_delegate_attaches_it_when_registering_a_participant(): void
    {
        Livewire::actingAs($this->delegate)->test(PlayersIndex::class)
            ->call('create')
            ->set('form.dni', '44556677')->set('form.first_name', 'Rosa')->set('form.last_name', 'Quispe')
            ->set('form.birth_date', '1980-01-01')->set('form.gender', 'F')
            ->set('newResolution', $this->pdf())
            ->call('save')
            ->assertHasNoErrors();

        $created = Player::where('dni', '44556677')->sole();
        Storage::disk('local')->assertExists($created->resolution_path);
    }

    public function test_only_pdf_files_are_accepted(): void
    {
        Livewire::actingAs($this->delegate)->test(PlayersIndex::class)
            ->call('openResolution', $this->player->id)
            ->set('resolutionFile', UploadedFile::fake()->image('foto.jpg'))
            ->call('saveResolution')
            ->assertHasErrors('resolutionFile');

        // El Service tambien lo revisa: un archivo que no es PDF aunque se llame .pdf.
        $this->expectException(ValidationException::class);
        $this->attach(file: UploadedFile::fake()->create('falso.pdf', 10, 'text/plain'));
    }

    public function test_too_big_files_are_rejected(): void
    {
        Livewire::actingAs($this->delegate)->test(PlayersIndex::class)
            ->call('openResolution', $this->player->id)
            ->set('resolutionFile', UploadedFile::fake()->create('grande.pdf', 6000, 'application/pdf'))
            ->call('saveResolution')
            ->assertHasErrors('resolutionFile');

        $this->assertNull($this->player->refresh()->resolution_path);
    }

    public function test_verified_documents_lock_the_file_for_the_delegate_but_not_the_organizer(): void
    {
        $this->attach();
        app(PlayerRegistrationService::class)->setDocumentsVerified($this->player, true, $this->organizer);
        $verifiedPath = $this->player->refresh()->resolution_path;

        try {
            $this->attach();
            $this->fail('El delegado reemplazó una resolución ya verificada.');
        } catch (AuthorizationException) {
            $this->assertSame($verifiedPath, $this->player->refresh()->resolution_path);
        }

        Livewire::actingAs($this->delegate)->test(PlayersIndex::class)
            ->call('openResolution', $this->player->id)
            ->assertSee('solo el organizador puede reemplazar')
            ->set('resolutionFile', $this->pdf())
            ->call('saveResolution')
            ->assertForbidden();

        // Tampoco colandolo en el formulario de edicion.
        Livewire::actingAs($this->delegate)->test(PlayersIndex::class)
            ->call('edit', $this->player->id)
            ->set('newResolution', $this->pdf())
            ->call('save')
            ->assertHasErrors('newResolution');
        $this->assertSame($verifiedPath, $this->player->refresh()->resolution_path);

        // El organizador si lo reemplaza; el archivo anterior se borra.
        $this->attach($this->organizer);
        $this->player->refresh();
        $this->assertNotSame($verifiedPath, $this->player->resolution_path);
        $this->assertSame($this->organizer->id, $this->player->resolution_uploaded_by);
        Storage::disk('local')->assertMissing($verifiedPath);
    }

    public function test_download_is_limited_to_its_delegation_and_the_organization(): void
    {
        $this->attach();
        $url = route('players.resolution', $this->player);

        foreach ([$this->delegate, $this->organizer, $this->admin()] as $allowed) {
            $this->actingAs($allowed)->get($url)
                ->assertOk()
                ->assertHeader('Content-Type', 'application/pdf');
        }

        // Delegado de otra delegacion y organizador de otra olimpiada.
        $otherDelegate = $this->delegate();
        $this->registerDelegation(GamesEdition::factory()->create(), Team::factory()->create(), $otherDelegate);

        foreach ([$otherDelegate, $this->organizer()] as $stranger) {
            $this->actingAs($stranger)->get($url)->assertForbidden();
        }
    }

    public function test_guest_cannot_download(): void
    {
        $this->attach();

        $this->get(route('players.resolution', $this->player))->assertRedirect(route('login'));
    }

    public function test_without_file_the_download_is_not_found(): void
    {
        $this->actingAs($this->delegate)->get(route('players.resolution', $this->player))->assertNotFound();
    }

    public function test_remove_and_delete_clean_up_the_file(): void
    {
        $path = $this->attach()->resolution_path;

        app(PlayerRegistrationService::class)->removeResolution($this->player, $this->delegate);
        $this->assertNull($this->player->refresh()->resolution_path);
        Storage::disk('local')->assertMissing($path);

        // Un participante sin inscripciones se elimina junto con su PDF.
        $loose = Player::factory()->create(['created_by' => $this->delegate->id]);
        $loosePath = app(PlayerRegistrationService::class)->attachResolution($loose, $this->pdf(), $this->delegate)->resolution_path;
        $this->assertTrue(app(PlayerRegistrationService::class)->delete($loose));
        Storage::disk('local')->assertMissing($loosePath);
    }

    public function test_listing_filters_by_resolution(): void
    {
        $this->attach();
        $without = Player::factory()->create(['created_by' => $this->delegate->id, 'last_name' => 'SinPdf']);

        $ids = fn (string $filter) => collect(Livewire::actingAs($this->delegate)->test(PlayersIndex::class)
            ->set('resolutionFilter', $filter)->viewData('players')->items())->pluck('id')->all();

        $this->assertSame([$this->player->id], $ids('with'));
        $this->assertSame([$without->id], $ids('without'));
    }

    public function test_uploader_with_resolutions_is_not_deleted(): void
    {
        $this->attach();

        $result = app(UserAccountService::class)->delete($this->delegate, $this->admin());

        $this->assertIsString($result);
        $this->assertDatabaseHas('users', ['id' => $this->delegate->id]);
    }
}
