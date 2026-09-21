<?php

namespace Tests\Feature;

use App\Filament\Club\Resources\Squad\Pages\EditSquadPlayer;
use App\Filament\Club\Resources\Squad\Pages\ListSquad;
use App\Filament\Resources\Players\Pages\EditPlayer;
use App\Models\Club;
use App\Models\Player;
use App\Models\Season;
use App\Models\SquadMembership;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El valor de un jugador (Fase 12). Lo fija el administrador, es único —no por
 * temporada, decisión cerrada— y se lee en el panel del técnico y en la ficha
 * pública.
 */
class PlayerValueTest extends TestCase
{
    use RefreshDatabase;

    public function test_players_carry_an_optional_market_value(): void
    {
        $this->assertTrue(Schema::hasColumn('players', 'market_value'));

        $player = Player::factory()->create();

        $this->assertNull($player->fresh()->market_value);
    }

    public function test_a_negative_value_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        Player::factory()->create(['market_value' => -1]);
    }

    public function test_the_administrator_sets_the_value(): void
    {
        $this->actingAs(User::factory()->create());
        $player = Player::factory()->create();

        Livewire::test(EditPlayer::class, ['record' => $player->getRouteKey()])
            ->fillForm(['market_value' => 250000])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(250000, $player->fresh()->market_value);
    }

    /**
     * El técnico lo ve pero no lo toca: quien lo fija es el administrador.
     */
    public function test_the_coach_sees_the_value_but_cannot_set_it(): void
    {
        $club = Club::factory()->create();
        $this->actingAs(User::factory()->coachOf($club)->create());
        Filament::setCurrentPanel('club');

        $player = Player::factory()->create(['club_id' => $club->id, 'market_value' => 90000]);

        Livewire::test(ListSquad::class)
            ->assertCanSeeTableRecords([$player])
            ->assertTableColumnStateSet('market_value', 90000, $player);

        $this->assertNull(
            Livewire::test(EditSquadPlayer::class, ['record' => $player->getRouteKey()])
                ->instance()
                ->getSchemaComponent('form.market_value'),
        );
    }

    public function test_the_public_squad_shows_values_and_their_total(): void
    {
        $season = Season::factory()->create(['is_current' => true, 'name' => '2026/27']);
        $club = Club::factory()->create(['name' => 'Manaos FC']);
        $team = Team::factory()->create(['season_id' => $season->id, 'club_id' => $club->id]);

        foreach ([1200000, 800000] as $index => $value) {
            $player = Player::factory()->create(['club_id' => $club->id, 'market_value' => $value]);
            SquadMembership::factory()->create([
                'team_id' => $team->id,
                'player_id' => $player->id,
                'shirt_number' => $index + 1,
            ]);
        }

        // Entero con separador de miles y sin símbolo de moneda.
        $this->get(route('site.clubs.show', ['club' => $club->id, 'tab' => 'jugadores']))
            ->assertOk()
            ->assertSee('1.200.000')
            ->assertSee('2.000.000');
    }
}
