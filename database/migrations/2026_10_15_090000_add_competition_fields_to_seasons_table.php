<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Una competencia (seasons) es deporte x nivel x genero (y prueba, en
     * atletismo) de la olimpiada. Los cuatro campos son opcionales: una
     * competencia creada a mano puede no tener fila del cuadro ni nivel.
     * Todas las FK son RESTRICT: no se borra una fila del cuadro, un nivel
     * o una prueba que ya tiene competencias.
     */
    public function up(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            $table->foreignId('games_edition_event_id')->nullable()->after('games_edition_id')
                ->constrained('games_edition_events')->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->after('sport_id')
                ->constrained('categories')->restrictOnDelete();
            $table->foreignId('discipline_id')->nullable()->after('category_id')
                ->constrained('disciplines')->restrictOnDelete();
            $table->enum('gender', ['M', 'F', 'X'])->nullable()->after('discipline_id')
                ->comment('M varones, F damas, X mixto; NULL = sin genero');
        });
    }

    public function down(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            $table->dropForeign(['games_edition_event_id']);
            $table->dropForeign(['category_id']);
            $table->dropForeign(['discipline_id']);
            $table->dropColumn(['games_edition_event_id', 'category_id', 'discipline_id', 'gender']);
        });
    }
};
