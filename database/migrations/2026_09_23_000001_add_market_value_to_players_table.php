<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 12: lo que vale un jugador.
 *
 * Un valor por jugador y no por temporada (decisión del propietario): el total
 * de una plantilla se calcula con los valores vigentes, no con los del año en
 * que se fichó a cada uno.
 *
 * Opcional, como la posición específica: los jugadores que ya están en la liga
 * no lo tienen y ponérselo es trabajo del administrador.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->unsignedBigInteger('market_value')->nullable()->after('specific_position');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn('market_value');
        });
    }
};
