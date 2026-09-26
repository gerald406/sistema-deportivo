<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\SeasonTeam;
use App\Models\SeasonTeamPlayer;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Relation::enforceMorphMap([
            'user' => User::class,
            'season_team' => SeasonTeam::class,
            'season_team_player' => SeasonTeamPlayer::class,
        ]);

        Gate::before(function ($user, string $ability) {
            return $user->hasRole('admin') ? true : null;
        });
    }
}
