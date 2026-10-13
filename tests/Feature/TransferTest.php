<?php

namespace Tests\Feature;

use App\Models\BudgetMovement;
use App\Models\Club;
use App\Models\Player;
use App\Models\Season;
use App\Models\SquadMembership;
use App\Models\Team;
use App\Models\Transfer;
use App\Services\BudgetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Fichajes, ventas y préstamos (Fase 12, design D8): guardar un traspaso lo
 * ejecuta — mueve al jugador y cuadra los dos presupuestos.
 */
class TransferTest extends TestCase
{
    use RefreshDatabase;

    private Season $season;

    private Club $selling;

    private Club $buying;

    private Team $sellingTeam;

    private Team $buyingTeam;

    private Player $player;

    protected function setUp(): void
    {
        parent::setUp();

        $this->season = Season::factory()->create(['is_current' => true, 'name' => '2026/27']);
        $this->selling = Club::factory()->create(['name' => 'Manaos FC', 'initial_balance' => 1_000_000]);
        $this->buying = Club::factory()->create(['name' => 'Tapajós SC', 'initial_balance' => 2_000_000]);
        $this->sellingTeam = Team::factory()->create(['club_id' => $this->selling->id, 'season_id' => $this->season->id]);
        $this->buyingTeam = Team::factory()->create(['club_id' => $this->buying->id, 'season_id' => $this->season->id]);

        $this->player = Player::factory()->create(['club_id' => $this->selling->id, 'name' => 'Jugador Traspasado']);
        SquadMembership::factory()->create([
            'team_id' => $this->sellingTeam->id,
            'player_id' => $this->player->id,
            'shirt_number' => 7,
        ]);
    }

    private function balance(Club $club): int
    {
        return app(BudgetService::class)->balanceFor($club->fresh());
    }

    public function test_an_in_league_signing_pays_moves_and_collects_in_one_write(): void
    {
        Transfer::create([
            'player_id' => $this->player->id,
            'season_id' => $this->season->id,
            'type' => Transfer::TYPE_SIGNING,
            'scope' => Transfer::SCOPE_INTERNAL,
            'from_club_id' => $this->selling->id,
            'to_club_id' => $this->buying->id,
            'fee' => 300_000,
        ]);

        // Dos movimientos, ya aprobados, uno en cada presupuesto.
        $this->assertSame(1_700_000, $this->balance($this->buying));
        $this->assertSame(1_300_000, $this->balance($this->selling));
        $this->assertSame(2, BudgetMovement::query()->count());

        // Y el jugador cambia de plantilla y de dueño.
        $this->assertSame($this->buying->id, $this->player->fresh()->club_id);
        $this->assertDatabaseHas('squad_memberships', [
            'team_id' => $this->buyingTeam->id,
            'player_id' => $this->player->id,
            'type' => SquadMembership::TYPE_OWNED,
        ]);
        $this->assertDatabaseMissing('squad_memberships', [
            'team_id' => $this->sellingTeam->id,
            'player_id' => $this->player->id,
        ]);
    }

    /**
     * Una venta entre clubes de la liga es el fichaje del comprador: registrarla
     * además desde el lado del vendedor sería la segunda fila que D8 descarta, y
     * el dinero se contaría dos veces.
     */
    public function test_an_in_league_sale_is_refused_as_such(): void
    {
        $this->expectException(ValidationException::class);

        Transfer::create([
            'player_id' => $this->player->id,
            'season_id' => $this->season->id,
            'type' => Transfer::TYPE_SALE,
            'scope' => Transfer::SCOPE_INTERNAL,
            'from_club_id' => $this->selling->id,
            'to_club_id' => $this->buying->id,
            'fee' => 300_000,
        ]);
    }

    public function test_a_loan_moves_the_player_without_moving_money(): void
    {
        Transfer::create([
            'player_id' => $this->player->id,
            'season_id' => $this->season->id,
            'type' => Transfer::TYPE_LOAN_IN,
            'scope' => Transfer::SCOPE_INTERNAL,
            'from_club_id' => $this->selling->id,
            'to_club_id' => $this->buying->id,
            'fee' => 0,
            'loan_term' => Transfer::TERM_ONE_YEAR,
        ]);

        $this->assertSame(2_000_000, $this->balance($this->buying));
        $this->assertSame(1_000_000, $this->balance($this->selling));
        $this->assertSame(0, BudgetMovement::query()->count());

        // Sigue siendo del club que lo cede, y su pertenencia nueva lo dice.
        $this->assertSame($this->selling->id, $this->player->fresh()->club_id);
        $this->assertDatabaseHas('squad_memberships', [
            'team_id' => $this->buyingTeam->id,
            'player_id' => $this->player->id,
            'type' => SquadMembership::TYPE_LOAN,
        ]);
    }

