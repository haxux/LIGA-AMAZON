<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 9: el club pasa a existir por sí mismo, y `teams` deja de ser "el equipo"
 * para ser la participación de un club en una temporada y una división.
 *
 * Hasta ahora un club no existía entre temporadas: `teams` colgaba de
 * `season_id` y llevaba encima el nombre, el escudo y el año de fundación, así
 * que "Manaos FC 2025/26" y "Manaos FC 2026/27" eran dos filas sin ningún
 * vínculo. Los trofeos, el técnico a cargo y el historial de fichajes necesitan
 * lo contrario.
 *
 * Reversión: `down()` devuelve las cuatro columnas a `teams` y las rellena desde
 * el club. Es correcto mientras un club tenga una única fila de `teams` por
 * temporada — que es justo lo que garantiza el índice unique(season_id, club_id)
 * que se crea aquí.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('clubs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('short_name');
            $table->string('crest_path')->nullable();
            $table->unsignedSmallInteger('founded_year')->nullable();
            $table->timestamps();
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->foreignId('club_id')->nullable()->after('season_id')
                ->constrained()->restrictOnDelete();
        });

        $this->backfill();

        // Ensanchar antes de estrechar, igual que en add_division_id_to_matchdays:
        // en MySQL el unique(season_id, name) es también el índice que sostiene la
        // FK de season_id, y soltarlo mientras es el único que empieza por
        // season_id falla con errno 1553.
        Schema::table('teams', function (Blueprint $table) {
            $table->unique(['season_id', 'club_id']);
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropUnique(['season_id', 'name']);
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['name', 'short_name', 'crest_path', 'founded_year']);
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->foreignId('club_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('name')->nullable()->after('season_id');
            $table->string('short_name')->nullable()->after('name');
            $table->string('crest_path')->nullable()->after('short_name');
            $table->unsignedSmallInteger('founded_year')->nullable()->after('crest_path');
        });

        foreach (DB::table('clubs')->get() as $club) {
            DB::table('teams')->where('club_id', $club->id)->update([
                'name' => $club->name,
                'short_name' => $club->short_name,
                'crest_path' => $club->crest_path,
                'founded_year' => $club->founded_year,
            ]);
        }

        Schema::table('teams', function (Blueprint $table) {
            $table->unique(['season_id', 'name']);
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropUnique(['season_id', 'club_id']);
            $table->dropConstrainedForeignId('club_id');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
            $table->string('short_name')->nullable(false)->change();
        });

        Schema::dropIfExists('clubs');
    }

    /**
     * Un club por cada nombre distinto de `teams`. Dos temporadas que
     * escribieron el mismo club con nombres distintos ("Manaos FC" y "Manaos
     * F.C.") quedan como dos clubes: no hay forma automática de saber que son
     * el mismo, y adivinarlo sería peor que dejarlo a la vista para que el
     * administrador los una a mano.
     */
    private function backfill(): void
    {
        $names = DB::table('teams')->select('name')->distinct()->orderBy('name')->pluck('name');

        foreach ($names as $name) {
            $source = DB::table('teams')->where('name', $name)->orderByDesc('season_id')->first();

            $clubId = DB::table('clubs')->insertGetId([
                'name' => $name,
                'short_name' => $source->short_name,
                'crest_path' => $source->crest_path,
                'founded_year' => $source->founded_year,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('teams')->where('name', $name)->update(['club_id' => $clubId]);
        }
    }
};
