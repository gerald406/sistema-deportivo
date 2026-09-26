<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suspensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();

            // Referencia al roster, no al jugador global (mismo criterio que match_events).
            $table->foreignId('season_team_player_id')->constrained('season_team_player')->cascadeOnDelete();

            // Se mantiene en RESTRICT deliberadamente: un partido con sanciones
            // asociadas no debe poder borrarse de forma directa. El borrado de
            // una season completa (caso excepcional) debe hacerse desde un
            // Service que elimine en orden (suspensions -> match_events ->
            // event_participants -> matches -> matchdays -> season) dentro de
            // una transaccion, en vez de depender de una cascada automatica de
            // MySQL sobre un grafo con conflictos.
            $table->foreignId('match_id')->constrained('matches')->restrictOnDelete();

            $table->string('reason', 255)->comment('Ej: Roja Directa, Doble Amarilla');
            $table->integer('matches_suspended')->default(1);
            $table->boolean('is_served')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suspensions');
    }
};
