<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\SportFormatType;
use App\Models\EventParticipant;
use App\Models\GameMatch;
use App\Services\DashboardService;
use App\Services\GameMatchService;
use App\Support\ParticipantLabel;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    public function label(EventParticipant $p): string
    {
        return ParticipantLabel::for($p);
    }

    public function isHeadToHead(GameMatch $match): bool
    {
        return app(GameMatchService::class)->formatFor($match->season, $match->discipline) === SportFormatType::HeadToHead;
    }

    /**
     * Las metricas se calculan acotadas al ambito del usuario (ver
     * DashboardService); las que su rol no ve vuelven null y no se
     * muestran ni se consultan.
     */
    public function render(DashboardService $service)
    {
        $user = auth()->user();

        return view('livewire.dashboard', [
            'metrics' => $service->metrics($user),
            'upcoming' => $service->upcomingMatches($user),
            'latest' => $service->latestResults($user),
        ]);
    }
}
