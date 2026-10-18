<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El club con el que negocia el presidente.
 *
 * En el hilo con el presidente el otro lado no es un club de la liga: él hace
 * de cualquier club de fuera —el que vende al jugador del catálogo, o el que
 * compra al que sale—. Lo escribe a mano al empezar a negociar, viaja con cada
 * contraoferta y acaba como el club de fuera del traspaso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->string('counterpart', 120)->nullable()->after('tension');
        });
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn('counterpart');
        });
    }
};
