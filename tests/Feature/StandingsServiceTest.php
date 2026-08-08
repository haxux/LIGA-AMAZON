<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\Team;
use App\Services\StandingRow;
use App\Services\StandingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StandingsServiceTest extends TestCase
{
    use RefreshDatabase;

    private function playGame(Matchday $matchday, Team $home, Team $away, ?int $homeScore, ?int $awayScore): Game
    {
        return Game::factory()->for($matchday)->create([
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'home_score' => $homeScore,
            'away_score' => $awayScore,
        ]);
    }

    public function test_row_exposes_derived_points_and_goal_difference(): void
    {
        $team = Team::factory()->create();

        $row = new StandingRow(
            team: $team,
            played: 3,
            won: 1,
            drawn: 1,
            lost: 1,
            goals_for: 5,
            goals_against: 3,
        );

        $this->assertInstanceOf(StandingRow::class, $row);
        $this->assertSame(2, $row->goal_difference);
        $this->assertSame(4, $row->points);
    }

    public function test_row_defaults_to_an_all_zero_row_when_no_counters_are_given(): void
    {
        $team = Team::factory()->create();

        $row = new StandingRow(team: $team);

        $this->assertSame(0, $row->played);
        $this->assertSame(0, $row->won);
        $this->assertSame(0, $row->drawn);
        $this->assertSame(0, $row->lost);
        $this->assertSame(0, $row->goals_for);
        $this->assertSame(0, $row->goals_against);
        $this->assertSame(0, $row->goal_difference);
        $this->assertSame(0, $row->points);
    }

    public function test_team_with_no_played_games_appears_as_an_all_zero_row(): void
    {
        $season = Season::factory()->create();
        $team = Team::factory()->for($season)->create();

        $rows = (new StandingsService)->forSeason($season);

        $row = $rows->firstWhere(fn (StandingRow $r) => $r->team->is($team));

        $this->assertNotNull($row);
        $this->assertSame(0, $row->played);
        $this->assertSame(0, $row->won);
        $this->assertSame(0, $row->drawn);
        $this->assertSame(0, $row->lost);
        $this->assertSame(0, $row->goals_for);
        $this->assertSame(0, $row->goals_against);
        $this->assertSame(0, $row->goal_difference);
        $this->assertSame(0, $row->points);
    }

    public function test_team_with_win_draw_loss_has_correct_row(): void
    {
        $season = Season::factory()->create();
        $matchday = Matchday::factory()->for($season)->create();
        $team = Team::factory()->for($season)->create();
        $opponentA = Team::factory()->for($season)->create();
        $opponentB = Team::factory()->for($season)->create();
        $opponentC = Team::factory()->for($season)->create();

        // Win: team scores 2, concedes 1.
        $this->playGame($matchday, $team, $opponentA, 2, 1);
        // Draw: team scores 1, concedes 1.
        $this->playGame($matchday, $team, $opponentB, 1, 1);
        // Loss: team scores 0, concedes 2.
        $this->playGame($matchday, $team, $opponentC, 0, 2);

        $rows = (new StandingsService)->forSeason($season);
        $row = $rows->firstWhere(fn (StandingRow $r) => $r->team->is($team));

        $this->assertNotNull($row);
        $this->assertSame(3, $row->played);
        $this->assertSame(1, $row->won);
        $this->assertSame(1, $row->drawn);
        $this->assertSame(1, $row->lost);
        $this->assertSame(3, $row->goals_for);
        $this->assertSame(4, $row->goals_against);
        $this->assertSame(-1, $row->goal_difference);
        $this->assertSame(4, $row->points);
    }

    public function test_home_and_away_games_fold_into_the_same_team(): void
    {
        $season = Season::factory()->create();
        $matchday = Matchday::factory()->for($season)->create();
        $team = Team::factory()->for($season)->create();
        $opponentA = Team::factory()->for($season)->create();
        $opponentB = Team::factory()->for($season)->create();

        // As home: scores 3, concedes 1 (win).
        $this->playGame($matchday, $team, $opponentA, 3, 1);
        // As away: scores 2, concedes 2 (draw).
        $this->playGame($matchday, $opponentB, $team, 2, 2);

        $rows = (new StandingsService)->forSeason($season);
        $row = $rows->firstWhere(fn (StandingRow $r) => $r->team->is($team));

        $this->assertNotNull($row);
        $this->assertSame(2, $row->played);
        $this->assertSame(1, $row->won);
        $this->assertSame(1, $row->drawn);
        $this->assertSame(0, $row->lost);
        $this->assertSame(5, $row->goals_for);
        $this->assertSame(3, $row->goals_against);
    }

    public function test_game_with_both_scores_null_is_excluded(): void
    {
        $season = Season::factory()->create();
        $matchday = Matchday::factory()->for($season)->create();
        $home = Team::factory()->for($season)->create();
        $away = Team::factory()->for($season)->create();

        $this->playGame($matchday, $home, $away, null, null);

        $rows = (new StandingsService)->forSeason($season);

        $homeRow = $rows->firstWhere(fn (StandingRow $r) => $r->team->is($home));
        $awayRow = $rows->firstWhere(fn (StandingRow $r) => $r->team->is($away));

        $this->assertSame(0, $homeRow->played);
        $this->assertSame(0, $awayRow->played);
    }

    public function test_game_with_only_one_score_set_is_excluded(): void
    {
        $season = Season::factory()->create();
        $matchday = Matchday::factory()->for($season)->create();
        $home = Team::factory()->for($season)->create();
        $away = Team::factory()->for($season)->create();
        $home2 = Team::factory()->for($season)->create();
        $away2 = Team::factory()->for($season)->create();

        // Mirror cases: home score set / away null, and away score set / home null.
        $this->playGame($matchday, $home, $away, 2, null);
        $this->playGame($matchday, $home2, $away2, null, 1);

        $rows = (new StandingsService)->forSeason($season);

        foreach ([$home, $away, $home2, $away2] as $team) {
            $row = $rows->firstWhere(fn (StandingRow $r) => $r->team->is($team));
            $this->assertSame(0, $row->played);
        }
    }

    public function test_games_from_another_season_do_not_affect_this_seasons_table(): void
    {
        $seasonA = Season::factory()->create();
        $matchdayA = Matchday::factory()->for($seasonA)->create();
        $teamA1 = Team::factory()->for($seasonA)->create();
        $teamA2 = Team::factory()->for($seasonA)->create();
        $this->playGame($matchdayA, $teamA1, $teamA2, 1, 0);

        // A matchday that belongs to a DIFFERENT season, but whose game (incorrectly,
        // since nothing in the schema enforces it) references season A's own teams.
        // The D5 isset() guard alone cannot catch this — both teams ARE on season A's
        // roster — only scoping the games query by matchday.season_id (D3) can. This
        // is what makes the test a genuine discriminator for the whereHas() scoping.
        $seasonB = Season::factory()->create();
        $matchdayB = Matchday::factory()->for($seasonB)->create();
        $this->playGame($matchdayB, $teamA1, $teamA2, 5, 0);

        $rows = (new StandingsService)->forSeason($seasonA);

        $this->assertSame(2, $rows->count());

        $rowA1 = $rows->firstWhere(fn (StandingRow $r) => $r->team->is($teamA1));
        $this->assertSame(1, $rowA1->played);
        $this->assertSame(1, $rowA1->goals_for);
    }

    public function test_rows_are_ordered_by_points_then_goal_difference_then_goals_for(): void
    {
        $season = Season::factory()->create();
        $matchday = Matchday::factory()->for($season)->create();

        // Pair A/B: equal points (3 each), differ on goal difference.
        $teamA = Team::factory()->for($season)->create(['name' => 'Team A']);
        $teamB = Team::factory()->for($season)->create(['name' => 'Team B']);
        $fillerAB1 = Team::factory()->for($season)->create();
        $fillerAB2 = Team::factory()->for($season)->create();
        $this->playGame($matchday, $teamA, $fillerAB1, 4, 0); // A: +4 GD, 3 pts
        $this->playGame($matchday, $teamB, $fillerAB2, 1, 0); // B: +1 GD, 3 pts

        // Pair C/D: equal points AND equal goal difference, differ on goals for.
        $teamC = Team::factory()->for($season)->create(['name' => 'Team C']);
        $teamD = Team::factory()->for($season)->create(['name' => 'Team D']);
        $fillerCD1 = Team::factory()->for($season)->create();
        $fillerCD2 = Team::factory()->for($season)->create();
        $this->playGame($matchday, $teamC, $fillerCD1, 3, 1); // C: +2 GD, 3 pts, GF=3
        $this->playGame($matchday, $teamD, $fillerCD2, 2, 0); // D: +2 GD, 3 pts, GF=2

        $rows = (new StandingsService)->forSeason($season);
        $order = $rows
            ->filter(fn (StandingRow $r) => in_array($r->team->id, [$teamA->id, $teamB->id, $teamC->id, $teamD->id], true))
            ->map(fn (StandingRow $r) => $r->team->name)
            ->values()
            ->all();

        $this->assertSame(['Team A', 'Team C', 'Team D', 'Team B'], $order);
    }

    public function test_fully_tied_teams_keep_a_stable_order(): void
    {
        $season = Season::factory()->create();
        $matchday = Matchday::factory()->for($season)->create();

        // Created in this order, so this is exactly the roster's id order too —
        // the tie-break a non-stable sort would be free to violate.
        $teamLowerId = Team::factory()->for($season)->create();
        $teamHigherId = Team::factory()->for($season)->create();
        $filler1 = Team::factory()->for($season)->create();
        $filler2 = Team::factory()->for($season)->create();

        // Identical points, goal difference, and goals for.
        $this->playGame($matchday, $teamHigherId, $filler1, 2, 1);
        $this->playGame($matchday, $teamLowerId, $filler2, 2, 1);

        $rows = (new StandingsService)->forSeason($season);
        $order = $rows
            ->filter(fn (StandingRow $r) => in_array($r->team->id, [$teamLowerId->id, $teamHigherId->id], true))
            ->map(fn (StandingRow $r) => $r->team->id)
            ->values()
            ->all();

        // Roster id order (Team::orderBy('id')) is the tie-break of last resort.
        $this->assertSame([$teamLowerId->id, $teamHigherId->id], $order);

        // Repeated calls return the same order — no hidden randomness.
        $again = (new StandingsService)->forSeason($season)
            ->filter(fn (StandingRow $r) => in_array($r->team->id, [$teamLowerId->id, $teamHigherId->id], true))
            ->map(fn (StandingRow $r) => $r->team->id)
            ->values()
            ->all();

        $this->assertSame($order, $again);
    }

    public function test_team_outside_the_season_roster_is_not_added_to_the_table(): void
    {
        $season = Season::factory()->create();
        $matchday = Matchday::factory()->for($season)->create();
        $rosterTeam = Team::factory()->for($season)->create();

        // A team from an unrelated season, referenced (incorrectly, unenforced by
        // the schema) by a game under this season's own matchday.
        $otherSeason = Season::factory()->create();
        $foreignTeam = Team::factory()->for($otherSeason)->create();

        $this->playGame($matchday, $rosterTeam, $foreignTeam, 3, 1);

        $rows = (new StandingsService)->forSeason($season);

        $this->assertSame(1, $rows->count());

        $rosterRow = $rows->firstWhere(fn (StandingRow $r) => $r->team->is($rosterTeam));
        $this->assertNotNull($rosterRow);
        $this->assertSame(1, $rosterRow->played);
        $this->assertSame(1, $rosterRow->won);
        $this->assertSame(3, $rosterRow->goals_for);
        $this->assertSame(1, $rosterRow->goals_against);
    }

    public function test_demo_seed_produces_a_consistent_table(): void
    {
        $this->seed();

        $season = Season::query()->sole();
        $rows = (new StandingsService)->forSeason($season);

        $playedGames = Game::query()
            ->whereNotNull('home_score')
            ->whereNotNull('away_score')
            ->get(['home_score', 'away_score']);

        $decisiveCount = $playedGames->filter(fn (Game $g) => $g->home_score !== $g->away_score)->count();
        $drawCount = $playedGames->count() - $decisiveCount;

        $this->assertSame(10, $rows->count());
        $this->assertSame($playedGames->count() * 2, $rows->sum('played'));
        $this->assertSame(($decisiveCount * 3) + ($drawCount * 2), $rows->sum('points'));
        $this->assertSame($rows->sum('goals_against'), $rows->sum('goals_for'));
    }
}
