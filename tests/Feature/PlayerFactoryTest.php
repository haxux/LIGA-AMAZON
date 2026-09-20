<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Models\SquadMembership;
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

        Player::factory()->count(18)->create(['team_id' => $teamId]);

        $shirtNumbers = SquadMembership::where('team_id', $teamId)->pluck('shirt_number');

        $this->assertCount(18, $shirtNumbers->unique());
    }

    /**
     * El factory sigue aceptando `team_id` por comodidad aunque la columna ya no
     * exista: crea al jugador en el club de ese equipo y le añade la pertenencia
     * de esa temporada (Fase 9).
     */
    public function test_creating_a_player_for_a_team_files_them_under_its_club(): void
    {
        $seasonId = DB::table('seasons')->insertGetId(['name' => '2025/26', 'created_at' => now(), 'updated_at' => now()]);
        $clubId = DB::table('clubs')->insertGetId(['name' => 'Home FC', 'short_name' => 'HOM', 'created_at' => now(), 'updated_at' => now()]);
        $teamId = DB::table('teams')->insertGetId(['season_id' => $seasonId, 'club_id' => $clubId, 'created_at' => now(), 'updated_at' => now()]);

        $player = Player::factory()->create(['team_id' => $teamId, 'shirt_number' => 9]);

        $this->assertSame($clubId, $player->club_id);
        $this->assertDatabaseHas('squad_memberships', [
            'team_id' => $teamId,
            'player_id' => $player->id,
            'shirt_number' => 9,
            'type' => SquadMembership::TYPE_OWNED,
        ]);
    }

    public function test_a_player_created_without_a_team_still_belongs_to_a_club(): void
    {
        $player = Player::factory()->create();

        $this->assertNotNull($player->club);
        $this->assertSame(0, $player->memberships()->count());
    }
}
