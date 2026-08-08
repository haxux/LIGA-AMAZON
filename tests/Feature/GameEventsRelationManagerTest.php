<?php

namespace Tests\Feature;

use App\Filament\Resources\Games\Pages\EditGame;
use App\Filament\Resources\Games\RelationManagers\GameEventsRelationManager;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\Matchday;
use App\Models\Player;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GameEventsRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    private function makeGame(): Game
    {
        $season = Season::factory()->create();
        $matchday = Matchday::factory()->for($season)->create();
        $homeTeam = Team::factory()->for($season)->create();
        $awayTeam = Team::factory()->for($season)->create();

        return Game::create([
            'matchday_id' => $matchday->id,
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
        ]);
    }

    public function test_relation_manager_renders(): void
    {
        $game = $this->makeGame();

        Livewire::test(GameEventsRelationManager::class, [
            'ownerRecord' => $game,
            'pageClass' => EditGame::class,
        ])->assertOk();
    }

    public function test_operator_records_a_goal_via_the_relation_manager(): void
    {
        $game = $this->makeGame();
        $player = Player::factory()->create(['team_id' => $game->home_team_id]);

        Livewire::test(GameEventsRelationManager::class, [
            'ownerRecord' => $game,
            'pageClass' => EditGame::class,
        ])
            ->mountTableAction('create')
            ->setTableActionData([
                'player_id' => $player->id,
                'type' => GameEvent::TYPE_GOAL,
                'minute' => 23,
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('game_events', [
            'game_id' => $game->id,
            'player_id' => $player->id,
            'type' => GameEvent::TYPE_GOAL,
            'minute' => 23,
        ]);
    }

    public function test_player_select_only_offers_players_from_the_two_teams_in_the_game(): void
    {
        $game = $this->makeGame();
        $homePlayer = Player::factory()->create(['team_id' => $game->home_team_id]);
        $otherTeam = Team::factory()->create();
        $otherPlayer = Player::factory()->create(['team_id' => $otherTeam->id]);

        Livewire::test(GameEventsRelationManager::class, [
            'ownerRecord' => $game,
            'pageClass' => EditGame::class,
        ])
            ->mountTableAction('create')
            ->setTableActionData([
                'player_id' => $otherPlayer->id,
                'type' => GameEvent::TYPE_GOAL,
                'minute' => 10,
            ])
            ->callMountedTableAction()
            ->assertHasTableActionErrors(['player_id']);

        $this->assertDatabaseMissing('game_events', [
            'player_id' => $otherPlayer->id,
        ]);

        Livewire::test(GameEventsRelationManager::class, [
            'ownerRecord' => $game,
            'pageClass' => EditGame::class,
        ])
            ->mountTableAction('create')
            ->setTableActionData([
                'player_id' => $homePlayer->id,
                'type' => GameEvent::TYPE_GOAL,
                'minute' => 10,
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('game_events', [
            'player_id' => $homePlayer->id,
        ]);
    }
}
