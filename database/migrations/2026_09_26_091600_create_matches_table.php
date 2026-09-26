<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->foreignId('matchday_id')->constrained()->cascadeOnDelete();
            $table->foreignId('discipline_id')->nullable()->constrained('disciplines')->nullOnDelete();
            $table->foreignId('venue_id')->nullable()->constrained('venues')->nullOnDelete();
            $table->enum('round_type', ['regular', 'heat', 'quarterfinal', 'semifinal', 'final'])->default('regular');
            $table->enum('modality', ['best_of_1', 'best_of_3', 'best_of_5', 'swiss_round', 'single_elimination'])->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->enum('status', ['pending', 'played', 'walkover', 'canceled'])->default('pending');
            $table->timestamps();

            $table->index('scheduled_at');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
