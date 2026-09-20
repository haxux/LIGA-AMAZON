<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Division;
use App\Models\Game;
use App\Models\Matchday;
use App\Models\Player;
use App\Models\Season;
use App\Models\SquadMembership;
use App\Models\Stadium;
use App\Models\StandingZone;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_season_and_team_relationship_resolves_both_directions(): void
    {
        $season = Season::factory()->create();
        $team = Team::factory()->for($season)->create();

        $this->assertTrue($season->teams->contains($team));
        $this->assertTrue($team->season->is($season));
    }

    public function test_club_and_player_relationship_resolves_both_directions(): void
    {
        $club = Club::factory()->create();
        $player = Player::factory()->for($club)->create();

        $this->assertTrue($club->players->contains($player));
        $this->assertTrue($player->club->is($club));
    }

    /**
     * El equipo llega a sus jugadores a través de la plantilla de esa
     * temporada, no directamente: es lo que permite que la plantilla del año
     * pasado siga siendo consultable (Fase 9).
     */
    public function test_a_squad_membership_links_a_player_to_one_seasons_team(): void
    {
        $team = Team::factory()->create();
        $player = Player::factory()->create(['club_id' => $team->club_id]);
        $membership = SquadMembership::factory()->create(['team_id' => $team->id, 'player_id' => $player->id]);

        $this->assertTrue($team->memberships->contains($membership));
        $this->assertTrue($team->players->contains($player));
        $this->assertTrue($player->memberships->contains($membership));
        $this->assertTrue($membership->team->is($team));
        $this->assertTrue($membership->player->is($player));
    }

    public function test_club_and_stadium_relationship_resolves_both_directions(): void
    {
        $club = Club::factory()->create();
        $stadium = Stadium::factory()->for($club)->create();

        $this->assertTrue($club->stadium->is($stadium));
        $this->assertTrue($stadium->club->is($club));
    }

    public function test_season_and_matchday_relationship_resolves_both_directions(): void
    {
        $season = Season::factory()->create();
        $matchday = Matchday::factory()->for($season)->create();

        $this->assertTrue($season->matchdays->contains($matchday));
        $this->assertTrue($matchday->season->is($season));
    }

    public function test_division_and_matchday_relationship_resolves_both_directions(): void
    {
        $division = Division::factory()->create();
        $matchday = Matchday::factory()->for($division->season)->for($division)->create();

        $this->assertTrue($division->matchdays->contains($matchday));
        $this->assertTrue($matchday->division->is($division));
    }

    public function test_division_and_standing_zone_relationship_resolves_both_directions(): void
    {
        $division = Division::factory()->create();
        $zone = StandingZone::factory()->for($division)->create();

        $this->assertTrue($division->standingZones->contains($zone));
        $this->assertTrue($zone->division->is($division));
    }

    public function test_matchday_and_game_relationship_resolves_both_directions(): void
    {
        $season = Season::factory()->create();
        $homeTeam = Team::factory()->for($season)->create();
        $awayTeam = Team::factory()->for($season)->create();
        $matchday = Matchday::factory()->for($season)->create();

        $game = Game::factory()->for($matchday)->create([
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
        ]);

        $this->assertTrue($matchday->games->contains($game));
        $this->assertTrue($game->matchday->is($matchday));
    }

    public function test_game_home_and_away_team_relationships_resolve_both_directions(): void
    {
        $season = Season::factory()->create();
        $homeTeam = Team::factory()->for($season)->create();
        $awayTeam = Team::factory()->for($season)->create();
        $matchday = Matchday::factory()->for($season)->create();

        $game = Game::factory()->for($matchday)->create([
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
        ]);

        $this->assertTrue($game->homeTeam->is($homeTeam));
        $this->assertTrue($game->awayTeam->is($awayTeam));
        $this->assertTrue($homeTeam->homeGames->contains($game));
        $this->assertTrue($awayTeam->awayGames->contains($game));
    }
}
