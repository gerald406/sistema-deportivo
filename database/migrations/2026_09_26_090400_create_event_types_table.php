<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sport_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50)->comment('Ej: goal, yellow_card, red_card, red_card_indirect, block_point');
            $table->string('name', 100);
            $table->timestamps();

            $table->unique(['sport_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_types');
    }
};
