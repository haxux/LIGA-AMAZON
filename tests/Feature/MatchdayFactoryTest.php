<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Matchday;
use App\Models\Season;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchdayFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_matchdays_created_one_at_a_time_for_one_season_get_unique_numbers(): void
    {
        $season = Season::factory()->create();

        for ($i = 0; $i < 20; $i++) {
            Matchday::factory()->create(['season_id' => $season->id]);
        }

        $distinctNumbers = Matchday::where('season_id', $season->id)->distinct()->count('number');

        $this->assertSame(20, $distinctNumbers);
    }

    public function test_a_batch_created_in_one_call_gets_unique_numbers(): void
    {
        $season = Season::factory()->create();

        Matchday::factory()->count(20)->create(['season_id' => $season->id]);

        $distinctNumbers = Matchday::where('season_id', $season->id)->distinct()->count('number');

        $this->assertSame(20, $distinctNumbers);
    }

    /**
     * The factory reuses the season's existing division instead of minting a
     * new one per matchday: DivisionFactory draws its name from a two-value
     * unique pool, so one division per matchday would exhaust it (and would
     * also model the domain wrong — a season's jornadas belong to few
     * divisions, not one each).
     */
    public function test_matchdays_reuse_the_season_existing_division(): void
    {
        $season = Season::factory()->create();
        $division = Division::factory()->create(['season_id' => $season->id]);

        Matchday::factory()->count(3)->create(['season_id' => $season->id]);

        $this->assertSame(1, Division::where('season_id', $season->id)->count());
        $this->assertSame(3, Matchday::where('division_id', $division->id)->count());
    }

    public function test_the_division_always_belongs_to_the_matchday_season(): void
    {
        $season = Season::factory()->create();

        $matchday = Matchday::factory()->create(['season_id' => $season->id]);

        $this->assertSame($season->id, $matchday->division->season_id);
    }

    public function test_numbers_restart_per_division(): void
    {
        $season = Season::factory()->create();
        $primera = Division::factory()->create(['season_id' => $season->id]);
        $segunda = Division::factory()->create(['season_id' => $season->id]);

        $first = Matchday::factory()->create(['season_id' => $season->id, 'division_id' => $primera->id]);
        $second = Matchday::factory()->create(['season_id' => $season->id, 'division_id' => $segunda->id]);

        $this->assertSame(1, $first->number);
        $this->assertSame(1, $second->number);
    }

    public function test_numbers_restart_per_season(): void
    {
        $seasonA = Season::factory()->create();
        $seasonB = Season::factory()->create();

        $matchdayA = Matchday::factory()->create(['season_id' => $seasonA->id]);
        $matchdayB = Matchday::factory()->create(['season_id' => $seasonB->id]);

        $this->assertSame(1, $matchdayA->number);
        $this->assertSame(1, $matchdayB->number);
    }

    public function test_an_explicitly_passed_number_is_respected(): void
    {
        $season = Season::factory()->create();

        $matchday = Matchday::factory()->create(['season_id' => $season->id, 'number' => 7]);

        $this->assertSame(7, $matchday->number);
    }
}
