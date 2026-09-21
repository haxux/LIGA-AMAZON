<?php

namespace Tests\Feature;

use App\Filament\Club\Pages\Chat as CoachChat;
use App\Filament\Resources\Offers\OfferResource;
use App\Filament\Resources\Offers\Pages\ListOffers;
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
use Filament\Facades\Filament;
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
        Filament::setCurrentPanel('club');

        Livewire::test(CoachChat::class)
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
    public function test_accepting_moves_neither_squads_nor_budgets(): void
    {
        $offer = $this->offer();

        app(OfferService::class)->accept($offer, $this->seller);

        $this->assertSame(Offer::STATUS_ACCEPTED, $offer->fresh()->status);
        $this->assertSame(0, BudgetMovement::query()->count());
        $this->assertSame(0, Transfer::query()->count());
        $this->assertSame($this->selling->id, $this->player->fresh()->club_id);
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
    public function test_the_administrator_executes_an_accepted_offer(): void
    {
        $offer = $this->offer(300_000);
        app(OfferService::class)->accept($offer, $this->seller);

        $admin = User::factory()->create();
        $transfer = app(OfferService::class)->execute($offer->fresh(), $admin);

        $this->assertSame(Transfer::TYPE_SIGNING, $transfer->type);
        $this->assertSame($this->selling->id, $transfer->from_club_id);
        $this->assertSame($this->buying->id, $transfer->to_club_id);
        $this->assertSame(300_000, $transfer->fee);

        $this->assertSame(Offer::STATUS_EXECUTED, $offer->fresh()->status);
        $this->assertSame($transfer->id, $offer->fresh()->transfer_id);

        // Y el traspaso hace lo suyo: los dos saldos y la plantilla.
        $this->assertSame(1_300_000, app(BudgetService::class)->balanceFor($this->selling->fresh()));
        $this->assertSame(700_000, app(BudgetService::class)->balanceFor($this->buying->fresh()));
        $this->assertSame($this->buying->id, $this->player->fresh()->club_id);
    }

    public function test_only_an_accepted_offer_can_be_executed(): void
    {
        $offer = $this->offer();

        $this->expectException(ValidationException::class);
        app(OfferService::class)->execute($offer, User::factory()->create());
    }

    public function test_a_coach_cannot_execute_an_offer(): void
    {
        $offer = $this->offer();
        app(OfferService::class)->accept($offer, $this->seller);

        $this->expectException(ValidationException::class);
        app(OfferService::class)->execute($offer->fresh(), $this->seller);
    }

    public function test_the_administrators_tray_lists_what_is_waiting_and_executes_it(): void
    {
        $offer = $this->offer(200_000);
        app(OfferService::class)->accept($offer, $this->seller);

        $this->actingAs(User::factory()->create());

        $this->assertSame('1', OfferResource::getNavigationBadge());

        Livewire::test(ListOffers::class)
            ->assertCanSeeTableRecords([$offer])
            ->callTableAction('execute', $offer)
            ->assertHasNoTableActionErrors();

        $this->assertSame(Offer::STATUS_EXECUTED, $offer->fresh()->status);
        $this->assertSame(1, Transfer::query()->count());
    }
}
