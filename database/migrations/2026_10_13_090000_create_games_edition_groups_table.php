<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Series de una olimpiada (Ej: Serie A, B, C, D). Agrupan a las
     * delegaciones inscritas (games_edition_teams.group_id).
     */
    public function up(): void
    {
        Schema::create('games_edition_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('games_edition_id')->constrained('games_editions')->cascadeOnDelete();
            $table->string('name', 50)->comment('Ej: Serie A');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['games_edition_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games_edition_groups');
    }
};
