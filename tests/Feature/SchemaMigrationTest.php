<?php

namespace Tests\Feature;

use App\Models\News;
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
            'id', 'season_id', 'division_id', 'number', 'date', 'created_at', 'updated_at',
        ]));
    }

    public function test_standing_zones_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('standing_zones', [
            'id', 'division_id', 'label', 'color', 'from_position', 'to_position', 'created_at', 'updated_at',
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

    public function test_news_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('news', [
            'id', 'team_id', 'title', 'slug', 'body', 'cover_path', 'published_at', 'created_at', 'updated_at',
        ]));
    }

    public function test_news_model_maps_to_the_news_table(): void
    {
        $this->assertSame('news', (new News)->getTable());
    }

    public function test_duplicate_news_slug_is_rejected(): void
    {
        DB::table('news')->insert(['title' => 'A', 'slug' => 'gran-victoria', 'body' => 'Body', 'created_at' => now(), 'updated_at' => now()]);

        $this->expectException(QueryException::class);
        DB::table('news')->insert(['title' => 'B', 'slug' => 'gran-victoria', 'body' => 'Body 2', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_news_item_persists_with_a_null_team_id(): void
    {
        $id = DB::table('news')->insertGetId(['title' => 'Untagged', 'slug' => 'untagged-news', 'body' => 'Body', 'team_id' => null, 'created_at' => now(), 'updated_at' => now()]);

        $this->assertNotNull(DB::table('news')->where('id', $id)->first());
    }

    public function test_game_events_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('game_events', [
            'id', 'game_id', 'player_id', 'type', 'minute', 'created_at', 'updated_at',
        ]));
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

    public function test_duplicate_matchday_number_within_same_division_is_rejected(): void
    {
        $seasonId = DB::table('seasons')->insertGetId(['name' => '2025/26', 'created_at' => now(), 'updated_at' => now()]);
        $divisionId = DB::table('divisions')->insertGetId(['season_id' => $seasonId, 'name' => 'Primera', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('matchdays')->insert(['season_id' => $seasonId, 'division_id' => $divisionId, 'number' => 1, 'created_at' => now(), 'updated_at' => now()]);

        $this->expectException(QueryException::class);
        DB::table('matchdays')->insert(['season_id' => $seasonId, 'division_id' => $divisionId, 'number' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    /**
     * The unique key widened from (season_id, number) to
     * (season_id, division_id, number): each division runs its own
     * calendar, so Primera and Segunda both own a Jornada 1.
     */
    public function test_same_matchday_number_in_two_divisions_of_one_season_is_allowed(): void
    {
        $seasonId = DB::table('seasons')->insertGetId(['name' => '2025/26', 'created_at' => now(), 'updated_at' => now()]);
        $primeraId = DB::table('divisions')->insertGetId(['season_id' => $seasonId, 'name' => 'Primera', 'created_at' => now(), 'updated_at' => now()]);
        $segundaId = DB::table('divisions')->insertGetId(['season_id' => $seasonId, 'name' => 'Segunda', 'created_at' => now(), 'updated_at' => now()]);

        DB::table('matchdays')->insert(['season_id' => $seasonId, 'division_id' => $primeraId, 'number' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('matchdays')->insert(['season_id' => $seasonId, 'division_id' => $segundaId, 'number' => 1, 'created_at' => now(), 'updated_at' => now()]);

        $this->assertSame(2, DB::table('matchdays')->where('season_id', $seasonId)->where('number', 1)->count());
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
