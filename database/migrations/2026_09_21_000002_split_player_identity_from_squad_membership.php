<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 9: `players` mezclaba dos cosas distintas — quién es un jugador y dónde
 * juega este año — y esa mezcla es la razón de que una plantilla fuera por
 * temporada y hubiera que teclearla entera cada vez.
 *
 * Se parten:
 *   - `players` conserva la identidad y gana `club_id`, el club propietario.
 *   - `squad_memberships` guarda la pertenencia a la plantilla de una temporada,
 *     con su dorsal y su tipo (propiedad o cesión).
 *
 * El `unique(team_id, shirt_number)` que `players` tiene hoy no se inventa de
 * nuevo: se muda tal cual a la tabla de pertenencias, que es donde significa
 * algo. Ahí además deja de ser falso — el dorsal cambia de temporada.
 *
 * El estadio pasa al club por el mismo motivo: no cambia al cambiar de año.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('squad_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('shirt_number');
            $table->string('type')->default('owned');
            $table->timestamps();

            $table->unique(['team_id', 'shirt_number']);
            $table->unique(['team_id', 'player_id']);
        });

        Schema::table('players', function (Blueprint $table) {
            $table->foreignId('club_id')->nullable()->after('id')
                ->constrained()->cascadeOnDelete();
        });

        Schema::table('stadiums', function (Blueprint $table) {
            $table->foreignId('club_id')->nullable()->after('id')
                ->constrained()->cascadeOnDelete();
        });

        $this->backfill();

        Schema::table('stadiums', function (Blueprint $table) {
            $table->unique('club_id');
        });

        // La FK primero y el índice después: en MySQL el unique(team_id, ...)
        // es también el índice que sostiene la clave ajena, así que soltarlo
        // mientras la FK vive falla con errno 1553.
        Schema::table('players', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
        });

        Schema::table('stadiums', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
        });

        Schema::table('players', function (Blueprint $table) {
            $table->dropUnique(['team_id', 'shirt_number']);
        });

        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn(['team_id', 'shirt_number']);
            $table->foreignId('club_id')->nullable(false)->change();
        });

        Schema::table('stadiums', function (Blueprint $table) {
            $table->dropUnique(['team_id']);
            $table->dropColumn('team_id');
            $table->foreignId('club_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('shirt_number')->nullable()->after('birth_date');
        });

        Schema::table('stadiums', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        // Una pertenencia por jugador al revertir: si un jugador estuvo en más
        // de una temporada, gana la más reciente, porque el esquema viejo sólo
        // sabe representar una.
        foreach (DB::table('squad_memberships')->orderBy('team_id')->get() as $membership) {
            DB::table('players')->where('id', $membership->player_id)->update([
                'team_id' => $membership->team_id,
                'shirt_number' => $membership->shirt_number,
            ]);
        }

        foreach (DB::table('stadiums')->get() as $stadium) {
            $teamId = DB::table('teams')->where('club_id', $stadium->club_id)->orderByDesc('season_id')->value('id');
            DB::table('stadiums')->where('id', $stadium->id)->update(['team_id' => $teamId]);
        }

        Schema::table('players', function (Blueprint $table) {
            $table->unique(['team_id', 'shirt_number']);
            $table->dropConstrainedForeignId('club_id');
        });

        Schema::table('stadiums', function (Blueprint $table) {
            $table->unique('team_id');
            $table->dropUnique(['club_id']);
            $table->dropConstrainedForeignId('club_id');
        });

        Schema::dropIfExists('squad_memberships');
    }

    /**
     * Cada fila de `players` de hoy es, a la vez, una identidad y una
     * pertenencia: se copia la segunda a la tabla nueva y se apunta la primera
     * al club de ese equipo.
     */
    private function backfill(): void
    {
        foreach (DB::table('players')->orderBy('id')->get() as $player) {
            $clubId = DB::table('teams')->where('id', $player->team_id)->value('club_id');

            DB::table('players')->where('id', $player->id)->update(['club_id' => $clubId]);

            DB::table('squad_memberships')->insert([
                'team_id' => $player->team_id,
                'player_id' => $player->id,
                'shirt_number' => $player->shirt_number,
                'type' => 'owned',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach (DB::table('stadiums')->orderBy('id')->get() as $stadium) {
            DB::table('stadiums')->where('id', $stadium->id)->update([
                'club_id' => DB::table('teams')->where('id', $stadium->team_id)->value('club_id'),
            ]);
        }
    }
};
