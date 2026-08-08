<?php

namespace Tests\Feature;

use App\Filament\Resources\Games\Pages\CreateGame;
use App\Filament\Resources\Games\Pages\EditGame;
use App\Filament\Resources\Games\Pages\ListGames;
use App\Models\Game;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GameResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    /**
     * @return array{0: Matchday, 1: Team, 2: Team}
     */
    private function makeMatchdayAndTeams(): array
    {
        $season = Season::factory()->create();
        $matchday = Matchday::factory()->create(['season_id' => $season->id]);
        $homeTeam = Team::factory()->create(['season_id' => $season->id]);
        $awayTeam = Team::factory()->create(['season_id' => $season->id]);

        return [$matchday, $homeTeam, $awayTeam];
    }

    public function test_list_page_renders_successfully(): void
    {
        Livewire::test(ListGames::class)->assertOk();
    }

    public function test_create_page_renders_successfully(): void
    {
        Livewire::test(CreateGame::class)->assertOk();
    }

    public function test_edit_page_renders_successfully(): void
    {
        $game = Game::factory()->create();

        Livewire::test(EditGame::class, ['record' => $game->getRouteKey()])->assertOk();
    }

    public function test_can_create_a_game_via_the_form(): void
    {
        [$matchday, $homeTeam, $awayTeam] = $this->makeMatchdayAndTeams();

        Livewire::test(CreateGame::class)
            ->fillForm([
                'matchday_id' => $matchday->id,
                'home_team_id' => $homeTeam->id,
                'away_team_id' => $awayTeam->id,
                'kickoff_at' => '2026-08-15 16:00:00',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('games', [
            'matchday_id' => $matchday->id,
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
        ]);
    }

    public function test_can_edit_a_game_via_the_form(): void
    {
        $game = Game::factory()->create();

        Livewire::test(EditGame::class, ['record' => $game->getRouteKey()])
            ->fillForm([
                'home_score' => 2,
                'away_score' => 1,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('games', [
            'id' => $game->id,
            'home_score' => 2,
            'away_score' => 1,
        ]);
    }

    public function test_equal_home_and_away_team_is_rejected_as_a_form_error(): void
    {
        [$matchday, $homeTeam] = $this->makeMatchdayAndTeams();

        Livewire::test(CreateGame::class)
            ->fillForm([
                'matchday_id' => $matchday->id,
                'home_team_id' => $homeTeam->id,
                'away_team_id' => $homeTeam->id,
            ])
            ->call('create')
            ->assertHasFormErrors(['away_team_id']);

        $this->assertSame(0, Game::count());
    }

    public function test_equal_home_and_away_team_with_mismatched_types_is_rejected_as_a_form_error(): void
    {
        // Pins V7: Laravel's built-in different() rule uses strict === and
        // would let an int-vs-string pair slip through. The custom closure
        // rule (int) casts both sides, so this must be caught too.
        [$matchday, $homeTeam] = $this->makeMatchdayAndTeams();

        Livewire::test(CreateGame::class)
            ->fillForm([
                'matchday_id' => $matchday->id,
                'home_team_id' => $homeTeam->id,
                'away_team_id' => (string) $homeTeam->id,
            ])
            ->call('create')
            ->assertHasFormErrors(['away_team_id']);

        $this->assertSame(0, Game::count());
    }

    public function test_empty_score_inputs_persist_as_null_not_zero(): void
    {
        [$matchday, $homeTeam, $awayTeam] = $this->makeMatchdayAndTeams();

        Livewire::test(CreateGame::class)
            ->fillForm([
                'matchday_id' => $matchday->id,
                'home_team_id' => $homeTeam->id,
                'away_team_id' => $awayTeam->id,
                'home_score' => '',
                'away_score' => '',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $game = Game::where('matchday_id', $matchday->id)->firstOrFail();

        $this->assertNull($game->home_score);
        $this->assertNull($game->away_score);
    }
}
