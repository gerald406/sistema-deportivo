<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scoring_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->string('phase_name', 100)->default('Fase Regular');
            $table->integer('pts_win')->default(3);
            $table->integer('pts_draw')->default(1);
            $table->integer('pts_loss')->default(0);
            $table->integer('pts_walkover')->default(-1);
            $table->json('rules')->nullable()->comment('Reglas adicionales por margen de resultado');
            $table->timestamps();

            $table->unique(['season_id', 'phase_name']);
        });

        DB::statement("ALTER TABLE scoring_configs ADD CONSTRAINT chk_scoring_configs_rules_json CHECK (rules IS NULL OR JSON_TYPE(rules) = 'OBJECT')");
    }

    public function down(): void
    {
        Schema::dropIfExists('scoring_configs');
    }
};
