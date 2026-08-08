<?php

namespace Tests\Feature;

use App\Models\Season;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeasonCurrentGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_marking_a_season_current_unsets_every_other_season(): void
    {
        $seasonA = Season::factory()->create(['is_current' => true]);
        $seasonB = Season::factory()->create(['is_current' => false]);

        $seasonB->update(['is_current' => true]);

        $this->assertFalse($seasonA->fresh()->is_current);
        $this->assertTrue($seasonB->fresh()->is_current);
    }

    public function test_creating_a_current_season_unsets_the_previous_one(): void
    {
        $seasonA = Season::factory()->create(['is_current' => true]);

        $seasonB = Season::factory()->create(['is_current' => true]);

        $this->assertFalse($seasonA->fresh()->is_current);
        $this->assertTrue($seasonB->fresh()->is_current);
    }

    public function test_saving_a_non_current_season_leaves_the_current_flag_alone(): void
    {
        $current = Season::factory()->create(['is_current' => true]);
        $other = Season::factory()->create(['is_current' => false, 'name' => '2024/25']);

        $other->update(['name' => '2024/25 - Renamed']);

        $this->assertTrue($current->fresh()->is_current);
        $this->assertFalse($other->fresh()->is_current);
    }

    public function test_renaming_the_current_season_keeps_it_current(): void
    {
        $current = Season::factory()->create(['is_current' => true, 'name' => '2025/26']);

        $current->update(['name' => '2025/26 - Renamed']);

        $this->assertTrue($current->fresh()->is_current);
        $this->assertSame('2025/26 - Renamed', $current->fresh()->name);
    }
}
