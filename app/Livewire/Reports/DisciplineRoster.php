<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Enums\Gender;
use App\Livewire\Concerns\ExportsExcel;
use App\Livewire\Concerns\SelectsEdition;
use App\Models\SeasonTeamPlayer;
use App\Services\ParticipantRosterService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Padron por disciplina (solo lectura, admin y organizador): deporte y,
 * opcionalmente, una de sus competencias; agrupado por competencia y
 * delegacion, con la resolucion (PDF) de cada persona en un modal.
 *
 * Los filtros vienen del navegador: cada id solo cuenta si esta entre las
 * opciones de la olimpiada elegida, y ParticipantRosterService revalida
 * que el usuario la gestione.
 */
#[Layout('layouts.admin')]
#[Title('Padrón por disciplina')]
class DisciplineRoster extends Component
{
    use ExportsExcel, SelectsEdition;

    /** '' = todos los deportes. */
    #[Url(as: 'deporte')]
    public string $sportId = '';

    /** '' = todas las competencias del deporte. */
    #[Url(as: 'competencia')]
    public string $seasonId = '';

    public string $teamId = '';

    public string $search = '';

    public function boot(): void
    {
        // El middleware de la ruta no se reaplica en /livewire/update.
        abort_unless(auth()->user()->hasAnyRole(['admin', 'organizador']), 403);
    }

    public function updatedEditionId(): void
    {
        $this->sportId = '';
        $this->seasonId = '';
        $this->teamId = '';
    }

    public function updatedSportId(): void
    {
        $this->seasonId = '';
    }

    public function sportOptions(): Collection
    {
        $edition = $this->edition();

        return $edition ? $this->service()->sportOptions(auth()->user(), $edition) : collect();
    }

    public function seasonOptions(): Collection
    {
        $edition = $this->edition();

        return $edition ? $this->service()->seasonOptions(auth()->user(), $edition, $this->selectedSportId()) : collect();
    }

    public function delegationOptions(): Collection
    {
        $edition = $this->edition();

        return $edition ? $this->service()->delegationOptions(auth()->user(), $edition) : collect();
    }

    public function render()
    {
        // En pantalla, solo con deporte: el padron entero son miles de filas.
        // El Excel (exportRows) si trae todas las disciplinas.
        $sportChosen = $this->selectedSportId() !== null;

        return view('livewire.reports.discipline-roster', [
            'edition' => $this->edition(),
            'sportChosen' => $sportChosen,
            'rows' => $sportChosen ? $this->rows() : collect(),
        ]);
    }

    /** Filas filtradas: las comparten render() y la exportacion a Excel. */
    private function rows(): Collection
    {
        $edition = $this->edition();

        return $edition
            ? $this->service()->byDiscipline(auth()->user(), $edition, $this->selectedSportId(), $this->selectedSeasonId(), $this->selectedTeamId(), $this->search)
            : collect();
    }

    private function selectedSportId(): ?int
    {
        return $this->pick($this->sportId, $this->sportOptions());
    }

    private function selectedSeasonId(): ?int
    {
        return $this->pick($this->seasonId, $this->seasonOptions());
    }

    private function selectedTeamId(): ?int
    {
        return $this->pick($this->teamId, $this->delegationOptions());
    }

    /** El id solo vale si esta entre las opciones visibles. */
    private function pick(string $value, Collection $options): ?int
    {
        return ctype_digit($value) && $options->contains('id', (int) $value) ? (int) $value : null;
    }

    protected function authorizeExport(): void
    {
        abort_unless(auth()->user()->hasAnyRole(['admin', 'organizador']), 403);
    }

    protected function exportTitle(): string
    {
        return 'Padrón por disciplina';
    }

    protected function exportRows(): iterable
    {
        return $this->rows();
    }

    protected function exportColumns(): array
    {
        return [
            'Competencia' => fn (SeasonTeamPlayer $e) => $e->seasonTeam->season->name,
            'Delegación' => fn (SeasonTeamPlayer $e) => $e->seasonTeam->team->name,
            'N.º' => fn (SeasonTeamPlayer $e) => $e->shirt_number,
            'Apellidos' => fn (SeasonTeamPlayer $e) => $e->player->last_name,
            'Nombres' => fn (SeasonTeamPlayer $e) => $e->player->first_name,
            'DNI' => fn (SeasonTeamPlayer $e) => $e->player->dni,
            'Edad' => fn (SeasonTeamPlayer $e) => $e->age,
            'Género' => fn (SeasonTeamPlayer $e) => self::genderLabel($e->player->gender),
            'Estado' => fn (SeasonTeamPlayer $e) => $e->status->label(),
            'Documentos' => fn (SeasonTeamPlayer $e) => $e->player->documents_verified_at ? 'Verificados' : 'Pendientes',
            'Resolución' => fn (SeasonTeamPlayer $e) => $e->player->resolution_path ? 'Sí' : 'No',
        ];
    }

    /** Una persona: Varon/Dama (Gender::label() es el de la competencia: Varones/Damas). */
    private static function genderLabel(?Gender $gender): ?string
    {
        return match ($gender) {
            Gender::Male => 'Varón',
            Gender::Female => 'Dama',
            default => null,
        };
    }

    private function service(): ParticipantRosterService
    {
        return app(ParticipantRosterService::class);
    }
}
