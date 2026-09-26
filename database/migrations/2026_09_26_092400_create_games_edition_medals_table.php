<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games_edition_medals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('games_edition_id')->constrained('games_editions')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams')->restrictOnDelete();
            $table->integer('gold')->default(0);
            $table->integer('silver')->default(0);
            $table->integer('bronze')->default(0);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable()->comment('Fecha del ultimo recalculo (Job/Observer)');

            $table->unique(['games_edition_id', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games_edition_medals');
    }
};
