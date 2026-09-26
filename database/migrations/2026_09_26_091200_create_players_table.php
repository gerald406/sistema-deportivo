<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->string('dni', 20)->unique();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->index('last_name');
            $table->date('birth_date')->nullable();
            $table->string('photo_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE players ADD CONSTRAINT chk_players_dni_length CHECK (CHAR_LENGTH(dni) BETWEEN 8 AND 12)');
    }

    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
