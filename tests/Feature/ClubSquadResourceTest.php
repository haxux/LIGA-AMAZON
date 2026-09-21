<?php

namespace Tests\Feature;

use App\Filament\Club\Resources\Squad\Pages\EditSquadPlayer;
use App\Filament\Club\Resources\Squad\Pages\ListSquad;
use App\Models\Club;
use App\Models\Player;
use App\Models\Season;
use App\Models\SquadMembership;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El módulo Plantilla del panel del técnico (Fase 10).
 */
class ClubSquadResourceTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private User $coach;

    protected function setUp(): void
    {
        parent::setUp();

        $this->club = Club::factory()->create();
        $this->coach = User::factory()->coachOf($this->club)->create();
        // El panel del técnico tiene guard propio desde la corrección de la
        // Fase 13; entrar por `web` dejaría las peticiones HTTP sin sesión.
        $this->actingAs($this->coach, 'club');
        Filament::setCurrentPanel('club');
    }

    public function test_the_squad_lists_only_the_coachs_players(): void
    {
        $own = Player::factory()->create(['club_id' => $this->club->id]);
        $foreign = Player::factory()->create();

        Livewire::test(ListSquad::class)
            ->assertCanSeeTableRecords([$own])
            ->assertCanNotSeeTableRecords([$foreign]);
    }

    public function test_the_coach_assigns_a_specific_position(): void
    {
        $player = Player::factory()->create(['club_id' => $this->club->id, 'position' => 'Defender']);

        Livewire::test(EditSquadPlayer::class, ['record' => $player->getRouteKey()])
            ->fillForm(['position' => 'Defender', 'specific_position' => 'LI'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('LI', $player->fresh()->specific_position);
    }

    public function test_the_coach_changes_the_general_position_too(): void
    {
        $player = Player::factory()->create(['club_id' => $this->club->id, 'position' => 'Defender']);

        Livewire::test(EditSquadPlayer::class, ['record' => $player->getRouteKey()])
            ->fillForm(['position' => 'Midfielder', 'specific_position' => 'MC'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Midfielder', $player->fresh()->position);
        $this->assertSame('MC', $player->fresh()->specific_position);
    }

    /**
     * El valor, en cambio, no: lo pone el administrador y aquí sólo se lee.
     */
    public function test_the_coach_cannot_set_the_value(): void
    {
        $player = Player::factory()->create(['club_id' => $this->club->id, 'market_value' => 90000]);

        $component = Livewire::test(EditSquadPlayer::class, ['record' => $player->getRouteKey()])->instance();

        $this->assertNull($component->getSchemaComponent('form.market_value'));
        $this->assertSame(90000, $player->fresh()->market_value);
    }

    /**
     * Las opciones siguen a la posición general, así que un portero sólo puede
     * ser POR y un delantero no aparece como lateral.
     */
    public function test_the_specific_options_follow_the_general_position(): void
    {
        $player = Player::factory()->create(['club_id' => $this->club->id, 'position' => Player::POSITION_GOALKEEPER]);

        $options = Livewire::test(EditSquadPlayer::class, ['record' => $player->getRouteKey()])
            ->instance()
            ->getSchemaComponent('form.specific_position')
            ->getOptions();

        $this->assertSame(['POR' => 'POR'], $options);
    }

    /**
     * Por HTTP y no con Livewire::test(), que instancia la página directamente
     * y se salta el kernel: es la misma razón por la que PanelAccessTest existe.
     */
    public function test_the_coach_cannot_open_another_clubs_player(): void
    {
        $foreign = Player::factory()->create();

        $this->get("/club/plantilla/{$foreign->getKey()}/edit")->assertNotFound();
    }

    public function test_the_coach_opens_their_own_player_over_http(): void
    {
        $own = Player::factory()->create(['club_id' => $this->club->id]);

        $this->get("/club/plantilla/{$own->getKey()}/edit")->assertSuccessful();
    }

    /**
     * El dorsal no vive en el jugador sino en la plantilla de cada temporada,
     * así que la columna lee el de la temporada vigente.
     */
    public function test_the_shirt_number_comes_from_this_seasons_squad(): void
    {
        $season = Season::factory()->create(['is_current' => true]);
        $team = Team::factory()->create(['club_id' => $this->club->id, 'season_id' => $season->id]);
        $player = Player::factory()->create(['club_id' => $this->club->id]);
        SquadMembership::factory()->create(['team_id' => $team->id, 'player_id' => $player->id, 'shirt_number' => 17]);

        Livewire::test(ListSquad::class)->assertTableColumnStateSet('shirt_number', 17, $player);
    }
}
