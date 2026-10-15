<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las zonas de la tabla también valen para la fase de grupos de una copa: «pasa
 * a octavos», «pasa a la Sudamericana»… Es la misma banda de colores sobre las
 * posiciones, así que se reutiliza la tabla en vez de crear una gemela.
 *
 * Una zona es de una división O de una copa. En una copa se define una sola vez
 * y vale para todos sus grupos, que tienen las mismas reglas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('standing_zones', function (Blueprint $table) {
            $table->dropForeign(['division_id']);
        });

        Schema::table('standing_zones', function (Blueprint $table) {
            $table->foreignId('division_id')->nullable()->change();
        });

        Schema::table('standing_zones', function (Blueprint $table) {
            $table->foreign('division_id')->references('id')->on('divisions')->cascadeOnDelete();
            $table->foreignId('cup_id')->nullable()->after('division_id')->constrained()->cascadeOnDelete();
            $table->unique(['cup_id', 'label']);
        });
    }

    public function down(): void
    {
        Schema::table('standing_zones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cup_id');
        });
    }
};
