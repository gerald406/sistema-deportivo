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
        /**
         * enforceMorphMap() es estricto: exige que TODO modelo usado en
         * CUALQUIER relacion polimorfica de la app este mapeado, no solo
         * los que nos interesan a nosotros. Por eso 'user' tambien va
         * aqui: Spatie usa morphs polimorficos en model_has_roles y
         * model_has_permissions (columna model_type) para poder asociar
         * roles/permisos a distintos tipos de modelo si algun dia se
         * necesitara. Sin esta entrada, cualquier operacion de roles
         * sobre User (assignRole, hasRole, etc.) revienta con
         * ClassMorphViolationException.
         *
         * 'season_team' / 'season_team_player' deben coincidir exactamente
         * con los valores permitidos por el CHECK constraint
         * 'chk_event_participants_type' de la migracion
         * create_event_participants_table. Nunca usar el nombre completo
         * de la clase como participant_type (rompe portabilidad si la app
         * se reorganiza).
         */
        Relation::enforceMorphMap([
            'user' => User::class,
            'season_team' => SeasonTeam::class,
            'season_team_player' => SeasonTeamPlayer::class,
        ]);

        /**
         * El admin tiene bypass total sobre cualquier Policy del sistema.
         * Devolver null (no false) cuando el usuario no es admin: eso deja
         * que la Policy correspondiente decida normalmente en vez de
         * denegar el acceso a organizador/delegado.
         */
        Gate::before(function ($user, string $ability) {
            return $user->hasRole('admin') ? true : null;
        });
    }
}
