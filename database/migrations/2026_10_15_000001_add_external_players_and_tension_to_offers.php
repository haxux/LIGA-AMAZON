<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El chat con el presidente: se negocia por jugadores que todavía no están en
 * la liga.
 *
 * Una oferta que PIDE un jugador al presidente no apunta a una fila de
 * `players`, porque ese jugador sólo existe en el catálogo de EA FC: guarda su
 * `fc_id` y su nombre, y la ficha nace cuando el presidente cierra el
 * traspaso. De ahí que `player_id` pase a ser opcional.
 *
 * `tension` es lo que el presidente dice que lleva la negociación, de 1 a 100.
 * Es información para el técnico; no cambia lo que se puede hacer.
 *
 * `transfers.fc_id` lleva el jugador del catálogo hasta el formulario de cierre
 * para que la ficha se rellene sola.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Mismo rodeo que en `allow_transfers_for_players_outside_the_league`:
        // SQLite no cambia una columna con FK sin recrear la tabla.
        Schema::table('offers', function (Blueprint $table) {
            $table->dropForeign(['player_id']);
        });

        Schema::table('offers', function (Blueprint $table) {
            $table->foreignId('player_id')->nullable()->change();
        });

        Schema::table('offers', function (Blueprint $table) {
            $table->foreign('player_id')->references('id')->on('players')->cascadeOnDelete();
            $table->unsignedBigInteger('fc_id')->nullable()->after('player_id');
            $table->string('external_player')->nullable()->after('fc_id');
            $table->unsignedTinyInteger('tension')->nullable()->after('loan_term');
        });

        Schema::table('transfers', function (Blueprint $table) {
            $table->unsignedBigInteger('fc_id')->nullable()->after('external_player');
        });
    }

    public function down(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropColumn('fc_id');
        });

        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn(['fc_id', 'external_player', 'tension']);
        });
    }
};
