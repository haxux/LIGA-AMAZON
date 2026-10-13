<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GameEvent;
use App\Models\Matchday;
use App\Models\Player;
use App\Models\Season;
use App\Models\Team;
use App\Services\GoalscorersService;
use App\Services\ScorerRow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoalscorersServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeGame(Season $season): Game
    {
        $matchday = Matchday::factory()->for($season)->create();
        $home = Team::factory()->for($season)->create();
        $away = Team::factory()->for($season)->create();

        return Game::factory()->for($matchday)->create([
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
        ]);
    }

    public function test_top_scorers_counts_only_goal_events(): void
    {
        $season = Season::factory()->create();
        $game = $this->makeGame($season);
        $player = Player::factory()->create(['team_id' => $game->home_team_id]);

        GameEvent::factory()->for($game)->for($player)->goal()->create();
        GameEvent::factory()->for($game)->for($player)->goal()->create();
        GameEvent::factory()->for($game)->for($player)->assist()->create();

        $rows = (new GoalscorersService)->topScorers($season);
        $row = $rows->firstWhere(fn (ScorerRow $r) => $r->player->is($player));

        $this->assertNotNull($row);
        $this->assertSame(2, $row->count);
    }

    public function test_top_assisters_counts_only_assist_events(): void
    {
        $season = Season::factory()->create();
        $game = $this->makeGame($season);
        $player = Player::factory()->create(['team_id' => $game->home_team_id]);

        GameEvent::factory()->for($game)->for($player)->assist()->create();
        GameEvent::factory()->for($game)->for($player)->assist()->create();
        GameEvent::factory()->for($game)->for($player)->assist()->create();
        GameEvent::factory()->for($game)->for($player)->goal()->create();

        $rows = (new GoalscorersService)->topAssisters($season);
        $row = $rows->firstWhere(fn (ScorerRow $r) => $r->player->is($player));

        $this->assertNotNull($row);
        $this->assertSame(3, $row->count);
    }

    /**
     * El filtro por equipo es lo que la ficha pública de un club necesita
     * (Fase 11): el mismo cómputo, acotado a su plantilla de esa temporada.
     */
    public function test_a_team_filter_narrows_the_board_to_that_squad(): void
    {
        $season = Season::factory()->create();
        $game = $this->makeGame($season);
        $ours = Player::factory()->create(['team_id' => $game->home_team_id]);
        $theirs = Player::factory()->create(['team_id' => $game->away_team_id]);

        GameEvent::factory()->for($game)->for($ours)->goal()->create();
        GameEvent::factory()->for($game)->for($theirs)->goal()->create();
        GameEvent::factory()->for($game)->for($theirs)->goal()->create();

        $rows = (new GoalscorersService)->topScorers($season, 10, $game->homeTeam);

        $this->assertCount(1, $rows);
        $this->assertTrue($rows->first()->player->is($ours));
    }

    public function test_events_from_another_season_are_excluded(): void
    {
        $season = Season::factory()->create();
        $otherSeason = Season::factory()->create();
        $game = $this->makeGame($season);
        $otherGame = $this->makeGame($otherSeason);
        $player = Player::factory()->create(['team_id' => $game->home_team_id]);
        $otherPlayer = Player::factory()->create(['team_id' => $otherGame->home_team_id]);

        GameEvent::factory()->for($game)->for($player)->goal()->create();
        GameEvent::factory()->for($otherGame)->for($otherPlayer)->goal()->create();

        $rows = (new GoalscorersService)->topScorers($season);
        $playerIds = $rows->map(fn (ScorerRow $r) => $r->player->id)->all();

        $this->assertContains($player->id, $playerIds);
        $this->assertNotContains($otherPlayer->id, $playerIds);
    }

    public function test_players_with_no_events_are_absent(): void
    {
        $season = Season::factory()->create();
        $game = $this->makeGame($season);
        $playerWithGoal = Player::factory()->create(['team_id' => $game->home_team_id]);
        $playerWithoutEvents = Player::factory()->create(['team_id' => $game->away_team_id]);

        GameEvent::factory()->for($game)->for($playerWithGoal)->goal()->create();

        $rows = (new GoalscorersService)->topScorers($season);
        $playerIds = $rows->map(fn (ScorerRow $r) => $r->player->id)->all();

        $this->assertContains($playerWithGoal->id, $playerIds);
        $this->assertNotContains($playerWithoutEvents->id, $playerIds);
    }

    public function test_rows_are_ordered_by_count_descending(): void
    {
        $season = Season::factory()->create();
        $game = $this->makeGame($season);
        $topScorer = Player::factory()->create(['team_id' => $game->home_team_id, 'name' => 'Top Scorer']);
        $lowScorer = Player::factory()->create(['team_id' => $game->away_team_id, 'name' => 'Low Scorer']);

        GameEvent::factory()->for($game)->for($topScorer)->goal()->count(3)->create();
        GameEvent::factory()->for($game)->for($lowScorer)->goal()->count(1)->create();

        $rows = (new GoalscorersService)->topScorers($season)->values();

        $this->assertSame('Top Scorer', $rows[0]->player->name);
        $this->assertSame('Low Scorer', $rows[1]->player->name);
    }

    public function test_result_is_limited_to_ten_by_default(): void
    {
        $season = Season::factory()->create();
        $game = $this->makeGame($season);

        collect(range(1, 12))->each(function (int $i) use ($game) {
            $player = Player::factory()->create(['team_id' => $game->home_team_id, 'shirt_number' => $i]);
            GameEvent::factory()->for($game)->for($player)->goal()->create();
        });

        $rows = (new GoalscorersService)->topScorers($season);

        $this->assertSame(10, $rows->count());
    }

    public function test_null_limit_returns_every_scorer(): void
    {
        $season = Season::factory()->create();
        $game = $this->makeGame($season);

        collect(range(1, 12))->each(function (int $i) use ($game) {
            $player = Player::factory()->create(['team_id' => $game->home_team_id, 'shirt_number' => $i]);
            GameEvent::factory()->for($game)->for($player)->goal()->create();
        });

        $rows = (new GoalscorersService)->topScorers($season, null);

        $this->assertSame(12, $rows->count());
    }

    public function test_tied_counts_keep_a_stable_order(): void
    {
        $season = Season::factory()->create();
        $game = $this->makeGame($season);

        $playerLowerId = Player::factory()->create(['team_id' => $game->home_team_id]);
        $playerHigherId = Player::factory()->create(['team_id' => $game->away_team_id]);

        GameEvent::factory()->for($game)->for($playerHigherId)->goal()->create();
        GameEvent::factory()->for($game)->for($playerLowerId)->goal()->create();

        $rows = (new GoalscorersService)->topScorers($season)->values();
        $order = $rows->map(fn (ScorerRow $r) => $r->player->id)->all();

        $this->assertSame([$playerLowerId->id, $playerHigherId->id], $order);

        $again = (new GoalscorersService)->topScorers($season)->values()
            ->map(fn (ScorerRow $r) => $r->player->id)->all();

        $this->assertSame($order, $again);
    }

    /**
     * `leaderboards()` existe sólo para ahorrarle consultas a la portada, así
     * que su única obligación es dar EXACTAMENTE lo mismo que las tres llamadas
     * a `leaders()` que sustituye. Si algún día se separan, lo que se enseña en
     * la portada deja de coincidir con lo que se enseña en Estadísticas, y
     * nadie sabría cuál de las dos miente.
     */
    public function test_the_combined_boards_agree_with_the_one_by_one_ones(): void
    {
        $season = Season::factory()->create();
        $service = app(GoalscorersService::class);

        $tipos = [GameEvent::TYPE_GOAL, GameEvent::TYPE_ASSIST, GameEvent::TYPE_CLEAN_SHEET];

        // Un reparto con empates a propósito, que es donde las dos podrían
        // discrepar: dos a tres goles, dos a una asistencia, uno solo arriba en
        // porterías a cero.
        $reparto = [
            ['Tres Goles A', GameEvent::TYPE_GOAL, 3],
            ['Tres Goles B', GameEvent::TYPE_GOAL, 3],
            ['Un Gol', GameEvent::TYPE_GOAL, 1],
            ['Una Asistencia A', GameEvent::TYPE_ASSIST, 1],
            ['Una Asistencia B', GameEvent::TYPE_ASSIST, 1],
            ['Dos Porterias', GameEvent::TYPE_CLEAN_SHEET, 2],
            ['Una Porteria', GameEvent::TYPE_CLEAN_SHEET, 1],
        ];

        foreach ($reparto as [$nombre, $tipo, $cuantos]) {
            $game = $this->makeGame($season);
            // Una portería a cero sólo la admite el modelo para un portero.
            $player = Player::factory()->create([
                'name' => $nombre,
                'team_id' => $game->home_team_id,
                'position' => $tipo === GameEvent::TYPE_CLEAN_SHEET ? Player::POSITION_GOALKEEPER : 'Forward',
            ]);

            foreach (range(1, $cuantos) as $ignored) {
                GameEvent::factory()->for($game)->for($player)->create(['type' => $tipo]);
            }
        }

        $combinadas = $service->leaderboards($season, $tipos, null, 5);

        foreach ($tipos as $tipo) {
            $unaAUna = $service->leaders($season, $tipo, null, 5);

            $this->assertSame(
                $unaAUna->map(fn (ScorerRow $r) => [$r->player->name, $r->count])->all(),
                $combinadas[$tipo]->map(fn (ScorerRow $r) => [$r->player->name, $r->count])->all(),
                "Las dos salidas discrepan en {$tipo}",
            );
        }

        // Y que de verdad había empates que comparar, no listas de uno.
        $this->assertCount(2, $combinadas[GameEvent::TYPE_GOAL]);
        $this->assertCount(2, $combinadas[GameEvent::TYPE_ASSIST]);
        $this->assertCount(1, $combinadas[GameEvent::TYPE_CLEAN_SHEET]);
    }

    public function test_the_combined_boards_are_empty_when_nothing_happened(): void
    {
        $season = Season::factory()->create();

        $boards = app(GoalscorersService::class)->leaderboards(
            $season,
            [GameEvent::TYPE_GOAL, GameEvent::TYPE_ASSIST],
        );

        $this->assertTrue($boards[GameEvent::TYPE_GOAL]->isEmpty());
        $this->assertTrue($boards[GameEvent::TYPE_ASSIST]->isEmpty());
    }
}
