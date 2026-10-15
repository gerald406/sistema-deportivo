<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Director(a) de la institucion: firma la ficha de inscripcion general
     * de cada disciplina y su declaracion jurada (bases VII.c y VII.d, D21).
     * Opcional: vacio = la ficha deja la linea para escribirlo a mano.
     */
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('director_name', 150)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('director_name');
        });
    }
};
