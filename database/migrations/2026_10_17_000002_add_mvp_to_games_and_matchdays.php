<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El MVP del partido y el jugador de la jornada (Week MVP).
 *
 * No son eventos: no ocurren en un minuto del partido, los elige el
 * administrador después, y por eso son una columna del propio partido y de la
 * propia jornada. Opcionales los dos. Si el jugador se borrara, la elección
 * simplemente queda sin nombre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->foreignId('mvp_player_id')->nullable()->constrained('players')->nullOnDelete();
        });

        Schema::table('matchdays', function (Blueprint $table) {
            $table->foreignId('week_mvp_player_id')->nullable()->constrained('players')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('matchdays', function (Blueprint $table) {
            $table->dropConstrainedForeignId('week_mvp_player_id');
        });

        Schema::table('games', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mvp_player_id');
        });
    }
};
