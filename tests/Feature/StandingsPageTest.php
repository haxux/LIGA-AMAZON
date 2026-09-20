<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Season;
use App\Models\StandingZone;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StandingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_standings_page_renders_a_table_per_populated_division(): void
    {
        $season = Season::factory()->create(['is_current' => true]);
        $primera = Division::factory()->create(['season_id' => $season->id, 'name' => 'Primera']);
        Division::factory()->create(['season_id' => $season->id, 'name' => 'Segunda']);
        Team::factory()->for($season)->create(['division_id' => $primera->id, 'name' => 'Manaos FC']);

        $this->get(route('site.standings'))
            ->assertOk()
            ->assertSee('Primera')
            ->assertSee('Manaos FC');
    }

    public function test_empty_division_renders_no_table(): void
    {
        $season = Season::factory()->create(['is_current' => true]);
        $primera = Division::factory()->create(['season_id' => $season->id, 'name' => 'Primera']);
        Division::factory()->create(['season_id' => $season->id, 'name' => 'Segunda']);
        Team::factory()->for($season)->create(['division_id' => $primera->id]);

        $this->get(route('site.standings'))
            ->assertOk()
            ->assertDontSee('Segunda');
    }

    public function test_page_follows_the_current_season_flag(): void
    {
        $seasonA = Season::factory()->create(['is_current' => true]);
        $divisionA = Division::factory()->create(['season_id' => $seasonA->id, 'name' => 'Primera']);
        Team::factory()->for($seasonA)->create(['division_id' => $divisionA->id, 'name' => 'Season A Team']);

        $seasonB = Season::factory()->create(['is_current' => false]);
        $divisionB = Division::factory()->create(['season_id' => $seasonB->id, 'name' => 'Primera']);
        Team::factory()->for($seasonB)->create(['division_id' => $divisionB->id, 'name' => 'Season B Team']);

        $this->get(route('site.standings'))->assertSee('Season A Team');

        $seasonB->update(['is_current' => true]);

        $this->get(route('site.standings'))
            ->assertSee('Season B Team')
            ->assertDontSee('Season A Team');
    }

    public function test_the_season_filter_renders_and_defaults_to_the_current_season(): void
    {
        $current = Season::factory()->create(['is_current' => true, 'name' => '2026/27']);
        Season::factory()->create(['is_current' => false, 'name' => '2025/26']);
        $division = Division::factory()->create(['season_id' => $current->id, 'name' => 'Primera']);
        Team::factory()->for($current)->create(['division_id' => $division->id, 'name' => 'Manaos FC']);

        $this->get(route('site.standings'))
            ->assertOk()
            ->assertSee('name="temporada"', false)
            ->assertSee('<option value="'.$current->id.'" selected', false)
            ->assertSee('Manaos FC');
    }

    public function test_another_season_can_be_selected_from_the_filter(): void
    {
        $current = Season::factory()->create(['is_current' => true]);
        $currentDivision = Division::factory()->create(['season_id' => $current->id, 'name' => 'Primera']);
        Team::factory()->for($current)->create(['division_id' => $currentDivision->id, 'name' => 'Season A Team']);

        $old = Season::factory()->create(['is_current' => false]);
        $oldDivision = Division::factory()->create(['season_id' => $old->id, 'name' => 'Segunda']);
        Team::factory()->for($old)->create(['division_id' => $oldDivision->id, 'name' => 'Season B Team']);

        $this->get(route('site.standings', ['temporada' => $old->id]))
            ->assertOk()
            ->assertSee('Season B Team')
            ->assertDontSee('Season A Team');
    }

    public function test_an_unknown_season_falls_back_to_the_current_one(): void
    {
        $current = Season::factory()->create(['is_current' => true]);
        $division = Division::factory()->create(['season_id' => $current->id, 'name' => 'Primera']);
        Team::factory()->for($current)->create(['division_id' => $division->id, 'name' => 'Manaos FC']);

        $this->get(route('site.standings', ['temporada' => 9999]))
            ->assertOk()
            ->assertSee('Manaos FC');
    }

    public function test_zone_rows_carry_their_colour_and_the_legend_lists_them(): void
    {
        $season = Season::factory()->create(['is_current' => true]);
        $division = Division::factory()->create(['season_id' => $season->id, 'name' => 'Primera']);
        Team::factory()->for($season)->create(['division_id' => $division->id, 'name' => 'Manaos FC']);
        StandingZone::factory()->create([
            'division_id' => $division->id,
            'label' => 'Ascenso',
            'color' => 'green',
            'from_position' => 1,
            'to_position' => 1,
        ]);

        $this->get(route('site.standings'))
            ->assertOk()
            ->assertSee('background-color: '.StandingZone::COLORS['green']['hex'], false)
            ->assertSee('ASCENSO');
    }

    public function test_a_division_without_zones_renders_no_legend(): void
    {
        $season = Season::factory()->create(['is_current' => true]);
        $division = Division::factory()->create(['season_id' => $season->id, 'name' => 'Primera']);
        Team::factory()->for($season)->create(['division_id' => $division->id, 'name' => 'Manaos FC']);

        $this->get(route('site.standings'))
            ->assertOk()
            ->assertSee('Manaos FC')
            ->assertDontSee('background-color:', false);
    }

    /**
     * A band stops where it is told to: the second row of a one-position
     * zone must come out unpainted.
     */
    public function test_a_row_outside_every_zone_is_left_unpainted(): void
    {
        $season = Season::factory()->create(['is_current' => true]);
        $division = Division::factory()->create(['season_id' => $season->id, 'name' => 'Primera']);
        Team::factory()->for($season)->count(2)->create(['season_id' => $season->id, 'division_id' => $division->id]);
        StandingZone::factory()->create([
            'division_id' => $division->id,
            'label' => 'Ascenso',
            'color' => 'green',
            'from_position' => 1,
            'to_position' => 1,
        ]);

        $html = $this->get(route('site.standings'))->assertOk()->getContent();

        // One row bar plus one legend swatch — never a second row bar.
        $this->assertSame(2, substr_count($html, 'background-color: '.StandingZone::COLORS['green']['hex']));
    }

    public function test_page_returns_404_when_no_seasons_exist(): void
    {
        $this->get(route('site.standings'))->assertNotFound();
    }

    public function test_page_falls_back_to_the_latest_season_when_none_is_current(): void
    {
        Season::factory()->create(['is_current' => false, 'name' => '2024/25']);
        $latest = Season::factory()->create(['is_current' => false, 'name' => '2025/26']);
        $division = Division::factory()->create(['season_id' => $latest->id, 'name' => 'Primera']);
        Team::factory()->for($latest)->create(['division_id' => $division->id, 'name' => 'Latest Season Team']);

        $this->get(route('site.standings'))
            ->assertOk()
            ->assertSee('Latest Season Team');
    }

    public function test_page_falls_back_to_a_single_season_table_when_no_divisions_exist(): void
    {
        $season = Season::factory()->create(['is_current' => true]);
        Team::factory()->for($season)->create(['division_id' => null, 'name' => 'Undivided FC']);

        $this->get(route('site.standings'))
            ->assertOk()
            ->assertSee('Undivided FC');
    }
}
