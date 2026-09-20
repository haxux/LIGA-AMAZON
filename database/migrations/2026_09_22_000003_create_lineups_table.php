<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 10: el once ideal que arma el técnico.
 *
 * Pertenece al equipo-temporada y no al club: una alineación de 2025/26 no
 * describe la plantilla de 2026/27 (design D6).
 *
 * Se guardan HUECOS, no coordenadas: "el hueco 7 de un 4-3-3". Guardar píxeles
 * ataría el dato al tamaño del campo dibujado hoy; así sobrevive a cualquier
 * rediseño y se pinta igual en el panel y en la ficha pública.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('lineups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('formation');
            $table->timestamps();
        });

        Schema::create('lineup_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lineup_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('slot');
            $table->timestamps();

            $table->unique(['lineup_id', 'slot']);
            $table->unique(['lineup_id', 'player_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lineup_slots');
        Schema::dropIfExists('lineups');
    }
};
