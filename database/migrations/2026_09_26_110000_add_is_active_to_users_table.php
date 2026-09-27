<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mismo patron "sin soft deletes, usar is_active" que el resto del
     * sistema (teams, players, etc.). Un usuario desactivado no se borra
     * (preserva organizer_id/delegate_id historicos), simplemente no
     * puede iniciar sesion: ver FortifyServiceProvider::boot() en las
     * instrucciones de instalacion.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('email_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
