<?php

namespace Tests\Feature;

use App\Filament\Resources\Offers\OfferResource;
use App\Filament\Resources\Offers\Pages\ListOffers;
use App\Livewire\Chat;
use App\Models\BudgetMovement;
use App\Models\Club;
use App\Models\Conversation;
use App\Models\Offer;
use App\Models\Player;
use App\Models\Season;
use App\Models\SquadMembership;
use App\Models\Team;
use App\Models\Transfer;
use App\Models\User;
use App\Services\BudgetService;
use App\Services\OfferService;
use App\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Las ofertas por un jugador (Fase 13, design D10): una máquina de estados
 * dentro del chat. Aceptar NO ejecuta nada.
 */
class OfferTest extends TestCase
{
    use RefreshDatabase;

    private Season $season;

    private Club $selling;

    private Club $buying;

    private User $seller;

    private User $buyer;

    private Player $player;

    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->season = Season::factory()->create(['is_current' => true, 'name' => '2026/27']);
        $this->selling = Club::factory()->create(['name' => 'Manaos FC', 'initial_balance' => 1_000_000]);
        $this->buying = Club::factory()->create(['name' => 'Tapajós SC', 'initial_balance' => 1_000_000]);

        $sellingTeam = Team::factory()->create(['club_id' => $this->selling->id, 'season_id' => $this->season->id]);
        Team::factory()->create(['club_id' => $this->buying->id, 'season_id' => $this->season->id]);

        $this->seller = User::factory()->coachOf($this->selling)->create(['name' => 'Vende']);
        $this->buyer = User::factory()->coachOf($this->buying)->create(['name' => 'Compra']);

        $this->player = Player::factory()->create(['club_id' => $this->selling->id, 'name' => 'El Fichaje']);
        SquadMembership::factory()->create([
            'team_id' => $sellingTeam->id,
            'player_id' => $this->player->id,
            'shirt_number' => 10,
        ]);

