<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 10: la posición general dice a qué se dedica un jugador —portero,
 * defensa, centrocampista, delantero—; la específica dice dónde juega
 * exactamente dentro de eso (LI, MCO, DFC…).
 *
 * Nullable a propósito: los 29 jugadores que ya hay no la tienen, y es el
 * técnico quien la asigna. Vocabulario en PHP, no enum de base de datos, como
 * el resto del proyecto.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->string('specific_position', 4)->nullable()->after('position');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn('specific_position');
        });
    }
};
