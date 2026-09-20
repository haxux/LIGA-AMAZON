<?php

namespace Tests\Feature;

use App\Filament\Resources\Matchdays\Pages\EditMatchday;
use App\Filament\Resources\Matchdays\RelationManagers\GamesRelationManager;
use App\Models\Division;
use App\Models\Game;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GamesRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_lists_only_the_owning_matchdays_games(): void
    {
        $season = Season::factory()->create();
        $matchdayA = Matchday::factory()->create(['season_id' => $season->id]);
        $matchdayB = Matchday::factory()->create(['season_id' => $season->id]);
        $gameA = Game::factory()->create(['matchday_id' => $matchdayA->id]);
        $gameB = Game::factory()->create(['matchday_id' => $matchdayB->id]);

        Livewire::test(GamesRelationManager::class, [
            'ownerRecord' => $matchdayA,
            'pageClass' => EditMatchday::class,
        ])
            ->assertCanSeeTableRecords([$gameA])
            ->assertCanNotSeeTableRecords([$gameB]);
    }

    public function test_editing_a_games_score_from_the_relation_manager_persists_it(): void
    {
        $game = Game::factory()->create();

        Livewire::test(GamesRelationManager::class, [
            'ownerRecord' => $game->matchday,
            'pageClass' => EditMatchday::class,
        ])
            ->mountTableAction('edit', $game)
            ->setTableActionData([
                'home_team_id' => $game->home_team_id,
                'away_team_id' => $game->away_team_id,
                'home_score' => 3,
                'away_score' => 0,
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('games', [
            'id' => $game->id,
            'home_score' => 3,
            'away_score' => 0,
        ]);
    }

    /**
     * The modal has no matchday field — the owning record IS the matchday —
     * so the team Selects have to read the division off the owner record.
     */
    public function test_a_team_from_another_division_is_rejected_inside_the_relation_manager_modal(): void
    {
        $season = Season::factory()->create();
        $matchday = Matchday::factory()->create(['season_id' => $season->id]);
        $ownTeam = Team::factory()->create(['season_id' => $season->id, 'division_id' => $matchday->division_id]);
        $otherDivision = Division::factory()->create(['season_id' => $season->id]);
        $foreignTeam = Team::factory()->create(['season_id' => $season->id, 'division_id' => $otherDivision->id]);

        $secondOwnTeam = Team::factory()->create(['season_id' => $season->id, 'division_id' => $matchday->division_id]);

        Livewire::test(GamesRelationManager::class, [
            'ownerRecord' => $matchday,
            'pageClass' => EditMatchday::class,
        ])
            ->mountTableAction('create')
            ->setTableActionData([
                'home_team_id' => $ownTeam->id,
                'away_team_id' => $foreignTeam->id,
            ])
            ->callMountedTableAction()
            ->assertHasTableActionErrors(['away_team_id']);

        $this->assertSame(0, Game::where('matchday_id', $matchday->id)->count());

        Livewire::test(GamesRelationManager::class, [
            'ownerRecord' => $matchday,
            'pageClass' => EditMatchday::class,
        ])
            ->mountTableAction('create')
            ->setTableActionData([
                'home_team_id' => $ownTeam->id,
                'away_team_id' => $secondOwnTeam->id,
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('games', [
            'matchday_id' => $matchday->id,
            'home_team_id' => $ownTeam->id,
            'away_team_id' => $secondOwnTeam->id,
        ]);
    }

    public function test_equal_home_and_away_team_guard_still_fires_inside_the_relation_manager_modal(): void
    {
        $season = Season::factory()->create();
        $matchday = Matchday::factory()->create(['season_id' => $season->id]);
        $team = Team::factory()->create(['season_id' => $season->id]);
        $otherTeam = Team::factory()->create(['season_id' => $season->id]);
        $game = Game::factory()->create([
            'matchday_id' => $matchday->id,
            'home_team_id' => $team->id,
            'away_team_id' => $otherTeam->id,
        ]);

        Livewire::test(GamesRelationManager::class, [
            'ownerRecord' => $matchday,
            'pageClass' => EditMatchday::class,
        ])
            ->mountTableAction('edit', $game)
            ->setTableActionData([
                'home_team_id' => $team->id,
                'away_team_id' => $team->id,
            ])
            ->callMountedTableAction()
            ->assertHasTableActionErrors(['away_team_id']);

        $this->assertSame($otherTeam->id, $game->fresh()->away_team_id);
    }
}