    public function test_a_sale_outside_the_league_collects_and_marks_the_player_as_gone(): void
    {
        Transfer::create([
            'player_id' => $this->player->id,
            'season_id' => $this->season->id,
            'type' => Transfer::TYPE_SALE,
            'scope' => Transfer::SCOPE_EXTERNAL,
            'from_club_id' => $this->selling->id,
            'external_club' => 'Palmeiras',
            'fee' => 500_000,
        ]);

        $this->assertSame(1_500_000, $this->balance($this->selling));

        $player = $this->player->fresh();

        // La ficha se conserva: sus goles y tarjetas cuelgan de ella (design D9).
        $this->assertNotNull($player);
        $this->assertNotNull($player->left_at);
        $this->assertSame('Palmeiras', $player->left_to);
        $this->assertDatabaseMissing('squad_memberships', [
            'team_id' => $this->sellingTeam->id,
            'player_id' => $this->player->id,
        ]);
    }

    public function test_a_signing_from_outside_the_league_only_pays(): void
    {
        $arriving = Player::factory()->create(['club_id' => $this->buying->id, 'name' => 'Recién Llegado']);

        Transfer::create([
            'player_id' => $arriving->id,
            'season_id' => $this->season->id,
            'type' => Transfer::TYPE_SIGNING,
            'scope' => Transfer::SCOPE_EXTERNAL,
            'to_club_id' => $this->buying->id,
            'external_club' => 'Palmeiras',
            'fee' => 400_000,
        ]);

        $this->assertSame(1_600_000, $this->balance($this->buying));
        $this->assertSame(1, BudgetMovement::query()->count());
        $this->assertDatabaseHas('squad_memberships', [
            'team_id' => $this->buyingTeam->id,
            'player_id' => $arriving->id,
        ]);
    }

    /**
     * El dorsal es obligatorio y único por equipo, así que la pertenencia nueva
     * se lleva el primero libre; retocarlo es cosa de la plantilla, que es
     * donde el dorsal vive.
     */
    public function test_the_new_membership_takes_the_first_free_shirt_number(): void
    {
        foreach ([1, 2, 3] as $number) {
            SquadMembership::factory()->create([
                'team_id' => $this->buyingTeam->id,
                'player_id' => Player::factory()->create(['club_id' => $this->buying->id])->id,
                'shirt_number' => $number,
            ]);
        }

        Transfer::create([
            'player_id' => $this->player->id,
            'season_id' => $this->season->id,
            'type' => Transfer::TYPE_SIGNING,
            'scope' => Transfer::SCOPE_INTERNAL,
            'from_club_id' => $this->selling->id,
            'to_club_id' => $this->buying->id,
            'fee' => 1,
        ]);

        $this->assertDatabaseHas('squad_memberships', [
            'team_id' => $this->buyingTeam->id,
            'player_id' => $this->player->id,
            'shirt_number' => 4,
        ]);
    }

    public function test_a_transfer_outside_the_league_needs_the_outside_clubs_name(): void
    {
        $this->expectException(ValidationException::class);

        Transfer::create([
            'player_id' => $this->player->id,
            'season_id' => $this->season->id,
            'type' => Transfer::TYPE_SIGNING,
            'scope' => Transfer::SCOPE_EXTERNAL,
            'to_club_id' => $this->buying->id,
            'fee' => 100,
        ]);
    }

    public function test_an_in_league_transfer_needs_both_ends(): void
    {
        $this->expectException(ValidationException::class);

        Transfer::create([
            'player_id' => $this->player->id,
            'season_id' => $this->season->id,
            'type' => Transfer::TYPE_SIGNING,
            'scope' => Transfer::SCOPE_INTERNAL,
            'to_club_id' => $this->buying->id,
            'fee' => 100,
        ]);
    }

    /**
     * Borrar un traspaso se lleva sus movimientos por la FK, así que el saldo
     * vuelve solo. La plantilla NO vuelve sola, y el panel lo advierte.
     */
    public function test_deleting_a_transfer_takes_its_movements_with_it(): void
    {
        $transfer = Transfer::create([
            'player_id' => $this->player->id,
            'season_id' => $this->season->id,
            'type' => Transfer::TYPE_SIGNING,
            'scope' => Transfer::SCOPE_INTERNAL,
            'from_club_id' => $this->selling->id,
            'to_club_id' => $this->buying->id,
            'fee' => 300_000,
        ]);

        $transfer->delete();

        $this->assertSame(0, BudgetMovement::query()->count());
        $this->assertSame(2_000_000, $this->balance($this->buying));
    }

