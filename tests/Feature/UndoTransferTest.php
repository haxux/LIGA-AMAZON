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
use App\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Borrar un traspaso ejecutado deshace TODO lo que movió.
 *
 * El dinero ya volvía solo —los movimientos cuelgan del traspaso con cascada—
 * pero el jugador se quedaba a medio camino. El 2026-10-17 la venta de Lucas
 * Chávez se aprobó y después se borró el traspaso: acabó siendo de La Fábrica y
 * marcado como ido al CD Tondela a la vez, fuera de toda plantilla, y desde el
 * chat ya no se le podía ni volver a ofrecer.
 */
class UndoTransferTest extends TestCase
{
    use RefreshDatabase;

    private Season $season;

    private Club $origin;

    private Club $destination;

    private Team $originTeam;

    private Team $destinationTeam;

    private Player $player;

    protected function setUp(): void
    {
        parent::setUp();

        $this->season = Season::factory()->create(['is_current' => true]);
        $this->origin = Club::factory()->create(['name' => 'La Fábrica', 'initial_balance' => 10_000_000]);
        $this->destination = Club::factory()->create(['name' => 'Club Comprador', 'initial_balance' => 10_000_000]);

        $this->originTeam = Team::factory()->create(['club_id' => $this->origin->id, 'season_id' => $this->season->id]);
        $this->destinationTeam = Team::factory()->create(['club_id' => $this->destination->id, 'season_id' => $this->season->id]);

        $this->player = Player::factory()->create(['club_id' => $this->origin->id, 'name' => 'Lucas Chávez']);
        SquadMembership::factory()->create([
            'team_id' => $this->originTeam->id,
            'player_id' => $this->player->id,
            'shirt_number' => 30,
        ]);
    }

    private function sellOutside(): Transfer
    {
        return Transfer::create([
            'player_id' => $this->player->id,
            'season_id' => $this->season->id,
            'type' => Transfer::TYPE_SALE,
            'scope' => Transfer::SCOPE_EXTERNAL,
            'status' => Transfer::STATUS_EXECUTED,
            'from_club_id' => $this->origin->id,
            'external_club' => 'CD Tondela',
            'fee' => 1_450_000,
        ]);
    }

    /** El caso exacto de Chávez. */
    public function test_deleting_an_external_sale_brings_the_player_back(): void
    {
        $transfer = $this->sellOutside();

        // Como quedaba: fuera de la plantilla y marcado como ido.
        $this->assertSame(0, $this->originTeam->memberships()->count());
        $this->assertNotNull($this->player->fresh()->left_at);

        $transfer->delete();

        $player = $this->player->fresh();
        $this->assertNull($player->left_at, 'Se le quita la marca de salida');
        $this->assertNull($player->left_to);
        $this->assertSame(1, $this->originTeam->memberships()->count(), 'Y vuelve a la plantilla');
        $this->assertSame($this->origin->id, $player->club_id);
    }

    /**
     * El dorsal NO se recupera: cerrar una pertenencia lo suelta, y el suyo
     * pudo cogerlo otro mientras tanto. Vuelve con el primero libre.
     */
    public function test_he_comes_back_with_the_first_free_shirt(): void
    {
        $transfer = $this->sellOutside();

        // Otro se queda su 30 mientras él está fuera.
        $otro = Player::factory()->create(['club_id' => $this->origin->id, 'name' => 'El Que Llego Despues']);
        SquadMembership::factory()->create([
            'team_id' => $this->originTeam->id,
            'player_id' => $otro->id,
            'shirt_number' => 30,
        ]);

        $transfer->delete();

        $suya = $this->originTeam->memberships()->where('player_id', $this->player->id)->first();
        $this->assertNotNull($suya);
        $this->assertNotSame(30, $suya->shirt_number, 'El 30 ya no es suyo');
        $this->assertNotNull($suya->shirt_number);
    }

    public function test_deleting_an_internal_signing_returns_him_to_the_selling_club(): void
    {
        $transfer = Transfer::create([
            'player_id' => $this->player->id,
            'season_id' => $this->season->id,
            'type' => Transfer::TYPE_SIGNING,
            'scope' => Transfer::SCOPE_INTERNAL,
            'status' => Transfer::STATUS_EXECUTED,
            'from_club_id' => $this->origin->id,
            'to_club_id' => $this->destination->id,
            'fee' => 1_000_000,
        ]);

        $this->assertSame($this->destination->id, $this->player->fresh()->club_id);

        $transfer->delete();

        $this->assertSame($this->origin->id, $this->player->fresh()->club_id, 'La propiedad vuelve');
        $this->assertSame(1, $this->originTeam->memberships()->count());
        $this->assertSame(0, $this->destinationTeam->memberships()->count(), 'Y sale de la del comprador');
    }

