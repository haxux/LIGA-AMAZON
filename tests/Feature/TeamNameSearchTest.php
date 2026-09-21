<?php

namespace Tests\Feature;

use App\Filament\Resources\Cups\Pages\EditCup;
use App\Filament\Resources\Cups\RelationManagers\ParticipantsRelationManager;
use App\Filament\Resources\CupTies\Pages\ListCupTies;
use App\Filament\Resources\Games\Pages\ListGames;
use App\Filament\Resources\Teams\Pages\ListTeams;
use App\Models\Cup;
use App\Models\CupRound;
use App\Models\CupTeam;
use App\Models\CupTie;
use App\Models\Division;
use App\Models\Game;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * REGRESIÓN: buscar por el nombre de un equipo reventaba con «Unknown column
 * 'name' in 'where clause'».
 *
 * Desde la Fase 9 `teams` no tiene nombre —lo lleva su club, y el modelo lo
 * delega con un accesor—, así que pintar la columna funcionaba pero consultarla
 * no. Estuvo latente un año de commits porque nadie había buscado por ahí.
 */
class TeamNameSearchTest extends TestCase
{
    use RefreshDatabase;

    private Season $season;

    private Team $manaos;

    private Team $tapajos;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        $this->season = Season::factory()->create(['is_current' => true, 'name' => '2026/27']);
        $division = Division::factory()->create(['season_id' => $this->season->id, 'name' => 'Primera']);

        $this->manaos = Team::factory()->create(['season_id' => $this->season->id, 'division_id' => $division->id, 'name' => 'Manaos FC']);
        $this->tapajos = Team::factory()->create(['season_id' => $this->season->id, 'division_id' => $division->id, 'name' => 'Tapajós SC']);
    }

    public function test_games_can_be_searched_by_team_name(): void
    {
        $matchday = Matchday::factory()->create(['season_id' => $this->season->id, 'division_id' => $this->manaos->division_id]);

        $theirs = Game::factory()->for($matchday)->create([
            'home_team_id' => $this->manaos->id,
            'away_team_id' => $this->tapajos->id,
        ]);

        $other = Team::factory()->create(['season_id' => $this->season->id, 'division_id' => $this->manaos->division_id, 'name' => 'Iquitos United']);
        $another = Team::factory()->create(['season_id' => $this->season->id, 'division_id' => $this->manaos->division_id, 'name' => 'Belém Athletic']);
        $mine = Game::factory()->for($matchday)->create(['home_team_id' => $other->id, 'away_team_id' => $another->id]);

        Livewire::test(ListGames::class)
            ->searchTable('Manaos')
            ->assertCanSeeTableRecords([$theirs])
            ->assertCanNotSeeTableRecords([$mine]);
    }

    public function test_games_can_be_sorted_by_team_name(): void
    {
        $matchday = Matchday::factory()->create(['season_id' => $this->season->id, 'division_id' => $this->manaos->division_id]);
        Game::factory()->for($matchday)->create(['home_team_id' => $this->tapajos->id, 'away_team_id' => $this->manaos->id]);
        Game::factory()->for($matchday)->create(['home_team_id' => $this->manaos->id, 'away_team_id' => $this->tapajos->id]);

        Livewire::test(ListGames::class)
            ->sortTable('homeTeam.name')
            ->assertOk();
    }

    public function test_cup_ties_can_be_searched_by_team_name(): void
    {
        $cup = Cup::factory()->create(['season_id' => $this->season->id, 'name' => 'Copa Amazonas']);
        $round = CupRound::create(['cup_id' => $cup->id, 'name' => 'Final', 'position' => 1, 'legs' => 1]);
        $tie = CupTie::create([
            'cup_round_id' => $round->id,
            'home_team_id' => $this->manaos->id,
            'away_team_id' => $this->tapajos->id,
        ]);

        Livewire::test(ListCupTies::class)
            ->searchTable('Tapajós')
            ->assertCanSeeTableRecords([$tie]);

        Livewire::test(ListCupTies::class)
            ->searchTable('Iquitos')
            ->assertCanNotSeeTableRecords([$tie]);
    }

    public function test_cup_participants_can_be_searched_by_team_name(): void
    {
        $cup = Cup::factory()->create(['season_id' => $this->season->id, 'name' => 'Copa Amazonas']);
        $entered = CupTeam::create(['cup_id' => $cup->id, 'team_id' => $this->manaos->id]);
        $other = CupTeam::create(['cup_id' => $cup->id, 'team_id' => $this->tapajos->id]);

        Livewire::test(ParticipantsRelationManager::class, [
            'ownerRecord' => $cup,
            'pageClass' => EditCup::class,
        ])
            ->searchTable('Manaos')
            ->assertCanSeeTableRecords([$entered])
            ->assertCanNotSeeTableRecords([$other]);
    }

    /**
     * El nombre corto es del club por la misma razón.
     */
    public function test_teams_can_be_searched_by_their_short_name(): void
    {
        Livewire::test(ListTeams::class)
            ->searchTable($this->manaos->short_name)
            ->assertCanSeeTableRecords([$this->manaos])
            ->assertCanNotSeeTableRecords([$this->tapajos]);
    }
}
