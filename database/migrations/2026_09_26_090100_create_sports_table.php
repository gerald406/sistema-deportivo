<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sports', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('Nombre visible del deporte (Ej: Futbol, Voley, Atletismo, Ajedrez)');
            $table->string('slug', 100)->unique()->comment('Identificador corto usado por los Services (Ej: futbol, voley, atletismo)');
            $table->enum('format_type', ['head_to_head', 'individual_ranked', 'team_relay'])
                ->default('head_to_head')
                ->comment('Formato de competencia del deporte');
            $table->json('config')->nullable()->comment('Configuracion flexible propia del deporte (JSON)');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::statement("ALTER TABLE sports ADD CONSTRAINT chk_sports_config_json CHECK (config IS NULL OR JSON_TYPE(config) = 'OBJECT')");
    }

    public function down(): void
    {
        Schema::dropIfExists('sports');
    }
};
