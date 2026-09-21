<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un técnico puede proponer por alguien que todavía no existe en la liga.
 *
 * Desde su panel sólo se propone hacia FUERA —lo de dentro se negocia por el
 * chat—, así que en un fichaje o una cesión que entra el jugador no tiene ficha
 * todavía: se propone por su nombre, y la ficha la crea el administrador
 * cuando acepta y completa la operación.
 *
 * De ahí que `player_id` pase a ser opcional y aparezca `external_player`. Una
 * fila nunca lleva los dos: o señala a un jugador de la liga, o nombra a uno de
 * fuera.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->string('external_player')->nullable()->after('player_id');
        });

        // SQLite no sabe cambiar una columna con FK sin recrear la tabla, y
        // doctrine/dbal ya no existe en Laravel 12+: se suelta la FK, se ablanda
        // la columna y se vuelve a atar.
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropForeign(['player_id']);
        });

        Schema::table('transfers', function (Blueprint $table) {
            $table->foreignId('player_id')->nullable()->change();
        });

        Schema::table('transfers', function (Blueprint $table) {
            $table->foreign('player_id')->references('id')->on('players')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropColumn('external_player');
        });
    }
};
