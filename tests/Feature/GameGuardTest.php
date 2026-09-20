<?php

namespace Tests\Feature;

use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class GameGuardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: int, 1: int, 2: int} [matchdayId, teamId, otherTeamId]
     */
    private function makeMatchdayAndTeams(): array
    {
        $seasonId = DB::table('seasons')->insertGetId(['name' => '2025/26', 'created_at' => now(), 'updated_at' => now()]);
        $clubHomeFCId = DB::table('clubs')->insertGetId(['name' => 'Home FC', 'short_name' => 'HOM', 'created_at' => now(), 'updated_at' => now()]);
        $teamId = DB::table('teams')->insertGetId(['season_id' => $seasonId, 'club_id' => $clubHomeFCId, 'created_at' => now(), 'updated_at' => now()]);
        $clubAwayFCId = DB::table('clubs')->insertGetId(['name' => 'Away FC', 'short_name' => 'AWY', 'created_at' => now(), 'updated_at' => now()]);
        $otherTeamId = DB::table('teams')->insertGetId(['season_id' => $seasonId, 'club_id' => $clubAwayFCId, 'created_at' => now(), 'updated_at' => now()]);
        $divisionId = DB::table('divisions')->insertGetId(['season_id' => $seasonId, 'name' => 'Primera', 'created_at' => now(), 'updated_at' => now()]);
        $matchdayId = DB::table('matchdays')->insertGetId(['season_id' => $seasonId, 'division_id' => $divisionId, 'number' => 1, 'created_at' => now(), 'updated_at' => now()]);

        return [$matchdayId, $teamId, $otherTeamId];
    }

    public function test_saving_a_game_with_equal_home_and_away_team_ids_is_rejected(): void
    {
        [$matchdayId, $teamId] = $this->makeMatchdayAndTeams();

        $this->expectException(ValidationException::class);

        Game::create([
            'matchday_id' => $matchdayId,
            'home_team_id' => $teamId,
            'away_team_id' => $teamId,
        ]);
    }

    public function test_saving_a_game_with_equal_home_and_away_team_ids_as_strings_is_rejected(): void
    {
        [$matchdayId, $teamId] = $this->makeMatchdayAndTeams();

        $this->expectException(ValidationException::class);

        Game::create([
            'matchday_id' => (string) $matchdayId,
            'home_team_id' => (string) $teamId,
            'away_team_id' => (string) $teamId,
        ]);
    }

    public function test_saving_a_game_with_mixed_type_equal_ids_is_rejected(): void
    {
        // home_team_id arrives as int, away_team_id as string (e.g. raw HTML form input) —
        // proves the guard's (int) cast comparison, not just same-type equality.
        [$matchdayId, $teamId] = $this->makeMatchdayAndTeams();

        $this->expectException(ValidationException::class);

        Game::create([
            'matchday_id' => $matchdayId,
            'home_team_id' => $teamId,
            'away_team_id' => (string) $teamId,
        ]);
    }

    public function test_guard_is_muted_inside_without_events(): void
    {
        [$matchdayId, $teamId] = $this->makeMatchdayAndTeams();

        $game = Game::withoutEvents(function () use ($matchdayId, $teamId) {
            return Game::create([
                'matchday_id' => $matchdayId,
                'home_team_id' => $teamId,
                'away_team_id' => $teamId,
            ]);
        });

        $this->assertSame($teamId, $game->home_team_id);
        $this->assertSame($teamId, $game->away_team_id);
    }

    public function test_saving_a_game_with_distinct_teams_succeeds(): void
    {
        [$matchdayId, $homeTeamId, $awayTeamId] = $this->makeMatchdayAndTeams();

        $game = Game::create([
            'matchday_id' => $matchdayId,
            'home_team_id' => $homeTeamId,
            'away_team_id' => $awayTeamId,
        ]);

        $this->assertNotSame($game->home_team_id, $game->away_team_id);
        $this->assertTrue($game->exists);
    }
}
