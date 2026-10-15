<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Resolucion de nombramiento o contrato del participante (PDF, D20):
     * para verificar su condicion y atender reclamos. El archivo vive en el
     * disco PRIVADO (storage/app/private/resolutions) y solo se descarga por
     * una ruta autorizada. Quien lo subio y cuando quedan como evidencia.
     */
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->string('resolution_path')->nullable()->after('photo_path')
                ->comment('PDF en el disco privado; NULL = sin resolucion');
            $table->timestamp('resolution_uploaded_at')->nullable()->after('resolution_path');
            $table->foreignId('resolution_uploaded_by')->nullable()->after('resolution_uploaded_at')
                ->constrained('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropConstrainedForeignId('resolution_uploaded_by');
            $table->dropColumn(['resolution_path', 'resolution_uploaded_at']);
        });
    }
};
