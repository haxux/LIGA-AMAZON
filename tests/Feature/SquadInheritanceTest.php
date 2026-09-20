<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Player;
use App\Models\Season;
use App\Models\SquadMembership;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Inscribir un club en una temporada nueva arrastra su plantilla: es lo que
 * hace que "el jugador no sea por temporada" signifique algo en la práctica.
 */
class SquadInheritanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrolling_a_club_in_a_new_season_inherits_its_previous_squad(): void
    {
        $club = Club::factory()->create();
        $lastSeason = Team::factory()->create(['club_id' => $club->id, 'season_id' => Season::factory()->create(['start_date' => '2025-08-01'])]);
        $players = Player::factory()->count(3)->create(['club_id' => $club->id]);
        $players->each(fn (Player $player, int $index) => SquadMembership::factory()->create([
            'team_id' => $lastSeason->id,
            'player_id' => $player->id,
            'shirt_number' => $index + 7,
        ]));

        $newSeason = Team::factory()->create(['club_id' => $club->id, 'season_id' => Season::factory()->create(['start_date' => '2026-08-01'])]);

        $this->assertSame(3, $newSeason->memberships()->count());
        foreach ($players as $index => $player) {
            $this->assertDatabaseHas('squad_memberships', [
                'team_id' => $newSeason->id,
                'player_id' => $player->id,
                'shirt_number' => $index + 7,
            ]);
        }
    }

    public function test_a_clubs_first_season_starts_with_an_empty_squad(): void
    {
        $team = Team::factory()->create();

        $this->assertSame(0, $team->memberships()->count());
    }

    /**
     * Una cesión termina con su temporada: renovarla es una decisión, no un
     * efecto secundario de inscribir al club.
     */
    public function test_loans_are_not_inherited(): void
    {
        $club = Club::factory()->create();
        $lastSeason = Team::factory()->create(['club_id' => $club->id, 'season_id' => Season::factory()->create(['start_date' => '2025-08-01'])]);
        SquadMembership::factory()->create(['team_id' => $lastSeason->id, 'shirt_number' => 5, 'type' => SquadMembership::TYPE_OWNED]);
        SquadMembership::factory()->create(['team_id' => $lastSeason->id, 'shirt_number' => 6, 'type' => SquadMembership::TYPE_LOAN]);

        $newSeason = Team::factory()->create(['club_id' => $club->id, 'season_id' => Season::factory()->create(['start_date' => '2026-08-01'])]);

        $this->assertSame(1, $newSeason->memberships()->count());
        $this->assertSame(SquadMembership::TYPE_OWNED, $newSeason->memberships()->first()->type);
    }

    public function test_the_squad_comes_from_the_most_recent_season_played(): void
    {
        $club = Club::factory()->create();
        $older = Team::factory()->create(['club_id' => $club->id, 'season_id' => Season::factory()->create(['start_date' => '2024-08-01'])]);
        $recent = Team::factory()->create(['club_id' => $club->id, 'season_id' => Season::factory()->create(['start_date' => '2025-08-01'])]);
        SquadMembership::factory()->count(2)->create(['team_id' => $older->id]);
        $recentMembership = SquadMembership::factory()->create(['team_id' => $recent->id, 'shirt_number' => 11]);

        $newSeason = Team::factory()->create(['club_id' => $club->id, 'season_id' => Season::factory()->create(['start_date' => '2026-08-01'])]);

        $this->assertSame(1, $newSeason->memberships()->count());
        $this->assertSame($recentMembership->player_id, $newSeason->memberships()->first()->player_id);
    }
}
