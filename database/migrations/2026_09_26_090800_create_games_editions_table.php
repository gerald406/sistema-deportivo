<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games_editions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->comment('Ej: Juegos Deportivos Nacionales 2027');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->foreignId('host_venue_id')->nullable()->constrained('venues')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games_editions');
    }
};
