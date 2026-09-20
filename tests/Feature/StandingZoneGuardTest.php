<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\StandingZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StandingZoneGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_zone_whose_last_position_is_above_its_first_is_rejected(): void
    {
        $division = Division::factory()->create();

        $this->expectException(ValidationException::class);

        StandingZone::create([
            'division_id' => $division->id,
            'label' => 'Ascenso',
            'color' => 'green',
            'from_position' => 4,
            'to_position' => 2,
        ]);
    }

    public function test_a_zone_overlapping_another_in_the_same_division_is_rejected(): void
    {
        $division = Division::factory()->create();
        StandingZone::factory()->create([
            'division_id' => $division->id,
            'label' => 'Ascenso',
            'from_position' => 1,
            'to_position' => 3,
        ]);

        $this->expectException(ValidationException::class);

        StandingZone::create([
            'division_id' => $division->id,
            'label' => 'Fase europea',
            'color' => 'blue',
            'from_position' => 3,
            'to_position' => 6,
        ]);
    }

    public function test_the_same_positions_in_another_division_are_allowed(): void
    {
        $primera = Division::factory()->create();
        $segunda = Division::factory()->create();
        StandingZone::factory()->create(['division_id' => $primera->id, 'from_position' => 1, 'to_position' => 3]);

        $zone = StandingZone::create([
            'division_id' => $segunda->id,
            'label' => 'Ascenso',
            'color' => 'green',
            'from_position' => 1,
            'to_position' => 3,
        ]);

        $this->assertTrue($zone->exists);
    }

    public function test_editing_a_zone_does_not_collide_with_itself(): void
    {
        $zone = StandingZone::factory()->create(['from_position' => 1, 'to_position' => 3]);

        $zone->update(['to_position' => 4]);

        $this->assertSame(4, $zone->fresh()->to_position);
    }

    public function test_adjacent_zones_are_allowed(): void
    {
        $division = Division::factory()->create();
        StandingZone::factory()->create(['division_id' => $division->id, 'label' => 'Ascenso', 'from_position' => 1, 'to_position' => 2]);

        $zone = StandingZone::create([
            'division_id' => $division->id,
            'label' => 'Descenso',
            'color' => 'red',
            'from_position' => 3,
            'to_position' => 4,
        ]);

        $this->assertTrue($zone->exists);
    }

    public function test_covers_answers_for_the_band_edges(): void
    {
        $zone = StandingZone::factory()->make(['from_position' => 2, 'to_position' => 4]);

        $this->assertFalse($zone->covers(1));
        $this->assertTrue($zone->covers(2));
        $this->assertTrue($zone->covers(4));
        $this->assertFalse($zone->covers(5));
    }

    public function test_an_unknown_colour_falls_back_to_grey(): void
    {
        $zone = StandingZone::factory()->make(['color' => 'chartreuse']);

        $this->assertSame(StandingZone::COLORS['grey']['hex'], $zone->hex());
    }
}
