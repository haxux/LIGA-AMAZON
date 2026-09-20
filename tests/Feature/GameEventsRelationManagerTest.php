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

    public function test_operator_records_a_card_via_the_relation_manager(): void
    {
        $game = $this->makeGame();
        $player = Player::factory()->create(['team_id' => $game->home_team_id]);

        foreach ([GameEvent::TYPE_YELLOW_CARD, GameEvent::TYPE_RED_CARD] as $type) {
            Livewire::test(GameEventsRelationManager::class, [
                'ownerRecord' => $game,
                'pageClass' => EditGame::class,
            ])
                ->mountTableAction('create')
                ->setTableActionData([
                    'type' => $type,
                    'player_id' => $player->id,
                    'minute' => 61,
                ])
                ->callMountedTableAction()
                ->assertHasNoTableActionErrors();

            $this->assertDatabaseHas('game_events', [
                'game_id' => $game->id,
                'player_id' => $player->id,
                'type' => $type,
                'minute' => 61,
            ]);
        }
    }

    public function test_operator_records_a_clean_sheet_for_a_goalkeeper(): void
    {
        $game = $this->makeGame();
        $keeper = Player::factory()->create([
            'team_id' => $game->home_team_id,
            'position' => Player::POSITION_GOALKEEPER,
        ]);

        Livewire::test(GameEventsRelationManager::class, [
            'ownerRecord' => $game,
            'pageClass' => EditGame::class,
        ])
            ->mountTableAction('create')
            ->setTableActionData([
                'type' => GameEvent::TYPE_CLEAN_SHEET,
                'player_id' => $keeper->id,
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('game_events', [
            'game_id' => $game->id,
            'player_id' => $keeper->id,
            'type' => GameEvent::TYPE_CLEAN_SHEET,
            'minute' => null,
        ]);
    }

    /**
     * The Select already hides them, so this covers the path the Select
     * cannot: a payload that names an outfield player anyway.
     */
    public function test_a_clean_sheet_for_an_outfield_player_is_rejected(): void
    {
        $game = $this->makeGame();
        $striker = Player::factory()->create([
            'team_id' => $game->home_team_id,
            'position' => 'Forward',
        ]);

        Livewire::test(GameEventsRelationManager::class, [
            'ownerRecord' => $game,
            'pageClass' => EditGame::class,
        ])
            ->mountTableAction('create')
            ->setTableActionData([
                'type' => GameEvent::TYPE_CLEAN_SHEET,
                'player_id' => $striker->id,
            ])
            ->callMountedTableAction()
            ->assertHasTableActionErrors(['player_id']);

        $this->assertSame(0, GameEvent::where('game_id', $game->id)->count());
    }

    public function test_the_player_list_narrows_to_goalkeepers_for_a_clean_sheet(): void
    {
        $game = $this->makeGame();
        $keeper = Player::factory()->create([
            'team_id' => $game->home_team_id,
            'position' => Player::POSITION_GOALKEEPER,
            'name' => 'Keeper One',
        ]);
        $striker = Player::factory()->create([
            'team_id' => $game->away_team_id,
            'position' => 'Forward',
            'name' => 'Striker One',
        ]);

        // A goal still offers the whole squad...
        Livewire::test(GameEventsRelationManager::class, [
            'ownerRecord' => $game,
            'pageClass' => EditGame::class,
        ])
            ->mountTableAction('create')
            ->setTableActionData([
                'type' => GameEvent::TYPE_GOAL,
                'player_id' => $striker->id,
                'minute' => 12,
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        // ...while the clean sheet only accepts the goalkeeper.
        Livewire::test(GameEventsRelationManager::class, [
            'ownerRecord' => $game,
            'pageClass' => EditGame::class,
        ])
            ->mountTableAction('create')
            ->setTableActionData([
                'type' => GameEvent::TYPE_CLEAN_SHEET,
                'player_id' => $keeper->id,
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertSame(2, GameEvent::where('game_id', $game->id)->count());
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
