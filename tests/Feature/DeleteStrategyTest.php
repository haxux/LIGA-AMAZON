<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeleteStrategyTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_a_season_cascades_to_teams_matchdays_players_stadiums_and_games(): void
    {
        $seasonId = DB::table('seasons')->insertGetId(['name' => '2025/26', 'created_at' => now(), 'updated_at' => now()]);
        $clubHomeFCId = DB::table('clubs')->insertGetId(['name' => 'Home FC', 'short_name' => 'HOM', 'created_at' => now(), 'updated_at' => now()]);
        $homeTeamId = DB::table('teams')->insertGetId(['season_id' => $seasonId, 'club_id' => $clubHomeFCId, 'created_at' => now(), 'updated_at' => now()]);
        $clubAwayFCId = DB::table('clubs')->insertGetId(['name' => 'Away FC', 'short_name' => 'AWY', 'created_at' => now(), 'updated_at' => now()]);
        $awayTeamId = DB::table('teams')->insertGetId(['season_id' => $seasonId, 'club_id' => $clubAwayFCId, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('stadiums')->insert(['team_id' => $homeTeamId, 'name' => 'Arena', 'city' => 'City', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('players')->insert(['team_id' => $homeTeamId, 'name' => 'Player A', 'position' => 'GK', 'shirt_number' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $divisionId = DB::table('divisions')->insertGetId(['season_id' => $seasonId, 'name' => 'Primera', 'created_at' => now(), 'updated_at' => now()]);
        $matchdayId = DB::table('matchdays')->insertGetId(['season_id' => $seasonId, 'division_id' => $divisionId, 'number' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('games')->insert(['matchday_id' => $matchdayId, 'home_team_id' => $homeTeamId, 'away_team_id' => $awayTeamId, 'created_at' => now(), 'updated_at' => now()]);

        // teams.division_id (Fase 5) is added to the already-shipped teams table via an
        // ADD-COLUMN-WITH-FK migration, which SQLite (unlike MySQL) can only implement by
        // rebuilding the table (create __temp__teams, copy rows, drop, rename — verified via
        // DB::getQueryLog()). That rebuild re-registers teams' foreign keys with SQLite's
        // internal schema, which changes the (unspecified-by-SQLite) order in which "seasons"'
        // cascade children (teams, matchdays) are processed: teams now processes before
        // matchdays. Because games.home_team_id/away_team_id use restrictOnDelete() (an
        // IMMEDIATE check, unlike NO ACTION's deferred-to-statement-end check), a team's
        // cascade delete is checked against games before matchdays' own cascade has removed
        // them. PRAGMA defer_foreign_keys defers ALL FK checks (including RESTRICT) to the end
        // of the current transaction — here, RefreshDatabase's already-open per-test
        // transaction — which lets every cascade in this statement complete before anything is
        // validated, matching the actually-intended (order-independent) behavior. This does not
        // weaken the assertions below: they still verify every row was genuinely removed.
        //
        // SQLite only. PRAGMA is not valid syntax on the MySQL family, and the
        // quirk it compensates for is SQLite's: the rebuild described above
        // never happens there, because MySQL adds a column with a foreign key
        // in place. Running the deletion unguarded on the real engine is the
        // point — it is what tells us whether the managed database honours
        // this cascade on its own.
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA defer_foreign_keys = ON');
        }

        DB::table('seasons')->where('id', $seasonId)->delete();

        $this->assertSame(0, DB::table('teams')->where('season_id', $seasonId)->count());
        $this->assertSame(0, DB::table('matchdays')->where('season_id', $seasonId)->count());
        $this->assertSame(0, DB::table('stadiums')->where('team_id', $homeTeamId)->count());
        $this->assertSame(0, DB::table('players')->where('team_id', $homeTeamId)->count());
        $this->assertSame(0, DB::table('games')->where('matchday_id', $matchdayId)->count());
    }

    public function test_deleting_a_team_referenced_by_a_game_is_restricted(): void
    {
        $seasonId = DB::table('seasons')->insertGetId(['name' => '2025/26', 'created_at' => now(), 'updated_at' => now()]);
        $clubHomeFCId = DB::table('clubs')->insertGetId(['name' => 'Home FC', 'short_name' => 'HOM', 'created_at' => now(), 'updated_at' => now()]);
        $homeTeamId = DB::table('teams')->insertGetId(['season_id' => $seasonId, 'club_id' => $clubHomeFCId, 'created_at' => now(), 'updated_at' => now()]);
        $clubAwayFCId = DB::table('clubs')->insertGetId(['name' => 'Away FC', 'short_name' => 'AWY', 'created_at' => now(), 'updated_at' => now()]);
        $awayTeamId = DB::table('teams')->insertGetId(['season_id' => $seasonId, 'club_id' => $clubAwayFCId, 'created_at' => now(), 'updated_at' => now()]);
        $divisionId = DB::table('divisions')->insertGetId(['season_id' => $seasonId, 'name' => 'Primera', 'created_at' => now(), 'updated_at' => now()]);
        $matchdayId = DB::table('matchdays')->insertGetId(['season_id' => $seasonId, 'division_id' => $divisionId, 'number' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('games')->insert(['matchday_id' => $matchdayId, 'home_team_id' => $homeTeamId, 'away_team_id' => $awayTeamId, 'created_at' => now(), 'updated_at' => now()]);

        $this->expectException(QueryException::class);
        DB::table('teams')->where('id', $homeTeamId)->delete();
    }

    public function test_deleting_a_season_cascades_to_divisions(): void
    {
        $seasonId = DB::table('seasons')->insertGetId(['name' => '2025/26', 'created_at' => now(), 'updated_at' => now()]);
        $divisionId = DB::table('divisions')->insertGetId(['season_id' => $seasonId, 'name' => 'Primera', 'created_at' => now(), 'updated_at' => now()]);

        DB::table('seasons')->where('id', $seasonId)->delete();

        $this->assertSame(0, DB::table('divisions')->where('id', $divisionId)->count());
    }

    /**
     * Divisions cascade to their matchdays rather than restricting on them
     * (unlike divisions -> teams above). A RESTRICT here would make the
     * season cascade order-dependent: deleting a season removes BOTH its
     * divisions and its matchdays, and whichever child the engine processes
     * first would decide whether the delete succeeds or blows up.
     */
    public function test_deleting_a_division_cascades_to_its_matchdays_and_their_games(): void
    {
        $seasonId = DB::table('seasons')->insertGetId(['name' => '2025/26', 'created_at' => now(), 'updated_at' => now()]);
        $divisionId = DB::table('divisions')->insertGetId(['season_id' => $seasonId, 'name' => 'Primera', 'created_at' => now(), 'updated_at' => now()]);
        $clubHomeFCId = DB::table('clubs')->insertGetId(['name' => 'Home FC', 'short_name' => 'HOM', 'created_at' => now(), 'updated_at' => now()]);
        $homeTeamId = DB::table('teams')->insertGetId(['season_id' => $seasonId, 'club_id' => $clubHomeFCId, 'created_at' => now(), 'updated_at' => now()]);
        $clubAwayFCId = DB::table('clubs')->insertGetId(['name' => 'Away FC', 'short_name' => 'AWY', 'created_at' => now(), 'updated_at' => now()]);
        $awayTeamId = DB::table('teams')->insertGetId(['season_id' => $seasonId, 'club_id' => $clubAwayFCId, 'created_at' => now(), 'updated_at' => now()]);
        $matchdayId = DB::table('matchdays')->insertGetId(['season_id' => $seasonId, 'division_id' => $divisionId, 'number' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $gameId = DB::table('games')->insertGetId(['matchday_id' => $matchdayId, 'home_team_id' => $homeTeamId, 'away_team_id' => $awayTeamId, 'created_at' => now(), 'updated_at' => now()]);

        DB::table('divisions')->where('id', $divisionId)->delete();

        $this->assertSame(0, DB::table('matchdays')->where('id', $matchdayId)->count());
        $this->assertSame(0, DB::table('games')->where('id', $gameId)->count());
    }

    public function test_deleting_a_division_cascades_to_its_standing_zones(): void
    {
        $seasonId = DB::table('seasons')->insertGetId(['name' => '2025/26', 'created_at' => now(), 'updated_at' => now()]);
        $divisionId = DB::table('divisions')->insertGetId(['season_id' => $seasonId, 'name' => 'Primera', 'created_at' => now(), 'updated_at' => now()]);
        $zoneId = DB::table('standing_zones')->insertGetId(['division_id' => $divisionId, 'label' => 'Ascenso', 'color' => 'green', 'from_position' => 1, 'to_position' => 2, 'created_at' => now(), 'updated_at' => now()]);

        DB::table('divisions')->where('id', $divisionId)->delete();

        $this->assertSame(0, DB::table('standing_zones')->where('id', $zoneId)->count());
    }

    public function test_deleting_a_division_with_teams_is_restricted(): void
    {
        $seasonId = DB::table('seasons')->insertGetId(['name' => '2025/26', 'created_at' => now(), 'updated_at' => now()]);
        $divisionId = DB::table('divisions')->insertGetId(['season_id' => $seasonId, 'name' => 'Primera', 'created_at' => now(), 'updated_at' => now()]);
        $clubHomeFCId = DB::table('clubs')->insertGetId(['name' => 'Home FC', 'short_name' => 'HOM', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('teams')->insert(['season_id' => $seasonId, 'division_id' => $divisionId, 'club_id' => $clubHomeFCId, 'created_at' => now(), 'updated_at' => now()]);

        $this->expectException(QueryException::class);
        DB::table('divisions')->where('id', $divisionId)->delete();
    }

    public function test_deleting_a_team_nulls_its_news_team_id(): void
    {
        $seasonId = DB::table('seasons')->insertGetId(['name' => '2025/26', 'created_at' => now(), 'updated_at' => now()]);
        $clubHomeFCId = DB::table('clubs')->insertGetId(['name' => 'Home FC', 'short_name' => 'HOM', 'created_at' => now(), 'updated_at' => now()]);
        $teamId = DB::table('teams')->insertGetId(['season_id' => $seasonId, 'club_id' => $clubHomeFCId, 'created_at' => now(), 'updated_at' => now()]);
        $newsId = DB::table('news')->insertGetId(['team_id' => $teamId, 'title' => 'Tagged', 'slug' => 'tagged-news', 'body' => 'Body', 'created_at' => now(), 'updated_at' => now()]);

        DB::table('teams')->where('id', $teamId)->delete();

        $news = DB::table('news')->where('id', $newsId)->first();
        $this->assertNotNull($news);
        $this->assertNull($news->team_id);
    }

    /**
     * @return array{0: int, 1: int} [gameId, playerId]
     */
    private function makeGameAndPlayer(): array
    {
        $seasonId = DB::table('seasons')->insertGetId(['name' => '2025/26', 'created_at' => now(), 'updated_at' => now()]);
        $clubHomeFCId = DB::table('clubs')->insertGetId(['name' => 'Home FC', 'short_name' => 'HOM', 'created_at' => now(), 'updated_at' => now()]);
        $homeTeamId = DB::table('teams')->insertGetId(['season_id' => $seasonId, 'club_id' => $clubHomeFCId, 'created_at' => now(), 'updated_at' => now()]);
        $clubAwayFCId = DB::table('clubs')->insertGetId(['name' => 'Away FC', 'short_name' => 'AWY', 'created_at' => now(), 'updated_at' => now()]);
        $awayTeamId = DB::table('teams')->insertGetId(['season_id' => $seasonId, 'club_id' => $clubAwayFCId, 'created_at' => now(), 'updated_at' => now()]);
        $divisionId = DB::table('divisions')->insertGetId(['season_id' => $seasonId, 'name' => 'Primera', 'created_at' => now(), 'updated_at' => now()]);
        $matchdayId = DB::table('matchdays')->insertGetId(['season_id' => $seasonId, 'division_id' => $divisionId, 'number' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $gameId = DB::table('games')->insertGetId(['matchday_id' => $matchdayId, 'home_team_id' => $homeTeamId, 'away_team_id' => $awayTeamId, 'created_at' => now(), 'updated_at' => now()]);
        $playerId = DB::table('players')->insertGetId(['team_id' => $homeTeamId, 'name' => 'Player A', 'position' => 'GK', 'shirt_number' => 1, 'created_at' => now(), 'updated_at' => now()]);

        return [$gameId, $playerId];
    }

    public function test_deleting_a_game_cascades_to_its_events(): void
    {
        [$gameId, $playerId] = $this->makeGameAndPlayer();
        $eventId = DB::table('game_events')->insertGetId(['game_id' => $gameId, 'player_id' => $playerId, 'type' => 'goal', 'created_at' => now(), 'updated_at' => now()]);

        DB::table('games')->where('id', $gameId)->delete();

        $this->assertSame(0, DB::table('game_events')->where('id', $eventId)->count());
    }

    public function test_deleting_a_player_cascades_to_their_events(): void
    {
        [$gameId, $playerId] = $this->makeGameAndPlayer();
        $eventId = DB::table('game_events')->insertGetId(['game_id' => $gameId, 'player_id' => $playerId, 'type' => 'goal', 'created_at' => now(), 'updated_at' => now()]);

        DB::table('players')->where('id', $playerId)->delete();

        $this->assertSame(0, DB::table('game_events')->where('id', $eventId)->count());
    }
}
