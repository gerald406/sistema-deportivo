<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();

            // Referencia al roster (season_team_player), no al jugador global:
            // garantiza que el evento solo puede registrarse a un jugador
            // efectivamente inscrito en ese equipo, en esa temporada.
            $table->foreignId('season_team_player_id')->constrained('season_team_player')->cascadeOnDelete();

            $table->foreignId('event_type_id')->constrained('event_types')->restrictOnDelete();
            $table->integer('minute')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_events');
    }
};
