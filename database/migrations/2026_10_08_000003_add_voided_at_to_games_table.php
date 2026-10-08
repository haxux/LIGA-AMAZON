<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un partido que pasó su fecha sin jugarse se anula de oficio: queda 0-0 y
 * cuenta como jugado, pero no reparte puntos ni resultado a nadie (ver
 * `GameSchedulingService::voidOverdueGames()`).
 *
 * `voided_at` es lo que distingue esa anulación de un 0-0 real —que sí
 * reparte el punto del empate a cada lado—: `StandingsService` y
 * `ClubSeasonService` lo leen para saltarse ganados/empatados/perdidos sin
 * dejar de contar el partido como jugado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->timestamp('voided_at')->nullable()->after('away_score');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('voided_at');
        });
    }
};
