<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cuadro "Puntaje total por categorias" de una olimpiada (pag. 9 de las
     * bases de Puno 2026). Cada fila es un deporte o actividad en un nivel,
     * con los puntos que da el 1.o, 2.o y 3.o puesto al puntaje general y
     * los topes de plantel. Todo es dato editable por el admin: un campo
     * vacio significa "sin puntos" o "sin restriccion".
     */
    public function up(): void
    {
        Schema::create('games_edition_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('games_edition_id')->constrained('games_editions')->cascadeOnDelete();
            $table->integer('sort_order')->default(0);
            $table->string('label', 150)->comment('Ej: Futsal Master');
            $table->foreignId('sport_id')->nullable()->constrained('sports')->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->enum('gender_scope', ['male', 'female', 'both', 'none'])->default('none');
            $table->boolean('separate_podiums')->default(true)
                ->comment('Con alcance "both": un podio por genero (true) o uno combinado (false)');
            $table->boolean('per_discipline')->default(false)
                ->comment('Una competencia por cada prueba del deporte (atletismo)');
            $table->integer('pts_first')->nullable();
            $table->integer('pts_second')->nullable();
            $table->integer('pts_third')->nullable();
            $table->integer('min_players')->nullable();
            $table->integer('max_players')->nullable();
            $table->boolean('rules_blocking')->default(false)
                ->comment('Las reglas de plantel bloquean (true) o solo advierten (false)');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['games_edition_id', 'label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games_edition_events');
    }
};
