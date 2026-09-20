<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A matchday now belongs to a division, not just to a season: each division
 * runs its own calendar, so Primera and Segunda each own a Jornada 1. The
 * unique key widens from (season_id, number) to (season_id, division_id, number).
 *
 * Three steps, in this order, because the column ends up NOT NULL over a
 * table that already holds rows: add it nullable, backfill it, then tighten it.
 *
 * Rollback: down() drops the column and restores the (season_id, number)
 * unique key. That restore FAILS LOUDLY — by design — if two divisions of one
 * season already share a matchday number, since there is no correct way to
 * squeeze those rows back into the narrower key. Resolve the duplicates (or
 * delete the extra division's matchdays) before rolling back.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('matchdays', function (Blueprint $table) {
            $table->foreignId('division_id')->nullable()->after('season_id')
                ->constrained()->cascadeOnDelete();
        });

        $this->backfill();

        // Widen first, narrow second — never the other way round. On MySQL the
        // (season_id, number) unique key doubles as the index backing the
        // season_id foreign key, so dropping it while it is the only index
        // starting with season_id fails with errno 1553. Adding
        // (season_id, division_id, number) first hands that duty over.
        Schema::table('matchdays', function (Blueprint $table) {
            $table->unique(['season_id', 'division_id', 'number']);
        });

        Schema::table('matchdays', function (Blueprint $table) {
            $table->dropUnique(['season_id', 'number']);
        });

        Schema::table('matchdays', function (Blueprint $table) {
            $table->foreignId('division_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Same index dance as up(), mirrored: restore the narrow key before
        // dropping the wide one, so season_id is never left unindexed.
        Schema::table('matchdays', function (Blueprint $table) {
            $table->unique(['season_id', 'number']);
        });

        Schema::table('matchdays', function (Blueprint $table) {
            $table->dropUnique(['season_id', 'division_id', 'number']);
            $table->dropConstrainedForeignId('division_id');
        });
    }

    /**
     * Every pre-existing matchday lands in its season's first division. A
     * season that has matchdays but no division at all gets one named
     * 'Primera' — the column is about to become NOT NULL, so there has to be
     * something to point at, and that is the seeder's own name for the top
     * flight. An admin can rename it afterwards.
     */
    private function backfill(): void
    {
        $seasonIds = DB::table('matchdays')->whereNull('division_id')->distinct()->pluck('season_id');

        foreach ($seasonIds as $seasonId) {
            $divisionId = DB::table('divisions')->where('season_id', $seasonId)->orderBy('id')->value('id')
                ?? DB::table('divisions')->insertGetId([
                    'season_id' => $seasonId,
                    'name' => 'Primera',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::table('matchdays')
                ->where('season_id', $seasonId)
                ->whereNull('division_id')
                ->update(['division_id' => $divisionId]);
        }
    }
};
