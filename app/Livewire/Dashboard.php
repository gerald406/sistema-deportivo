<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Team;
use App\Models\Tournament;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    public int $activeTournaments = 0;

    public int $activeTeams = 0;

    /**
     * Los contadores solo se calculan si el usuario tiene el permiso
     * correspondiente: evita una consulta innecesaria (y una fuga de
     * informacion vía timing) para roles que no gestionan ese recurso.
     */
    public function mount(): void
    {
        $user = auth()->user();

        if ($user->can('tournaments.manage')) {
            $this->activeTournaments = Tournament::where('is_active', true)->count();
        }

        if ($user->can('teams.manage')) {
            $this->activeTeams = Team::where('is_active', true)->count();
        }
    }

    public function render()
    {
        return view('livewire.dashboard');
    }
}
