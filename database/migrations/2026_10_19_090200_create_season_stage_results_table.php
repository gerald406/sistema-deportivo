<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Podio directo de una fase (D13): 1.o, 2.o y 3.o registrados por el
     * organizador, sin partidos ni marcas. Un puesto admite varias filas
     * (puesto compartido). El participante individual es opcional: el
     * premio es de la delegacion.
     */
    public function up(): void
    {
        Schema::create('season_stage_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_stage_id')->constrained('season_stages')->cascadeOnDelete();
            $table->unsignedTinyInteger('place')->comment('1, 2 o 3');
            $table->foreignId('team_id')->constrained('teams')->restrictOnDelete();
            $table->foreignId('season_team_player_id')->nullable()->constrained('season_team_player')->restrictOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['season_stage_id', 'place', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('season_stage_results');
    }
};