    /**
     * Sale de la plantilla del comprador pero su paso NO se borra: si llegó a
     * jugar allí, hay que poder anotarle esos eventos.
     */
    public function test_his_time_at_the_buyer_still_shows(): void
    {
        $transfer = Transfer::create([
            'player_id' => $this->player->id,
            'season_id' => $this->season->id,
            'type' => Transfer::TYPE_SIGNING,
            'scope' => Transfer::SCOPE_INTERNAL,
            'status' => Transfer::STATUS_EXECUTED,
            'from_club_id' => $this->origin->id,
            'to_club_id' => $this->destination->id,
            'fee' => 1_000_000,
        ]);

        $transfer->delete();

        $this->assertSame(0, $this->destinationTeam->memberships()->count());
        $this->assertSame(1, $this->destinationTeam->allMemberships()->count(), 'Consta que estuvo');
    }

    public function test_the_money_goes_back_as_it_always_did(): void
    {
        $transfer = Transfer::create([
            'player_id' => $this->player->id,
            'season_id' => $this->season->id,
            'type' => Transfer::TYPE_SIGNING,
            'scope' => Transfer::SCOPE_INTERNAL,
            'status' => Transfer::STATUS_EXECUTED,
            'from_club_id' => $this->origin->id,
            'to_club_id' => $this->destination->id,
            'fee' => 1_000_000,
        ]);

        $this->assertSame(11_000_000, app(BudgetService::class)->balanceFor($this->origin->fresh()));

        $transfer->delete();

        $this->assertSame(0, BudgetMovement::query()->where('transfer_id', $transfer->id)->count());
        $this->assertSame(10_000_000, app(BudgetService::class)->balanceFor($this->origin->fresh()));
        $this->assertSame(10_000_000, app(BudgetService::class)->balanceFor($this->destination->fresh()));
    }

    /**
     * Deshacer un traspaso anterior lo devolvería a un club del que otro
     * posterior ya lo sacó. Las dos operaciones se contradirían.
     */
    public function test_an_older_transfer_does_not_move_the_player(): void
    {
        $primero = Transfer::create([
            'player_id' => $this->player->id,
            'season_id' => $this->season->id,
            'type' => Transfer::TYPE_SIGNING,
            'scope' => Transfer::SCOPE_INTERNAL,
            'status' => Transfer::STATUS_EXECUTED,
            'from_club_id' => $this->origin->id,
            'to_club_id' => $this->destination->id,
            'fee' => 1_000_000,
        ]);

        $tercero = Club::factory()->create(['name' => 'Tercer Club', 'initial_balance' => 10_000_000]);
        Team::factory()->create(['club_id' => $tercero->id, 'season_id' => $this->season->id]);

        Transfer::create([
            'player_id' => $this->player->id,
            'season_id' => $this->season->id,
            'type' => Transfer::TYPE_SIGNING,
            'scope' => Transfer::SCOPE_INTERNAL,
            'status' => Transfer::STATUS_EXECUTED,
            'from_club_id' => $this->destination->id,
            'to_club_id' => $tercero->id,
            'fee' => 2_000_000,
        ]);

        $this->assertFalse(app(TransferService::class)->isLastExecutedFor($primero));

        $primero->delete();

        $this->assertSame($tercero->id, $this->player->fresh()->club_id, 'Sigue donde lo dejó el último');
    }

    /** Una propuesta no movió nada, así que borrarla no deshace nada. */
    public function test_deleting_a_proposal_touches_nothing(): void
    {
        $propuesta = Transfer::create([
            'player_id' => $this->player->id,
            'season_id' => $this->season->id,
            'type' => Transfer::TYPE_SIGNING,
            'scope' => Transfer::SCOPE_INTERNAL,
            'status' => Transfer::STATUS_PROPOSED,
            'from_club_id' => $this->origin->id,
            'to_club_id' => $this->destination->id,
            'fee' => 1_000_000,
        ]);

        $propuesta->delete();

        $this->assertSame(1, $this->originTeam->memberships()->count());
        $this->assertSame(30, $this->originTeam->memberships()->first()->shirt_number, 'Ni le toca el dorsal');
    }
}
