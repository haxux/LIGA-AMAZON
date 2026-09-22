<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('game_events', function (Blueprint $table) {
            // Enlaza una asistencia con el gol al que pertenece: nace al crear
            // el gol desde el propio formulario, en lugar de como un evento
            // suelto que sólo coincidía con él por casualidad de minuto.
            $table->foreignId('related_event_id')
                ->nullable()
                ->after('minute')
                ->constrained('game_events')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('related_event_id');
        });
    }
};
