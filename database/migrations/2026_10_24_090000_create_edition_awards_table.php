<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Premios de una olimpiada: un registro por cada puesto de podio
     * (1.o, 2.o o 3.o) de cada competencia, con los puntos que suma al
     * puntaje general segun el cuadro (Art. 28 y pag. 9 de las bases).
     *
     * Es un dato DERIVADO: AwardService::rebuild() lo borra y lo vuelve a
     * calcular desde los podios y los resultados. Nunca se edita a mano.
     */
    public function up(): void
    {
        Schema::create('edition_awards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('games_edition_id')->constrained('games_editions')->cascadeOnDelete();
            $table->foreignId('season_id')->constrained('seasons')->cascadeOnDelete();
            $table->foreignId('games_edition_event_id')->nullable()->constrained('games_edition_events')->nullOnDelete();
            $table->foreignId('team_id')->constrained('teams')->restrictOnDelete();
            $table->unsignedTinyInteger('place')->comment('1, 2 o 3');
            $table->integer('points')->default(0);
            $table->timestamps();

            $table->index(['games_edition_id', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edition_awards');
    }
};
