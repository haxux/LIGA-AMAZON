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
        $homeTeamId = DB::table('teams')->insertGetId(['season_id' => $seasonId, 'name' => 'Home FC', 'short_name' => 'HOM', 'created_at' => now(), 'updated_at' => now()]);
        $awayTeamId = DB::table('teams')->insertGetId(['season_id' => $seasonId, 'name' => 'Away FC', 'short_name' => 'AWY', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('stadiums')->insert(['team_id' => $homeTeamId, 'name' => 'Arena', 'city' => 'City', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('players')->insert(['team_id' => $homeTeamId, 'name' => 'Player A', 'position' => 'GK', 'shirt_number' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $matchdayId = DB::table('matchdays')->insertGetId(['season_id' => $seasonId, 'number' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('games')->insert(['matchday_id' => $matchdayId, 'home_team_id' => $homeTeamId, 'away_team_id' => $awayTeamId, 'created_at' => now(), 'updated_at' => now()]);

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
        $homeTeamId = DB::table('teams')->insertGetId(['season_id' => $seasonId, 'name' => 'Home FC', 'short_name' => 'HOM', 'created_at' => now(), 'updated_at' => now()]);
        $awayTeamId = DB::table('teams')->insertGetId(['season_id' => $seasonId, 'name' => 'Away FC', 'short_name' => 'AWY', 'created_at' => now(), 'updated_at' => now()]);
        $matchdayId = DB::table('matchdays')->insertGetId(['season_id' => $seasonId, 'number' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('games')->insert(['matchday_id' => $matchdayId, 'home_team_id' => $homeTeamId, 'away_team_id' => $awayTeamId, 'created_at' => now(), 'updated_at' => now()]);

        $this->expectException(QueryException::class);
        DB::table('teams')->where('id', $homeTeamId)->delete();
    }
}
