<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una copa con «mejores terceros» tiene DOS tablas con zonas: la de cada grupo
 * y la de los terceros puestos a competir. Cada una cuenta posiciones desde 1,
 * así que sus bandas no pueden mezclarse: `stage` dice a cuál pertenece.
 *
 * La unicidad del nombre pasa a ser por etapa: «Pasa a octavos» puede existir
 * en las dos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('standing_zones', function (Blueprint $table) {
            $table->string('stage', 8)->default('groups')->after('cup_id');
            // El nuevo índice empieza por cup_id, así que sigue sosteniendo la
            // clave foránea cuando se suelta el viejo.
            $table->unique(['cup_id', 'stage', 'label']);
        });

        Schema::table('standing_zones', function (Blueprint $table) {
            $table->dropUnique(['cup_id', 'label']);
        });
    }

    public function down(): void
    {
        Schema::table('standing_zones', function (Blueprint $table) {
            $table->unique(['cup_id', 'label']);
        });

        Schema::table('standing_zones', function (Blueprint $table) {
            $table->dropUnique(['cup_id', 'stage', 'label']);
            $table->dropColumn('stage');
        });
    }
};
