<?php

namespace Tests\Feature;

use App\Filament\Resources\Cups\Pages\CreateCup;
use App\Filament\Resources\Cups\Pages\EditCup;
use App\Filament\Resources\Cups\Pages\ListCups;
use App\Filament\Resources\Cups\RelationManagers\GroupsRelationManager;
use App\Filament\Resources\Cups\RelationManagers\ParticipantsRelationManager;
use App\Filament\Resources\Cups\RelationManagers\RoundsRelationManager;
use App\Filament\Resources\CupTies\CupTieResource;
use App\Filament\Resources\CupTies\Pages\CreateCupTie;
use App\Filament\Resources\CupTies\Pages\EditCupTie;
use App\Filament\Resources\CupTies\Pages\ListCupTies;
use App\Filament\Resources\CupTies\RelationManagers\TieGamesRelationManager;
use App\Models\Cup;
use App\Models\CupRound;
use App\Models\CupTeam;
use App\Models\CupTie;
use App\Models\Division;
use App\Models\Game;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El administrador arma la copa con las manos: la crea, apunta equipos, pone
 * rondas, empareja y resuelve los empates. La aplicación no sortea nada
 * (decisión del propietario).
 */
class CupPanelTest extends TestCase
{
    use RefreshDatabase;

    private Season $season;

    private Cup $cup;

    private Team $one;

    private Team $two;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        $this->season = Season::factory()->create(['is_current' => true, 'name' => '2026/27']);
        $this->cup = Cup::factory()->create(['season_id' => $this->season->id, 'name' => 'Copa Amazonas']);

        $primera = Division::factory()->create(['season_id' => $this->season->id, 'name' => 'Primera']);
        $segunda = Division::factory()->create(['season_id' => $this->season->id, 'name' => 'Segunda']);

