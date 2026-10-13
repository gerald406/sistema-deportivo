<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * tournaments desaparece: su papel (marca + organizador) lo cumple
     * games_editions. El permiso tournaments.manage se renombra a
     * editions.manage conservando a quien ya lo tenia asignado.
     */
    public function up(): void
    {
        Schema::dropIfExists('tournaments');

        DB::table('permissions')->where('name', 'tournaments.manage')->update(['name' => 'editions.manage']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('logo')->nullable();
            $table->foreignId('organizer_id')->constrained('users')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('permissions')->where('name', 'editions.manage')->update(['name' => 'tournaments.manage']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
