<?php

namespace Tests\Feature;

use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * La posición específica de la Fase 10: la general dice a qué se dedica un
 * jugador, esta dice dónde juega exactamente.
 */
class SpecificPositionTest extends TestCase
{
    use RefreshDatabase;

    public function test_players_table_carries_a_specific_position(): void
    {
        $this->assertTrue(Schema::hasColumn('players', 'specific_position'));
    }

    public function test_the_vocabulary_holds_the_twenty_seven_agreed_positions(): void
    {
        $this->assertCount(27, Player::SPECIFIC_POSITIONS);

        foreach (['POR', 'DFC', 'LI', 'LD', 'MC', 'MCO', 'ED', 'EI', 'DC'] as $expected) {
            $this->assertContains($expected, Player::SPECIFIC_POSITIONS);
        }
    }

    public function test_a_player_can_be_saved_without_one(): void
    {
        $player = Player::factory()->create(['specific_position' => null]);

        $this->assertNull($player->fresh()->specific_position);
    }

    public function test_a_position_outside_the_vocabulary_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        Player::factory()->create(['specific_position' => 'XYZ']);
    }

    /**
     * Un portero no juega de extremo. La posición específica tiene que ser
     * coherente con la general, o la ficha pública agrupa mal a los jugadores.
     */
    public function test_a_specific_position_from_another_general_position_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        Player::factory()->create([
            'position' => Player::POSITION_GOALKEEPER,
            'specific_position' => 'ED',
        ]);
    }

    public function test_every_general_position_has_its_own_specific_ones(): void
    {
        foreach (Player::POSITIONS as $general) {
            $this->assertNotEmpty(
                Player::specificPositionsFor($general),
                "{$general} debería tener posiciones específicas",
            );
        }

        $this->assertSame(['POR'], Player::specificPositionsFor(Player::POSITION_GOALKEEPER));
    }
}
