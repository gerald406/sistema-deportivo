<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->unsignedTinyInteger('period_number')->comment('Set, cuarto o parcial (1, 2, 3...)');
            $table->integer('home_points')->default(0);
            $table->integer('away_points')->default(0);
            $table->timestamps();

            $table->unique(['match_id', 'period_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_periods');
    }
};
