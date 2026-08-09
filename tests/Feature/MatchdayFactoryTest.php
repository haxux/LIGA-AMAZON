<?php

namespace Tests\Feature;

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
