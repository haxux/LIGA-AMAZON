<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Matchday;
use App\Models\Season;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MatchdayGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_a_matchday_whose_division_belongs_to_another_season_is_rejected(): void
    {
        $season = Season::factory()->create();
        $foreignDivision = Division::factory()->create();

        $this->expectException(ValidationException::class);

        Matchday::create([
            'season_id' => $season->id,
            'division_id' => $foreignDivision->id,
            'number' => 1,
        ]);
    }

    public function test_saving_a_matchday_whose_division_belongs_to_its_own_season_is_allowed(): void
    {
        $season = Season::factory()->create();
        $division = Division::factory()->create(['season_id' => $season->id]);

        $matchday = Matchday::create([
            'season_id' => $season->id,
            'division_id' => $division->id,
            'number' => 1,
        ]);

        $this->assertTrue($matchday->exists);
    }

    public function test_moving_a_matchday_to_a_foreign_division_is_rejected_on_update(): void
    {
        $matchday = Matchday::factory()->create();
        $foreignDivision = Division::factory()->create();

        $this->expectException(ValidationException::class);

        $matchday->update(['division_id' => $foreignDivision->id]);
    }
}
