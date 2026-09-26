<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_reopen_logs', function (Blueprint $table) {
            $table->id();

            // Nullable + SET NULL: un log de auditoria nunca debe desaparecer
            // porque el partido que audita fue borrado despues.
            $table->foreignId('match_id')->nullable()->constrained('matches')->nullOnDelete();

            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('previous_status', 50);
            $table->json('previous_state')->nullable()->comment('Snapshot del resultado previo, segun el deporte');
            $table->unsignedInteger('events_deleted')->default(0);
            $table->unsignedInteger('suspensions_deleted')->default(0);
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE match_reopen_logs ADD CONSTRAINT chk_reopen_logs_previous_state_json CHECK (previous_state IS NULL OR JSON_TYPE(previous_state) = 'OBJECT')");
    }

    public function down(): void
    {
        Schema::dropIfExists('match_reopen_logs');
    }
};
