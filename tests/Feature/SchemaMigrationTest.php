<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_seasons_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('seasons', [
            'id', 'name', 'start_date', 'end_date', 'created_at', 'updated_at',
        ]));
    }

    public function test_seasons_table_has_is_current_column(): void
    {
        $this->assertTrue(Schema::hasColumn('seasons', 'is_current'));
    }

    public function test_teams_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('teams', [
            'id', 'season_id', 'name', 'short_name', 'crest_path', 'founded_year', 'created_at', 'updated_at',
        ]));
    }

    public function test_stadiums_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('stadiums', [
            'id', 'team_id', 'name', 'city', 'capacity', 'created_at', 'updated_at',
        ]));
    }

    public function test_players_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('players', [
            'id', 'team_id', 'name', 'position', 'birth_date', 'shirt_number', 'created_at', 'updated_at',
        ]));
    }

    public function test_matchdays_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('matchdays', [
            'id', 'season_id', 'number', 'date', 'created_at', 'updated_at',
        ]));
    }

    public function test_games_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('games', [
            'id', 'matchday_id', 'home_team_id', 'away_team_id', 'kickoff_at', 'home_score', 'away_score', 'created_at', 'updated_at',
        ]));
    }

    public function test_divisions_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('divisions', [
            'id', 'season_id', 'name', 'created_at', 'updated_at',
        ]));
    }

    public function test_teams_table_has_division_id_column(): void
    {
        $this->assertTrue(Schema::hasColumn('teams', 'division_id'));
    }

    public function test_duplicate_division_name_within_same_season_is_rejected(): void
    {
        $seasonId = DB::table('seasons')->insertGetId(['name' => '2025/26', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('divisions')->insert(['season_id' => $seasonId, 'name' => 'Primera', 'created_at' => now(), 'updated_at' => now()]);

        $this->expectException(QueryException::class);
        DB::table('divisions')->insert(['season_id' => $seasonId, 'name' => 'Primera', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_duplicate_team_name_within_same_season_is_rejected(): void
    {
        $seasonId = DB::table('seasons')->insertGetId(['name' => '2025/26', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('teams')->insert(['season_id' => $seasonId, 'name' => 'River', 'short_name' => 'RIV', 'created_at' => now(), 'updated_at' => now()]);

        $this->expectException(QueryException::class);
        DB::table('teams')->insert(['season_id' => $seasonId, 'name' => 'River', 'short_name' => 'RIV2', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_duplicate_shirt_number_within_same_team_is_rejected(): void
    {
        $seasonId = DB::table('seasons')->insertGetId(['name' => '2025/26', 'created_at' => now(), 'updated_at' => now()]);
        $teamId = DB::table('teams')->insertGetId(['season_id' => $seasonId, 'name' => 'River', 'short_name' => 'RIV', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('players')->insert(['team_id' => $teamId, 'name' => 'Player A', 'position' => 'GK', 'shirt_number' => 1, 'created_at' => now(), 'updated_at' => now()]);

        $this->expectException(QueryException::class);
        DB::table('players')->insert(['team_id' => $teamId, 'name' => 'Player B', 'position' => 'DF', 'shirt_number' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_duplicate_matchday_number_within_same_season_is_rejected(): void
    {
        $seasonId = DB::table('seasons')->insertGetId(['name' => '2025/26', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('matchdays')->insert(['season_id' => $seasonId, 'number' => 1, 'created_at' => now(), 'updated_at' => now()]);

        $this->expectException(QueryException::class);
        DB::table('matchdays')->insert(['season_id' => $seasonId, 'number' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_duplicate_stadium_for_same_team_is_rejected(): void
    {
        $seasonId = DB::table('seasons')->insertGetId(['name' => '2025/26', 'created_at' => now(), 'updated_at' => now()]);
        $teamId = DB::table('teams')->insertGetId(['season_id' => $seasonId, 'name' => 'River', 'short_name' => 'RIV', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('stadiums')->insert(['team_id' => $teamId, 'name' => 'Monumental', 'city' => 'City', 'created_at' => now(), 'updated_at' => now()]);

        $this->expectException(QueryException::class);
        DB::table('stadiums')->insert(['team_id' => $teamId, 'name' => 'Second', 'city' => 'City', 'created_at' => now(), 'updated_at' => now()]);
    }
}
