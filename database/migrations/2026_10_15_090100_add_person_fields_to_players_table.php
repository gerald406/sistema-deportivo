<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Datos del participante que piden las bases (VI): damas o varones,
     * docente o administrativo, nombrado o contratado. Nulos porque los
     * participantes ya registrados no los tienen; el formulario los pide.
     * documents_verified_at: el organizador reviso DNI y documentos.
     */
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->enum('gender', ['M', 'F'])->nullable()->after('birth_date');
            $table->enum('staff_type', ['teacher', 'administrative'])->nullable()->after('gender');
            $table->enum('employment_type', ['appointed', 'contracted'])->nullable()->after('staff_type');
            $table->timestamp('documents_verified_at')->nullable()->after('employment_type');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn(['gender', 'staff_type', 'employment_type', 'documents_verified_at']);
        });
    }
};
