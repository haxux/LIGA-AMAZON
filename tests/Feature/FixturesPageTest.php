<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Game;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FixturesPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * One game inside a matchday, with both teams built in that matchday's
     * own division — the shape the panel now enforces.
     */
    private function gameIn(Matchday $matchday, string $home, string $away, ?int $homeScore = null, ?int $awayScore = null): Game
    {
        $teams = collect([$home, $away])->map(fn (string $name) => Team::factory()->create([
            'season_id' => $matchday->season_id,
            'division_id' => $matchday->division_id,
            'name' => $name,
        ]));

        return Game::factory()->for($matchday)->create([
            'home_team_id' => $teams[0]->id,
            'away_team_id' => $teams[1]->id,
            'home_score' => $homeScore,
            'away_score' => $awayScore,
        ]);
    }

    public function test_played_game_shows_its_score(): void
    {
        $season = Season::factory()->create(['is_current' => true]);
        $matchday = Matchday::factory()->for($season)->create(['number' => 1]);
        $this->gameIn($matchday, 'Manaos FC', 'Tapajós SC', 3, 1);

        $this->get(route('site.fixtures'))
            ->assertOk()
            ->assertSee('Manaos FC')
            ->assertSee('Tapajós SC')
            ->assertSee('3')
            ->assertSee('1');
    }

    public function test_unplayed_game_shows_no_score(): void
    {
        $season = Season::factory()->create(['is_current' => true]);
        $matchday = Matchday::factory()->for($season)->create(['number' => 1]);
        $this->gameIn($matchday, 'Amazonas Royals', 'Belém Athletic');

        $this->get(route('site.fixtures'))
            ->assertOk()
            ->assertSee('Amazonas Royals')
            ->assertSee('Belém Athletic');
    }

    public function test_page_groups_games_by_matchday(): void
    {
        $season = Season::factory()->create(['is_current' => true]);
        $division = Division::factory()->create(['season_id' => $season->id]);
        $matchday1 = Matchday::factory()->for($season)->for($division)->create(['number' => 1]);
        $matchday2 = Matchday::factory()->for($season)->for($division)->create(['number' => 2]);
        $this->gameIn($matchday1, 'Manaos FC', 'Tapajós SC');
        $this->gameIn($matchday2, 'Xingu Rangers', 'Solimões FC');

        $this->get(route('site.fixtures', ['jornada' => 'todas']))
            ->assertOk()
            ->assertSeeInOrder(['Jornada 1', 'Jornada 2']);
    }

    public function test_defaults_to_the_current_season(): void
    {
        $current = Season::factory()->create(['is_current' => true]);
        $old = Season::factory()->create(['is_current' => false]);
        $this->gameIn(Matchday::factory()->for($current)->create(), 'Manaos FC', 'Tapajós SC');
        $this->gameIn(Matchday::factory()->for($old)->create(), 'Xingu Rangers', 'Solimões FC');

        $this->get(route('site.fixtures'))
            ->assertOk()
            ->assertSee('Manaos FC')
            ->assertDontSee('Xingu Rangers');
    }

    public function test_another_season_can_be_selected_from_the_filter(): void
    {
        $current = Season::factory()->create(['is_current' => true, 'name' => '2026/27']);
        $old = Season::factory()->create(['is_current' => false, 'name' => '2025/26']);
        $this->gameIn(Matchday::factory()->for($current)->create(), 'Manaos FC', 'Tapajós SC');
        $this->gameIn(Matchday::factory()->for($old)->create(), 'Xingu Rangers', 'Solimões FC');

        $this->get(route('site.fixtures', ['temporada' => $old->id]))
            ->assertOk()
            ->assertSee('Xingu Rangers')
            ->assertDontSee('Manaos FC');
    }

    /**
     * "The matchday going on right now": the earliest one still to be played,
     * counting today itself.
     */
    public function test_defaults_to_the_next_matchday_still_to_be_played(): void
    {
        $season = Season::factory()->create(['is_current' => true]);
        $division = Division::factory()->create(['season_id' => $season->id]);
        $past = Matchday::factory()->for($season)->for($division)->create(['number' => 1, 'date' => today()->subWeek()]);
        $today = Matchday::factory()->for($season)->for($division)->create(['number' => 2, 'date' => today()]);
        $future = Matchday::factory()->for($season)->for($division)->create(['number' => 3, 'date' => today()->addWeek()]);
        $this->gameIn($past, 'Manaos FC', 'Tapajós SC');
        $this->gameIn($today, 'Xingu Rangers', 'Solimões FC');
        $this->gameIn($future, 'Iquitos United', 'Marañón AC');

        $this->get(route('site.fixtures'))
            ->assertOk()
            ->assertSee('Xingu Rangers')
            ->assertDontSee('Manaos FC')
            ->assertDontSee('Iquitos United');
    }

    public function test_falls_back_to_the_last_matchday_once_the_calendar_is_over(): void
    {
        $season = Season::factory()->create(['is_current' => true]);
        $division = Division::factory()->create(['season_id' => $season->id]);
        $first = Matchday::factory()->for($season)->for($division)->create(['number' => 1, 'date' => today()->subMonths(2)]);
        $last = Matchday::factory()->for($season)->for($division)->create(['number' => 2, 'date' => today()->subWeek()]);
        $this->gameIn($first, 'Manaos FC', 'Tapajós SC');
        $this->gameIn($last, 'Xingu Rangers', 'Solimões FC');

        $this->get(route('site.fixtures'))
            ->assertOk()
            ->assertSee('Xingu Rangers')
            ->assertDontSee('Manaos FC');
    }

    public function test_a_matchday_number_can_be_picked_from_the_filter(): void
    {
        $season = Season::factory()->create(['is_current' => true]);
        $division = Division::factory()->create(['season_id' => $season->id]);
        $first = Matchday::factory()->for($season)->for($division)->create(['number' => 1, 'date' => today()->subWeek()]);
        $second = Matchday::factory()->for($season)->for($division)->create(['number' => 2, 'date' => today()]);
        $this->gameIn($first, 'Manaos FC', 'Tapajós SC');
        $this->gameIn($second, 'Xingu Rangers', 'Solimões FC');

        $this->get(route('site.fixtures', ['jornada' => 1]))
            ->assertOk()
            ->assertSee('Manaos FC')
            ->assertDontSee('Xingu Rangers');
    }

    public function test_divisions_are_shown_as_separate_sections(): void
    {
        $season = Season::factory()->create(['is_current' => true]);
        $primera = Division::factory()->create(['season_id' => $season->id, 'name' => 'Primera']);
        $segunda = Division::factory()->create(['season_id' => $season->id, 'name' => 'Segunda']);
        $this->gameIn(Matchday::factory()->for($season)->for($primera)->create(['number' => 1, 'date' => today()]), 'Manaos FC', 'Tapajós SC');
        $this->gameIn(Matchday::factory()->for($season)->for($segunda)->create(['number' => 1, 'date' => today()]), 'Xingu Rangers', 'Solimões FC');

        $this->get(route('site.fixtures'))
            ->assertOk()
            ->assertSee('Primera')
            ->assertSee('Segunda')
            ->assertSee('Manaos FC')
            ->assertSee('Xingu Rangers');
    }

    public function test_a_single_division_can_be_picked_from_the_filter(): void
    {
        $season = Season::factory()->create(['is_current' => true]);
        $primera = Division::factory()->create(['season_id' => $season->id, 'name' => 'Primera']);
        $segunda = Division::factory()->create(['season_id' => $season->id, 'name' => 'Segunda']);
        $this->gameIn(Matchday::factory()->for($season)->for($primera)->create(['number' => 1, 'date' => today()]), 'Manaos FC', 'Tapajós SC');
        $this->gameIn(Matchday::factory()->for($season)->for($segunda)->create(['number' => 1, 'date' => today()]), 'Xingu Rangers', 'Solimões FC');

        $this->get(route('site.fixtures', ['division' => $primera->id]))
            ->assertOk()
            ->assertSee('Manaos FC')
            ->assertDontSee('Xingu Rangers');
    }

    /**
     * Each division runs its own calendar, so picking a jornada number while
     * showing every division means "jornada N of each of them".
     */
    public function test_a_matchday_number_selects_that_round_in_every_division(): void
    {
        $season = Season::factory()->create(['is_current' => true]);
        $primera = Division::factory()->create(['season_id' => $season->id, 'name' => 'Primera']);
        $segunda = Division::factory()->create(['season_id' => $season->id, 'name' => 'Segunda']);
        $this->gameIn(Matchday::factory()->for($season)->for($primera)->create(['number' => 2, 'date' => today()]), 'Manaos FC', 'Tapajós SC');
        $this->gameIn(Matchday::factory()->for($season)->for($segunda)->create(['number' => 2, 'date' => today()->addWeek()]), 'Xingu Rangers', 'Solimões FC');
        $this->gameIn(Matchday::factory()->for($season)->for($segunda)->create(['number' => 1, 'date' => today()->subWeek()]), 'Iquitos United', 'Marañón AC');

        $this->get(route('site.fixtures', ['jornada' => 2]))
            ->assertOk()
            ->assertSee('Manaos FC')
            ->assertSee('Xingu Rangers')
            ->assertDontSee('Iquitos United');
    }

    public function test_unknown_filter_values_fall_back_to_the_defaults(): void
    {
        $season = Season::factory()->create(['is_current' => true]);
        $matchday = Matchday::factory()->for($season)->create(['number' => 1, 'date' => today()]);
        $this->gameIn($matchday, 'Manaos FC', 'Tapajós SC');

        $this->get(route('site.fixtures', ['temporada' => 9999, 'division' => 9999, 'jornada' => 9999]))
            ->assertOk()
            ->assertSee('Manaos FC');
    }

    public function test_a_season_without_games_renders_an_empty_state(): void
    {
        Season::factory()->create(['is_current' => true]);

        $this->get(route('site.fixtures'))
            ->assertOk()
            ->assertSee('No hay partidos');
    }
}
