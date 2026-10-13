<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fases de una competencia (Art. 8 y 10 de las bases): cada serie y la
     * fase final. Una fase se juega con partidos (llave de eliminacion) o
     * es de podio directo (solo se registra 1.o, 2.o y 3.o).
     */
    public function up(): void
    {
        Schema::create('season_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained('seasons')->cascadeOnDelete();
            $table->string('name', 80)->comment('Ej: Serie A, Fase final, Final');
            $table->integer('sort_order')->default(0);
            $table->enum('mode', ['matches', 'direct_podium']);
            $table->foreignId('group_id')->nullable()->constrained('games_edition_groups')->nullOnDelete();
            $table->integer('qualifiers')->nullable()->comment('Cuantos pasan a la fase siguiente; NULL = no aplica');
            $table->boolean('has_third_place')->default(false);
            $table->timestamps();

            $table->unique(['season_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('season_stages');
    }
};
