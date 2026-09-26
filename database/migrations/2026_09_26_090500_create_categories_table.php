<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('Ej: Sub-17, Libre, Damas, Varones Master');
            $table->index('name');
            $table->integer('min_age')->nullable();
            $table->integer('max_age')->nullable();
            $table->enum('gender', ['M', 'F', 'X'])->default('X');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
