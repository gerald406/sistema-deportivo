<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ultimo dia en que el DELEGADO puede inscribir a su delegacion en una
     * disciplina (D22; bases VII.a: hasta el 23/10). Pasada la fecha solo el
     * organizador o el admin inscriben. Vacio = sin cierre.
     */
    public function up(): void
    {
        Schema::table('games_editions', function (Blueprint $table) {
            $table->date('registration_deadline')->nullable()->after('end_date');
        });
    }

    public function down(): void
    {
        Schema::table('games_editions', function (Blueprint $table) {
            $table->dropColumn('registration_deadline');
        });
    }
};
