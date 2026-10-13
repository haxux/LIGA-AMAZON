<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A quién perjudica una anulación: `home`, `away` o `both`. Con un solo lado
 * el partido se da por perdido 0-3 para ése (el marcador reglamentario se
 * guarda en `home_score`/`away_score` y reparte puntos como cualquier otro);
 * con `both` queda 0-0 sin repartir nada, que es lo que ya hacía la anulación
 * automática. Las anuladas de antes eran todas así, de ahí el relleno.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->string('voided_against', 4)->nullable()->after('voided_at');
        });

        DB::table('games')->whereNotNull('voided_at')->update(['voided_against' => 'both']);
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('voided_against');
        });
    }
};