        $this->conversation = Conversation::between($this->buyer, $this->seller);
    }

    private function offer(int $amount = 300_000, string $status = Offer::STATUS_SENT, ?User $mover = null): Offer
    {
        return Offer::create([
            'conversation_id' => $this->conversation->id,
            'player_id' => $this->player->id,
            'from_club_id' => $this->buying->id,
            'amount' => $amount,
            'status' => $status,
            'moved_by' => ($mover ?? $this->buyer)->id,
        ]);
    }

    public function test_a_coach_offers_for_a_player_of_the_other_club(): void
    {
        $this->actingAs($this->buyer, 'club');

        Livewire::test(Chat::class)
            ->call('open', $this->conversation->id)
            ->set('offerPlayerId', (string) $this->player->id)
            ->set('offerAmount', '250000')
            ->call('sendOffer')
            ->assertSet('offerAmount', null);

        $this->assertDatabaseHas('offers', [
            'player_id' => $this->player->id,
            'from_club_id' => $this->buying->id,
            'amount' => 250_000,
            'status' => Offer::STATUS_SENT,
        ]);
    }

    /**
     * Entre técnicos se negocian las mismas cuatro operaciones que con la
     * dirección, con las dos puntas dentro de la liga.
     */
    public function test_a_coach_offers_one_of_theirs_for_sale(): void
    {
        $mine = Player::factory()->create(['club_id' => $this->buying->id, 'name' => 'El Mío']);

        $offer = Offer::create([
            'conversation_id' => $this->conversation->id,
            'player_id' => $mine->id,
            'kind' => Offer::KIND_SELL,
            'from_club_id' => $this->buying->id,
            'amount' => 200_000,
            'status' => Offer::STATUS_SENT,
            'moved_by' => $this->buyer->id,
        ]);

        $this->assertFalse($offer->isAsking());
        // Quien ofrece cede: el que se queda al jugador es el otro.
        $this->assertSame($this->buying->id, $offer->player->club_id);
    }

    public function test_a_loan_offer_carries_a_term_and_no_money(): void
    {
        $offer = Offer::create([
            'conversation_id' => $this->conversation->id,
            'player_id' => $this->player->id,
            'kind' => Offer::KIND_LOAN_IN,
            'from_club_id' => $this->buying->id,
            'amount' => 999,
            'loan_term' => Transfer::TERM_ONE_YEAR,
            'status' => Offer::STATUS_SENT,
            'moved_by' => $this->buyer->id,
        ]);

        // El importe se guarda en cero: una cesión no tiene coste, y dos formas
        // de decir lo mismo acaban discrepando.
        $this->assertSame(0, $offer->fresh()->amount);
        $this->assertTrue($offer->isFree());
    }

    /**
     * Pedir es pedir lo del otro; ofrecer es ofrecer lo propio. Al revés, la
     * operación no significa nada.
     */
    public function test_offering_a_player_who_is_not_yours_is_refused(): void
    {
        $this->expectException(ValidationException::class);

        Offer::create([
            'conversation_id' => $this->conversation->id,
            'player_id' => $this->player->id,
            'kind' => Offer::KIND_SELL,
            'from_club_id' => $this->buying->id,
            'amount' => 100,
            'status' => Offer::STATUS_SENT,
            'moved_by' => $this->buyer->id,
        ]);
    }

    /**
     * Y lo pactado es lo que se ejecuta: una cesión acordada en el chat no se
     * convierte en una compra al firmarla el administrador.
     */
    public function test_accepting_a_loan_offer_proposes_a_loan(): void
    {
        $offer = Offer::create([
            'conversation_id' => $this->conversation->id,
            'player_id' => $this->player->id,
            'kind' => Offer::KIND_LOAN_IN,
            'from_club_id' => $this->buying->id,
            'loan_term' => Transfer::TERM_SIX_MONTHS,
            'amount' => 0,
            'status' => Offer::STATUS_SENT,
            'moved_by' => $this->buyer->id,
        ]);

        app(OfferService::class)->accept($offer, $this->seller);

        $transfer = $offer->fresh()->transfer;

        $this->assertNotNull($transfer, 'Aceptar deja escrita la propuesta');
        $this->assertSame(Transfer::TYPE_LOAN_IN, $transfer->type);
        $this->assertSame(Transfer::TERM_SIX_MONTHS, $transfer->loan_term);
        $this->assertSame(Transfer::STATUS_PROPOSED, $transfer->status);

        // Y nada se ha movido todavía: eso lo hace la firma del administrador.
        $this->assertSame(0, BudgetMovement::query()->count());
        $this->assertDatabaseMissing('squad_memberships', [
            'team_id' => Team::query()->where('club_id', $this->buying->id)->value('id'),
            'player_id' => $this->player->id,
        ]);
    }

    public function test_offering_for_your_own_player_is_refused(): void
    {
        $this->expectException(ValidationException::class);

        $mine = Player::factory()->create(['club_id' => $this->buying->id]);

        Offer::create([
            'conversation_id' => $this->conversation->id,
            'player_id' => $mine->id,
            'from_club_id' => $this->buying->id,
            'amount' => 100,
            'status' => Offer::STATUS_SENT,
            'moved_by' => $this->buyer->id,
        ]);
    }

    public function test_an_offer_of_zero_is_refused(): void
    {
        $this->expectException(ValidationException::class);

        $this->offer(amount: 0);
    }

    /**
     * El corazón de D10: aceptar deja la oferta acordada y nada más. Ni
     * plantillas ni presupuestos se mueven hasta que el administrador firma.
     */
    /**
     * Aceptar cierra el trato y lo deja escrito como PROPUESTA, pero no mueve
     * ni una plantilla ni un euro: eso sigue siendo cosa del traspaso, que el
     * administrador firma desde Transfers.
     */
    public function test_accepting_proposes_the_transfer_without_moving_anything(): void
    {
        $offer = $this->offer();

        app(OfferService::class)->accept($offer, $this->seller);

        $this->assertSame(Offer::STATUS_ACCEPTED, $offer->fresh()->status);

        $transfer = $offer->fresh()->transfer;
        $this->assertNotNull($transfer);
        $this->assertSame(Transfer::STATUS_PROPOSED, $transfer->status);
        $this->assertSame(Transfer::SCOPE_INTERNAL, $transfer->scope);

        // Nada movido.
        $this->assertSame(0, BudgetMovement::query()->count());
        $this->assertSame($this->selling->id, $this->player->fresh()->club_id);
    }

    /**
     * El fallo del 2026-10-13, que costó la venta de Sandro: en una oferta de
     * VENTA el que recibía salía siendo el club del propio jugador, o sea el
     * que lo suelta, y el traspaso nacía de un club a sí mismo.
     */
    public function test_a_sale_offer_proposes_the_move_in_the_right_direction(): void
    {
        $offer = Offer::create([
            'conversation_id' => $this->conversation->id,
            'player_id' => $this->player->id,
            'kind' => Offer::KIND_SELL,
            // En una venta la ofrece el dueño del jugador.
            'from_club_id' => $this->selling->id,
            'amount' => 35_000_000,
            'status' => Offer::STATUS_SENT,
            'moved_by' => $this->seller->id,
        ]);

        app(OfferService::class)->accept($offer, $this->buyer);

        $transfer = $offer->fresh()->transfer;

        $this->assertNotNull($transfer);
        $this->assertSame($this->selling->id, $transfer->from_club_id, 'Suelta el dueño del jugador');
        $this->assertSame($this->buying->id, $transfer->to_club_id, 'Recibe el otro del hilo');
        $this->assertNotSame($transfer->from_club_id, $transfer->to_club_id);
    }

    public function test_a_purchase_offer_also_proposes_the_right_direction(): void
    {
        $offer = $this->offer();

        app(OfferService::class)->accept($offer, $this->seller);

        $transfer = $offer->fresh()->transfer;

        $this->assertSame($this->selling->id, $transfer->from_club_id);
        $this->assertSame($this->buying->id, $transfer->to_club_id);
    }

    /**
     * Y la propuesta llega entera a Transfers, que es donde se firma: el
     * administrador la aprueba allí y es entonces cuando se mueve todo.
     */
    public function test_the_administrator_signs_the_proposal_from_transfers(): void
    {
        $offer = $this->offer(300_000);
        app(OfferService::class)->accept($offer, $this->seller);

        $transfer = $offer->fresh()->transfer;
        $admin = User::factory()->create();

        app(TransferService::class)->approve($transfer, $admin, [
            'fee' => 300_000,
            'shirt_number' => 14,
        ]);

        $this->assertSame(Transfer::STATUS_EXECUTED, $transfer->fresh()->status);
        $this->assertSame($this->buying->id, $this->player->fresh()->club_id);
        $this->assertDatabaseHas('squad_memberships', [
            'team_id' => Team::query()->where('club_id', $this->buying->id)->value('id'),
            'player_id' => $this->player->id,
            'shirt_number' => 14,
        ]);
        $this->assertSame(700_000, app(BudgetService::class)->balanceFor($this->buying->fresh()));
        $this->assertSame(1_300_000, app(BudgetService::class)->balanceFor($this->selling->fresh()));
    }

    public function test_rejecting_closes_the_offer(): void
    {
        $offer = $this->offer();

        app(OfferService::class)->reject($offer, $this->seller);

        $this->assertSame(Offer::STATUS_REJECTED, $offer->fresh()->status);
        $this->assertFalse($offer->fresh()->isOpen());
    }

    /**
     * Negociar no reescribe la oferta anterior: la cierra como negociando y
     * nace otra, para que la cadena se lea entera en el hilo.
     */
    public function test_negotiating_leaves_a_counter_offer(): void
    {
        $offer = $this->offer(300_000);

        $counter = app(OfferService::class)->counter($offer, $this->seller, 450_000);

        $this->assertSame(Offer::STATUS_NEGOTIATING, $offer->fresh()->status);
        $this->assertSame(450_000, $counter->amount);
        $this->assertSame(Offer::STATUS_SENT, $counter->status);
        // El club comprador no cambia porque responda el vendedor.
        $this->assertSame($this->buying->id, $counter->from_club_id);
        $this->assertSame($this->seller->id, $counter->moved_by);
    }

    public function test_the_one_who_made_the_offer_cannot_answer_it(): void
    {
        $offer = $this->offer();

        $this->assertFalse(app(OfferService::class)->mayAnswer($offer, $this->buyer));
        $this->assertTrue(app(OfferService::class)->mayAnswer($offer, $this->seller));

        $this->expectException(ValidationException::class);
        app(OfferService::class)->accept($offer, $this->buyer);
    }

    public function test_an_outsider_cannot_answer_an_offer(): void
    {
        $offer = $this->offer();
        $outsider = User::factory()->coachOf(Club::factory()->create(['name' => 'Club Ajeno']))->create();

        $this->expectException(ValidationException::class);
        app(OfferService::class)->accept($offer, $outsider);
    }

    public function test_an_answered_offer_is_closed_to_further_answers(): void
    {
        $offer = $this->offer(status: Offer::STATUS_REJECTED);

        $this->assertFalse(app(OfferService::class)->mayAnswer($offer, $this->seller));
    }

    /**
     * La firma del administrador: un traspaso de los de la Fase 12, que al
     * guardarse paga, cobra y mueve al jugador.
     */
    /**
     * La bandeja del administrador es de MIRAR (decisión del propietario,
     * 2026-10-13): enseña lo que los clubes negocian y no ejecuta nada. Hasta
     * hoy tenía un botón de firmar, y esa segunda puerta construía mal el
     * traspaso en las ofertas de venta.
     */
    public function test_the_administrators_tray_lists_but_does_not_execute(): void
    {
        $offer = $this->offer(200_000);
        app(OfferService::class)->accept($offer, $this->seller);

        $this->actingAs(User::factory()->create());

        $this->assertSame('1', OfferResource::getNavigationBadge());

        Livewire::test(ListOffers::class)
            ->assertCanSeeTableRecords([$offer])
            ->assertTableActionDoesNotExist('execute');
    }

    /**
     * Y no queda ninguna otra puerta: el servicio ya no sabe ejecutar.
     */
    public function test_the_offer_service_can_no_longer_execute(): void
    {
        $this->assertFalse(
            method_exists(OfferService::class, 'execute'),
            'Ejecutar desde la oferta era la segunda puerta al dinero; ya no existe.',
        );
    }

    /**
     * Si entre la oferta y el apretón de manos el jugador se fue a un TERCER
     * club, lo acordado ya no significa nada: ninguno de los dos que hablan lo
     * tiene. Sin esta comprobación se propondría un traspaso entre ellos por un
     * jugador que no es de ninguno.
     */
    public function test_an_offer_gone_stale_is_refused_instead_of_proposing_nonsense(): void
    {
        $offer = $this->offer();

        $tercero = Club::factory()->create(['name' => 'Otro Club']);
        $this->player->forceFill(['club_id' => $tercero->id])->save();

        $this->expectException(ValidationException::class);
        app(OfferService::class)->accept($offer, $this->seller);
    }
}