        $this->one = Team::factory()->create(['season_id' => $this->season->id, 'division_id' => $primera->id, 'name' => 'Manaos FC']);
        $this->two = Team::factory()->create(['season_id' => $this->season->id, 'division_id' => $segunda->id, 'name' => 'Tapajós SC']);
    }

    public function test_the_pages_render(): void
    {
        Livewire::test(ListCups::class)->assertOk();
        Livewire::test(CreateCup::class)->assertOk();
        Livewire::test(EditCup::class, ['record' => $this->cup->getRouteKey()])->assertOk();
        Livewire::test(ListCupTies::class)->assertOk();
        Livewire::test(CreateCupTie::class)->assertOk();
    }

    public function test_the_administrator_creates_a_cup(): void
    {
        Livewire::test(CreateCup::class)
            ->fillForm([
                'season_id' => $this->season->id,
                'name' => 'Copa de la Liga',
                'has_group_stage' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('cups', ['name' => 'Copa de la Liga', 'has_group_stage' => true]);
    }

    /**
     * Apuntar equipos de divisiones distintas es lo que una copa viene a
     * permitir, así que el desplegable los ofrece todos los de la temporada.
     */
    public function test_teams_from_any_division_can_be_entered(): void
    {
        Livewire::test(ParticipantsRelationManager::class, [
            'ownerRecord' => $this->cup,
            'pageClass' => EditCup::class,
        ])
            ->mountTableAction('create')
            ->setTableActionData(['team_id' => $this->two->id])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('cup_teams', ['cup_id' => $this->cup->id, 'team_id' => $this->two->id]);
    }

    public function test_a_team_is_not_entered_twice(): void
    {
        CupTeam::create(['cup_id' => $this->cup->id, 'team_id' => $this->one->id]);

        Livewire::test(ParticipantsRelationManager::class, [
            'ownerRecord' => $this->cup,
            'pageClass' => EditCup::class,
        ])
            ->mountTableAction('create')
            ->setTableActionData(['team_id' => $this->one->id])
            ->callMountedTableAction()
            ->assertHasTableActionErrors(['team_id']);
    }

    /**
     * Los grupos sólo salen si la copa los lleva: una copa de cuadro directo no
     * tiene dónde ponerlos.
     */
    public function test_groups_only_show_for_a_cup_that_has_them(): void
    {
        $this->assertFalse(GroupsRelationManager::canViewForRecord($this->cup, EditCup::class));

        $withGroups = Cup::factory()->withGroups()->create(['season_id' => $this->season->id, 'name' => 'Con grupos']);

        $this->assertTrue(GroupsRelationManager::canViewForRecord($withGroups, EditCup::class));
    }

    public function test_the_administrator_adds_a_round_with_its_legs(): void
    {
        Livewire::test(RoundsRelationManager::class, [
            'ownerRecord' => $this->cup,
            'pageClass' => EditCup::class,
        ])
            ->mountTableAction('create')
            ->setTableActionData(['name' => 'Semifinal', 'position' => 1, 'legs' => 2])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('cup_rounds', ['cup_id' => $this->cup->id, 'name' => 'Semifinal', 'legs' => 2]);
    }

    public function test_a_tie_only_pairs_teams_entered_in_that_cup(): void
    {
        CupTeam::create(['cup_id' => $this->cup->id, 'team_id' => $this->one->id]);
        $round = CupRound::create(['cup_id' => $this->cup->id, 'name' => 'Final', 'position' => 1, 'legs' => 1]);

        $options = Livewire::test(CreateCupTie::class)
            ->fillForm(['cup_round_id' => $round->id])
            ->instance()
            ->getSchemaComponent('form.home_team_id')
            ->getOptions();

        $this->assertArrayHasKey($this->one->id, $options);
        $this->assertArrayNotHasKey($this->two->id, $options, 'el que no está apuntado no se puede emparejar');
    }

    public function test_the_games_of_a_tie_are_loaded_from_the_tie(): void
    {
        $round = CupRound::create(['cup_id' => $this->cup->id, 'name' => 'Final', 'position' => 1, 'legs' => 1]);
        $tie = CupTie::create([
            'cup_round_id' => $round->id,
            'home_team_id' => $this->one->id,
            'away_team_id' => $this->two->id,
        ]);

        Livewire::test(TieGamesRelationManager::class, [
            'ownerRecord' => $tie,
            'pageClass' => EditCupTie::class,
        ])
            ->mountTableAction('create')
            ->setTableActionData([
                'home_team_id' => $this->one->id,
                'away_team_id' => $this->two->id,
                'home_score' => 2,
                'away_score' => 1,
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $game = Game::query()->latest('id')->first();

        $this->assertSame($tie->id, $game->cup_tie_id);
        $this->assertNull($game->matchday_id, 'un partido de copa no cuelga de ninguna jornada');
    }

    /**
     * La eliminatoria empatada es la única que pide decisión, y pide el motivo
     * con ella.
     */
    public function test_a_level_tie_is_settled_from_the_table(): void
    {
        $round = CupRound::create(['cup_id' => $this->cup->id, 'name' => 'Final', 'position' => 1, 'legs' => 1]);
        $tie = CupTie::create([
            'cup_round_id' => $round->id,
            'home_team_id' => $this->one->id,
            'away_team_id' => $this->two->id,
        ]);

        Game::create([
            'cup_tie_id' => $tie->id,
            'home_team_id' => $this->one->id,
            'away_team_id' => $this->two->id,
            'home_score' => 1,
            'away_score' => 1,
        ]);

        Livewire::test(ListCupTies::class)
            ->mountTableAction('decide', $tie)
            ->setTableActionData(['winner_team_id' => $this->two->id, 'decision_note' => 'Penaltis 5-4'])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertSame($this->two->id, $tie->fresh()->winner_team_id);
        $this->assertSame('Penaltis 5-4', $tie->fresh()->decision_note);
    }

    public function test_a_decided_tie_is_not_asked_about_again(): void
    {
        $round = CupRound::create(['cup_id' => $this->cup->id, 'name' => 'Final', 'position' => 1, 'legs' => 1]);
        $tie = CupTie::create([
            'cup_round_id' => $round->id,
            'home_team_id' => $this->one->id,
            'away_team_id' => $this->two->id,
        ]);

        Game::create([
            'cup_tie_id' => $tie->id,
            'home_team_id' => $this->one->id,
            'away_team_id' => $this->two->id,
            'home_score' => 3,
            'away_score' => 0,
        ]);

        Livewire::test(ListCupTies::class)->assertTableActionHidden('decide', $tie);
        $this->assertTrue(CupTieResource::canCreate());
    }
}
