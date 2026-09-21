<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un partido pasa a pertenecer a una jornada de liga, a un cruce de copa o a un
 * grupo de copa — y exactamente a uno de los tres, que es lo que vigila el
 * guard de `Game`.
 *
 * De ahí que `matchday_id` deje de ser obligatoria: hasta hoy un partido era de
 * una jornada por definición, y eso deja fuera cualquier competición que no se
 * juegue por jornadas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->foreignId('cup_tie_id')->nullable()->after('matchday_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cup_group_id')->nullable()->after('cup_tie_id')->constrained()->cascadeOnDelete();
            // La jornada del grupo, para ordenarlos: un grupo se juega en varias
            // tandas como una liga pequeña.
            $table->unsignedTinyInteger('group_matchday')->nullable()->after('cup_group_id');
        });

        Schema::table('games', function (Blueprint $table) {
            $table->dropForeign(['matchday_id']);
        });

        Schema::table('games', function (Blueprint $table) {
            $table->foreignId('matchday_id')->nullable()->change();
        });

        Schema::table('games', function (Blueprint $table) {
            $table->foreign('matchday_id')->references('id')->on('matchdays')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        // Los partidos de copa se van ANTES de volver a exigir la jornada: sin
        // esto la columna no puede volver a ser obligatoria y la vuelta atrás
        // fallaría a medias, que es peor que no poder deshacerla.
        DB::table('games')->whereNull('matchday_id')->delete();

        Schema::table('games', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cup_tie_id');
            $table->dropConstrainedForeignId('cup_group_id');
            $table->dropColumn('group_matchday');
        });
    }
};
