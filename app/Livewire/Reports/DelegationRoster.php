<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Enums\Gender;
use App\Livewire\Concerns\ExportsExcel;
use App\Livewire\Concerns\SelectsEdition;
use App\Services\ParticipantRosterService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Padron por delegacion (solo lectura, admin y organizador): una fila por
 * persona con todas sus disciplinas y su resolucion (PDF) en un modal.
 *
 * editionId y teamId vienen del navegador: la olimpiada se busca entre las
 * que el usuario gestiona (SelectsEdition) y ParticipantRosterService lo
 * revalida; la delegacion solo cuenta si esta entre las de la olimpiada.
 */
#[Layout('layouts.admin')]
#[Title('Padrón por delegación')]
class DelegationRoster extends Component
{
    use ExportsExcel, SelectsEdition;

    /** '' = todas las delegaciones. */
    #[Url(as: 'delegacion')]
    public string $teamId = '';

    public string $search = '';

    public function boot(): void
    {
        // El middleware de la ruta no se reaplica en /livewire/update.
        abort_unless(auth()->user()->hasAnyRole(['admin', 'organizador']), 403);
    }

    public function updatedEditionId(): void
    {
        $this->teamId = '';
    }

    public function delegationOptions(): Collection
    {
        $edition = $this->edition();

        return $edition ? $this->service()->delegationOptions(auth()->user(), $edition) : collect();
    }

    public function render()
    {
        return view('livewire.reports.delegation-roster', [
            'edition' => $this->edition(),
            'rows' => $this->rows(),
        ]);
    }

    /** Filas filtradas: las comparten render() y la exportacion a Excel. */
    private function rows(): Collection
    {
        $edition = $this->edition();

        return $edition
            ? $this->service()->byDelegation(auth()->user(), $edition, $this->selectedTeamId(), $this->search)
            : collect();
    }

    private function selectedTeamId(): ?int
    {
        return ctype_digit($this->teamId) && $this->delegationOptions()->contains('id', (int) $this->teamId)
            ? (int) $this->teamId
            : null;
    }

    protected function authorizeExport(): void
    {
        abort_unless(auth()->user()->hasAnyRole(['admin', 'organizador']), 403);
    }

    protected function exportTitle(): string
    {
        return 'Padrón por delegación';
    }

    protected function exportRows(): iterable
    {
        return $this->rows();
    }

    protected function exportColumns(): array
    {
        return [
            'Delegación' => fn ($r) => $r->team->name,
            'Apellidos' => fn ($r) => $r->player->last_name,
            'Nombres' => fn ($r) => $r->player->first_name,
            'DNI' => fn ($r) => $r->player->dni,
            'Edad' => fn ($r) => $r->age,
            'Género' => fn ($r) => self::genderLabel($r->player->gender),
            'Tipo' => fn ($r) => $r->player->staff_type?->label(),
            'Condición' => fn ($r) => $r->player->employment_type?->label(),
            'Disciplinas' => fn ($r) => $r->disciplines->implode(', '),
            'Documentos' => fn ($r) => $r->player->documents_verified_at ? 'Verificados' : 'Pendientes',
            'Resolución' => fn ($r) => $r->player->resolution_path ? 'Sí' : 'No',
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