    /**
     * El mismo traspaso es el fichaje de uno y la venta del otro: una fila
     * leída desde los dos lados.
     */
    public function test_a_transfer_reads_differently_from_each_side(): void
    {
        $transfer = Transfer::factory()->create([
            'player_id' => $this->player->id,
            'season_id' => $this->season->id,
            'from_club_id' => $this->selling->id,
            'to_club_id' => $this->buying->id,
            'fee' => 1,
        ]);

        $this->assertSame('Venta', $transfer->labelFor($this->selling));
        $this->assertSame('Fichaje', $transfer->labelFor($this->buying));
        $this->assertSame('Tapajós SC', $transfer->counterpartFor($this->selling));
        $this->assertSame('Manaos FC', $transfer->counterpartFor($this->buying));
    }

    /**
     * El fallo del 2026-10-13: el fichaje de Sandro se registró de Drakukeo a
     * Drakukeo y la operación hizo exactamente lo que se le pidió — lo sacó de
     * la plantilla, lo volvió a meter en la misma CON OTRO DORSAL, y movió 35
     * millones de un club a sí mismo. Nada falló y nada avisó.
     */
    public function test_a_club_cannot_sign_a_player_from_itself(): void
    {
        $this->expectException(ValidationException::class);

        Transfer::create([
            'player_id' => $this->player->id,
            'season_id' => $this->season->id,
            'scope' => Transfer::SCOPE_INTERNAL,
            'type' => Transfer::TYPE_SIGNING,
            'from_club_id' => $this->selling->id,
            'to_club_id' => $this->selling->id,
            'fee' => 35_000_000,
        ]);
    }

    public function test_nothing_moves_when_the_two_clubs_are_the_same(): void
    {
        $dorsalAntes = SquadMembership::query()
            ->where('team_id', $this->sellingTeam->id)
            ->where('player_id', $this->player->id)
            ->value('shirt_number');

        try {
            Transfer::create([
                'player_id' => $this->player->id,
                'season_id' => $this->season->id,
                'scope' => Transfer::SCOPE_INTERNAL,
                'type' => Transfer::TYPE_SIGNING,
                'from_club_id' => $this->selling->id,
                'to_club_id' => $this->selling->id,
                'fee' => 35_000_000,
            ]);
        } catch (ValidationException) {
            // Lo que importa es lo que NO pasó.
        }

        $this->assertSame(7, $dorsalAntes);
        $this->assertSame(7, SquadMembership::query()
            ->where('team_id', $this->sellingTeam->id)
            ->where('player_id', $this->player->id)
            ->value('shirt_number'), 'El dorsal no se toca');
        $this->assertSame(0, BudgetMovement::query()->count(), 'No se apunta dinero ninguno');
    }

    /**
     * El otro síntoma que reportó el propietario: nadie le preguntó con qué
     * dorsal llegaba. Ahora el alta lo lleva, y tiene que ser el que se eligió
     * y no el primero libre.
     */
    public function test_an_internal_signing_lands_with_the_chosen_shirt(): void
    {
        $transfer = new Transfer([
            'player_id' => $this->player->id,
            'season_id' => $this->season->id,
            'scope' => Transfer::SCOPE_INTERNAL,
            'type' => Transfer::TYPE_SIGNING,
            'from_club_id' => $this->selling->id,
            'to_club_id' => $this->buying->id,
            'fee' => 35_000_000,
        ]);
        $transfer->desiredShirtNumber = 21;
        $transfer->save();

        $this->assertDatabaseHas('squad_memberships', [
            'team_id' => $this->buyingTeam->id,
            'player_id' => $this->player->id,
            'shirt_number' => 21,
        ]);

        // Y sale de verdad del anterior.
        $this->assertDatabaseMissing('squad_memberships', [
            'team_id' => $this->sellingTeam->id,
            'player_id' => $this->player->id,
        ]);
        $this->assertSame($this->buying->id, $this->player->fresh()->club_id);
    }

    /**
     * Sin elegirlo, el primero libre, como siempre: el alta no se vuelve
     * obligatoria por haber ganado un campo.
     */
    public function test_an_internal_signing_without_a_chosen_shirt_takes_the_first_free_one(): void
    {
        Transfer::create([
            'player_id' => $this->player->id,
            'season_id' => $this->season->id,
            'scope' => Transfer::SCOPE_INTERNAL,
            'type' => Transfer::TYPE_SIGNING,
            'from_club_id' => $this->selling->id,
            'to_club_id' => $this->buying->id,
            'fee' => 35_000_000,
        ]);

        $this->assertDatabaseHas('squad_memberships', [
            'team_id' => $this->buyingTeam->id,
            'player_id' => $this->player->id,
            'shirt_number' => 1,
        ]);
    }
}
