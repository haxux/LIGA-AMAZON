<?php

namespace Tests\Feature;

use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlayerFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_eighteen_players_factory_for_one_team_yields_eighteen_distinct_shirt_numbers(): void
    {
        $seasonId = DB::table('seasons')->insertGetId(['name' => '2025/26', 'created_at' => now(), 'updated_at' => now()]);
        $clubHomeFCId = DB::table('clubs')->insertGetId(['name' => 'Home FC', 'short_name' => 'HOM', 'created_at' => now(), 'updated_at' => now()]);
        $teamId = DB::table('teams')->insertGetId(['season_id' => $seasonId, 'club_id' => $clubHomeFCId, 'created_at' => now(), 'updated_at' => now()]);

        $players = Player::factory()->count(18)->create(['team_id' => $teamId]);

        $distinctShirtNumbers = $players->pluck('shirt_number')->unique();

        $this->assertCount(18, $distinctShirtNumbers);
    }
}
