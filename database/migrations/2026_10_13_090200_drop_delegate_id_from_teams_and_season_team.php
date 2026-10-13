<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El delegado deja de vivir en tres sitios (teams.delegate_id,
     * season_team.delegate_id y la precedencia entre ambos) y pasa a uno
     * solo: games_edition_teams (titular y suplente por olimpiada).
     *
     * Antes de borrar las columnas, los delegados generales ya asignados se
     * copian como titulares a la inscripcion de su delegacion en cada
     * olimpiada donde esta tenga competencias, para no perder a nadie.
     */
    public function up(): void
    {
        $pairs = DB::table('season_team')
            ->join('seasons', 'seasons.id', '=', 'season_team.season_id')
            ->join('teams', 'teams.id', '=', 'season_team.team_id')
            ->select('seasons.games_edition_id', 'season_team.team_id')
            ->selectRaw('MAX(COALESCE(season_team.delegate_id, teams.delegate_id)) as delegate_id')
            ->groupBy('seasons.games_edition_id', 'season_team.team_id')
            ->get();

        foreach ($pairs as $pair) {
            $exists = DB::table('games_edition_teams')
                ->where('games_edition_id', $pair->games_edition_id)
                ->where('team_id', $pair->team_id)
                ->exists();

            if (! $exists) {
                DB::table('games_edition_teams')->insert([
                    'games_edition_id' => $pair->games_edition_id,
                    'team_id' => $pair->team_id,
                    'delegate_id' => $pair->delegate_id,
                    'status' => 'registered',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Schema::table('teams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delegate_id');
        });

        Schema::table('season_team', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delegate_id');
        });
    }

    /** Solo restaura las columnas (vacias): la fuente ya es games_edition_teams. */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->foreignId('delegate_id')->nullable()->after('logo_path')->constrained('users')->nullOnDelete();
        });

        Schema::table('season_team', function (Blueprint $table) {
            $table->foreignId('delegate_id')->nullable()->after('category_id')->constrained('users')->nullOnDelete();
        });
    }
};
