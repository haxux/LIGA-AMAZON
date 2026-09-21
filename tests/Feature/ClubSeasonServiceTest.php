<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\Matchday;
use App\Models\Player;
use App\Models\Season;
use App\Models\SquadMembership;
use App\Models\Team;
use App\Services\ClubSeasonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Los números de la ficha de un club (Fase 11). Se derivan de `games` y
 * `game_events`, sin tabla de estadísticas (design D12).
 */
class ClubSeasonServiceTest extends TestCase
{
    use RefreshDatabase;

    private Season $season;

    private Division $division;

    private Team $team;

    private ClubSeasonService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->season = Season::factory()->create(['is_current' => true, 'name' => '2026/27']);
        $this->division = Division::factory()->create(['season_id' => $this->season->id]);
        $this->team = $this->teamNamed('Manaos FC');
        $this->service = app(ClubSeasonService::class);
    }

    private function teamNamed(string $name): Team
    {
        return Team::factory()->create([
            'season_id' => $this->season->id,
            'division_id' => $this->division->id,
            'name' => $name,
        ]);
    }

    private function game(Team $rival, ?int $for, ?int $against, int $number, bool $atHome = true): Game
    {
        $matchday = Matchday::factory()->create([
            'season_id' => $this->season->id,
            'division_id' => $this->division->id,
            'number' => $number,
        ]);

        return Game::factory()->for($matchday)->create([
            'home_team_id' => $atHome ? $this->team->id : $rival->id,
            'away_team_id' => $atHome ? $rival->id : $this->team->id,
            'home_score' => $atHome ? $for : $against,
            'away_score' => $atHome ? $against : $for,
        ]);
    }

    /**
     * La letra es la del club de la ficha, gane en casa o fuera. Es justo lo
     * que la vista no debería tener que decidir.
     */
    public function test_recent_results_read_from_the_clubs_side(): void
    {
        $rival = $this->teamNamed('Tapajós SC');
        $this->game($rival, for: 2, against: 0, number: 1);
        $this->game($rival, for: 1, against: 1, number: 2, atHome: false);
        $this->game($rival, for: 0, against: 3, number: 3, atHome: false);

        $results = $this->service->recentResults($this->team);

        $this->assertSame(['P', 'E', 'G'], $results->pluck('outcome')->all());
        $this->assertSame(0, $results->first()['scored']);
        $this->assertSame(3, $results->first()['conceded']);
        $this->assertSame('Tapajós SC', $results->first()['opponent']->name);
    }

    public function test_recent_results_stop_at_five(): void
    {
        $rival = $this->teamNamed('Tapajós SC');

        foreach (range(1, 7) as $number) {
            $this->game($rival, for: 1, against: 0, number: $number);
        }

        $this->assertCount(5, $this->service->recentResults($this->team));
    }

    public function test_the_next_game_is_the_first_one_without_a_score(): void
    {
        $rival = $this->teamNamed('Tapajós SC');
        $this->game($rival, for: 1, against: 0, number: 1);
        $pending = $this->game($rival, for: null, against: null, number: 2);
        $this->game($rival, for: null, against: null, number: 3);

        $this->assertSame($pending->id, $this->service->nextGame($this->team)?->id);
    }

    public function test_there_is_no_next_game_once_everything_is_played(): void
    {
        $this->game($this->teamNamed('Tapajós SC'), for: 1, against: 0, number: 1);

        $this->assertNull($this->service->nextGame($this->team));
    }

    public function test_the_position_is_the_one_in_its_division_table(): void
    {
        $rival = $this->teamNamed('Tapajós SC');
        $this->game($rival, for: 0, against: 2, number: 1);

        $this->assertSame(2, $this->service->position($this->team));
        $this->assertSame(1, $this->service->position($rival->fresh()));
    }

    public function test_stats_count_goals_cards_and_clean_sheets(): void
    {
        $rival = $this->teamNamed('Tapajós SC');
        $won = $this->game($rival, for: 3, against: 1, number: 1);
        $this->game($rival, for: 0, against: 0, number: 2);
        $this->game($rival, for: null, against: null, number: 3);

        $keeper = $this->squadPlayer($this->team, Player::POSITION_GOALKEEPER);
        $defender = $this->squadPlayer($this->team, 'Defender');
        GameEvent::factory()->create(['game_id' => $won->id, 'player_id' => $defender->id, 'type' => GameEvent::TYPE_YELLOW_CARD]);
        GameEvent::factory()->create(['game_id' => $won->id, 'player_id' => $defender->id, 'type' => GameEvent::TYPE_RED_CARD]);
        GameEvent::factory()->create(['game_id' => $won->id, 'player_id' => $keeper->id, 'type' => GameEvent::TYPE_CLEAN_SHEET, 'minute' => null]);

        // La tarjeta de un rival no es de este club: el puente es la plantilla.
        $theirs = $this->squadPlayer($rival, 'Defender');
        GameEvent::factory()->create(['game_id' => $won->id, 'player_id' => $theirs->id, 'type' => GameEvent::TYPE_YELLOW_CARD]);

        $stats = $this->service->stats($this->team);

        $this->assertSame(2, $stats->played);
        $this->assertSame(1, $stats->won);
        $this->assertSame(1, $stats->drawn);
        $this->assertSame(0, $stats->lost);
        $this->assertSame(3, $stats->goals_for);
        $this->assertSame(1, $stats->goals_against);
        $this->assertSame(2, $stats->goal_difference);
        $this->assertSame(4, $stats->points);
        $this->assertSame(1, $stats->yellow_cards);
        $this->assertSame(1, $stats->red_cards);
        $this->assertSame(1, $stats->clean_sheets);
    }

    private function squadPlayer(Team $team, string $position): Player
    {
        $player = Player::factory()->create(['club_id' => $team->club_id, 'position' => $position]);

        SquadMembership::factory()->create(['team_id' => $team->id, 'player_id' => $player->id]);

        return $player;
    }
}
