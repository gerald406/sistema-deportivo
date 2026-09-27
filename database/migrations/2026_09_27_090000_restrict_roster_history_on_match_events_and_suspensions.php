<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Con CASCADE sobre season_team_player_id, dar de baja a un jugador
     * del roster borraba en silencio sus goles/tarjetas (match_events) y
     * sanciones (suspensions), mientras que event_lineups ya usaba
     * RESTRICT. Se unifica a RESTRICT: un jugador con historial en la
     * temporada no se puede quitar del roster (se usa el status de
     * season_team_player en su lugar).
     *
     * No afecta el borrado de un partido (match_id sigue en CASCADE en
     * match_events) ni el borrado ordenado de una season completa, que
     * ya debe hacerse desde un Service (ver create_suspensions_table).
     */
    public function up(): void
    {
        Schema::table('match_events', function (Blueprint $table) {
            $table->dropForeign(['season_team_player_id']);
            $table->foreign('season_team_player_id')
                ->references('id')->on('season_team_player')
                ->restrictOnDelete();
        });

        Schema::table('suspensions', function (Blueprint $table) {
            $table->dropForeign(['season_team_player_id']);
            $table->foreign('season_team_player_id')
                ->references('id')->on('season_team_player')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('match_events', function (Blueprint $table) {
            $table->dropForeign(['season_team_player_id']);
            $table->foreign('season_team_player_id')
                ->references('id')->on('season_team_player')
                ->cascadeOnDelete();
        });

        Schema::table('suspensions', function (Blueprint $table) {
            $table->dropForeign(['season_team_player_id']);
            $table->foreign('season_team_player_id')
                ->references('id')->on('season_team_player')
                ->cascadeOnDelete();
        });
    }
};
