<?php

namespace Tests\Feature;

use App\Filament\Resources\Players\Pages\CreatePlayer;
use App\Filament\Resources\Players\Pages\EditPlayer;
use App\Filament\Resources\Players\Pages\ListPlayers;
use App\Models\Club;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlayerResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_list_page_renders_successfully(): void
    {
        Livewire::test(ListPlayers::class)->assertOk();
    }

    public function test_create_page_renders_successfully(): void
    {
        Livewire::test(CreatePlayer::class)->assertOk();
    }

    public function test_edit_page_renders_successfully(): void
    {
        $player = Player::factory()->create();

        Livewire::test(EditPlayer::class, ['record' => $player->getRouteKey()])->assertOk();
    }

    /**
     * El formulario edita la identidad del jugador: club, nombre, posición y
     * fecha. El dorsal pertenece a la plantilla de cada temporada y se pone
     * desde el equipo (Fase 9).
     */
    public function test_can_create_a_player_via_the_form(): void
    {
        $club = Club::factory()->create();

        Livewire::test(CreatePlayer::class)
            ->fillForm([
                'club_id' => $club->id,
                'name' => 'Rivaldo Nunes',
                'position' => 'Forward',
                'birth_date' => '1998-04-12',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('players', [
            'club_id' => $club->id,
            'name' => 'Rivaldo Nunes',
        ]);
    }

    public function test_the_form_does_not_ask_for_a_shirt_number(): void
    {
        $component = Livewire::test(CreatePlayer::class)->instance();

        $this->assertNull($component->getSchemaComponent('form.shirt_number'));
    }

    public function test_can_edit_a_player_via_the_form(): void
    {
        $player = Player::factory()->create(['name' => 'Old Name']);

        Livewire::test(EditPlayer::class, ['record' => $player->getRouteKey()])
            ->fillForm([
                'name' => 'New Name',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('players', [
            'id' => $player->id,
            'name' => 'New Name',
        ]);
    }

    /**
     * La posición específica es de las dos partes (Fase 10): el técnico la
     * ajusta desde su panel y el administrador, desde aquí.
     */
    public function test_the_administrator_assigns_a_specific_position(): void
    {
        $player = Player::factory()->create(['position' => 'Defender', 'specific_position' => null]);

        Livewire::test(EditPlayer::class, ['record' => $player->getRouteKey()])
            ->fillForm(['position' => 'Defender', 'specific_position' => 'LD'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('LD', $player->fresh()->specific_position);
    }

    public function test_the_specific_options_follow_the_general_position(): void
    {
        $player = Player::factory()->create(['position' => Player::POSITION_GOALKEEPER]);

        $options = Livewire::test(EditPlayer::class, ['record' => $player->getRouteKey()])
            ->instance()
            ->getSchemaComponent('form.specific_position')
            ->getOptions();

        $this->assertSame(['POR' => 'POR'], $options);
    }

    /**
     * Cambiar la general deja sin sentido a la específica anterior, así que el
     * formulario la suelta en lugar de guardar un central de extremo.
     */
    public function test_changing_the_general_position_clears_the_specific_one(): void
    {
        $player = Player::factory()->create(['position' => 'Forward', 'specific_position' => 'ED']);

        Livewire::test(EditPlayer::class, ['record' => $player->getRouteKey()])
            ->fillForm(['position' => 'Midfielder'])
            ->assertFormSet(['specific_position' => null]);
    }

    public function test_position_filter_returns_only_matching_records(): void
    {
        $team = Team::factory()->create();
        $goalkeeper = Player::factory()->create(['team_id' => $team->id, 'position' => 'Goalkeeper', 'shirt_number' => 1]);
        $forward = Player::factory()->create(['team_id' => $team->id, 'position' => 'Forward', 'shirt_number' => 9]);

        Livewire::test(ListPlayers::class)
            ->filterTable('position', 'Goalkeeper')
            ->assertCanSeeTableRecords([$goalkeeper])
            ->assertCanNotSeeTableRecords([$forward]);
    }
}
