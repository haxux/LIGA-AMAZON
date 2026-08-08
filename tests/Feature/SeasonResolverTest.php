<?php

namespace Tests\Feature;

use App\Models\Season;
use App\Services\SeasonResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeasonResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_the_season_flagged_current(): void
    {
        Season::factory()->create(['is_current' => false]);
        $current = Season::factory()->create(['is_current' => true]);

        $resolved = (new SeasonResolver)->active();

        $this->assertNotNull($resolved);
        $this->assertTrue($resolved->is($current));
    }

    public function test_falls_back_to_the_latest_season_when_none_is_flagged(): void
    {
        Season::factory()->create(['is_current' => false]);
        $latest = Season::factory()->create(['is_current' => false]);

        $resolved = (new SeasonResolver)->active();

        $this->assertNotNull($resolved);
        $this->assertTrue($resolved->is($latest));
    }

    public function test_returns_null_when_no_seasons_exist(): void
    {
        $resolved = (new SeasonResolver)->active();

        $this->assertNull($resolved);
    }
}
