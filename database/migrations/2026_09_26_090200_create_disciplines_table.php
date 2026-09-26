<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disciplines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sport_id')->constrained()->restrictOnDelete();
            $table->string('name', 100)->comment('Ej: 100 metros llanos, Posta 4x100, Ajedrez clasico');
            $table->enum('format_type', ['head_to_head', 'individual_ranked', 'team_relay'])
                ->nullable()
                ->comment('Sobrescribe el formato del deporte padre cuando la disciplina se comporta distinto');
            $table->enum('unit_of_measure', ['time', 'distance', 'points', 'sets']);
            $table->enum('better_direction', ['asc', 'desc'])->default('asc');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['sport_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disciplines');
    }
};
