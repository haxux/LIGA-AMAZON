<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Conversation;
use App\Models\Offer;
use App\Models\Player;
use App\Models\Season;
use App\Models\SquadMembership;
use App\Models\Team;
use App\Models\User;
use App\Services\OfferService;
use App\Services\PushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Los movimientos de una oferta avisan al otro lado del hilo (decisión del
 * propietario, 2026-10-22): oferta nueva, contraoferta, aceptación y rechazo.
 */
class OfferNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    private User $buyer;

    private Player $player;

    private Club $buying;

    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $season = Season::factory()->create(['is_current' => true]);
        $selling = Club::factory()->create(['name' => 'Manaos FC', 'initial_balance' => 1_000_000]);
        $this->buying = Club::factory()->create(['name' => 'Tapajós SC', 'initial_balance' => 1_000_000]);

        $sellingTeam = Team::factory()->create(['club_id' => $selling->id, 'season_id' => $season->id]);
        Team::factory()->create(['club_id' => $this->buying->id, 'season_id' => $season->id]);

        $this->seller = User::factory()->coachOf($selling)->create(['name' => 'Vende']);
        $this->buyer = User::factory()->coachOf($this->buying)->create(['name' => 'Compra']);

        $this->player = Player::factory()->create(['club_id' => $selling->id, 'name' => 'El Fichaje']);
        SquadMembership::factory()->create(['team_id' => $sellingTeam->id, 'player_id' => $this->player->id, 'shirt_number' => 10]);

        $this->conversation = Conversation::between($this->buyer, $this->seller);
    }

    private function offer(): Offer
    {
        return Offer::create([
            'conversation_id' => $this->conversation->id,
            'player_id' => $this->player->id,
            'from_club_id' => $this->buying->id,
            'amount' => 300_000,
            'status' => Offer::STATUS_SENT,
            'moved_by' => $this->buyer->id,
        ]);
    }

    /** Los avisos que se mandan, como [evento, id de quien movió la oferta]. */
    private function spyEvents(): \ArrayObject
    {
        $events = new \ArrayObject;

        $this->spy(PushNotificationService::class)
            ->shouldReceive('offerMoved')
            ->andReturnUsing(function (Offer $offer, string $event) use ($events): void {
                $events[] = [$event, (int) $offer->moved_by];
            });

        return $events;
    }

    public function test_a_new_offer_a_counter_and_an_acceptance_each_notify(): void
    {
        $events = $this->spyEvents();
        $offers = app(OfferService::class);

        $first = $this->offer();
        $counter = $offers->counter($first, $this->seller, 450_000);
        $offers->accept($counter, $this->buyer);

        $this->assertSame([
            ['sent', $this->buyer->id],
            ['countered', $this->seller->id],
            ['accepted', $this->buyer->id],
        ], $events->getArrayCopy());
    }

    public function test_a_rejection_notifies(): void
    {
        $events = $this->spyEvents();

        app(OfferService::class)->reject($this->offer(), $this->seller);

        $this->assertSame([['sent', $this->buyer->id], ['rejected', $this->seller->id]], $events->getArrayCopy());
    }

    /**
     * Una oferta nueva por un jugador cuya negociación ya acabó es una oferta,
     * no una contraoferta.
     */
    public function test_a_fresh_offer_after_a_rejection_is_not_a_counter(): void
    {
        $events = $this->spyEvents();
        $offers = app(OfferService::class);

        $offers->reject($offers->counter($this->offer(), $this->seller, 450_000), $this->buyer);
        $this->offer();

        $this->assertSame('sent', $events->getArrayCopy()[3][0]);
    }

    /** El aviso va al otro participante, con quién la movió y lo que se ofrece. */
    public function test_the_notice_goes_to_the_other_side_with_the_terms(): void
    {
        $notice = app(PushNotificationService::class)->offerNotice($this->offer(), 'sent');

        $this->assertTrue($notice['to']->is($this->seller));
        $this->assertSame('Nueva oferta de Compra', $notice['title']);
        $this->assertSame('Compra · El Fichaje · 300.000', $notice['body']);
    }

    /**
     * Lo que decide el administrador sobre el trato les llega a LOS DOS
     * técnicos (decisión del propietario, 2026-10-22), cada uno desde su lado:
     * la venta de uno es el fichaje del otro.
     */
    public function test_the_admin_decision_reaches_both_coaches_each_from_their_side(): void
    {
        $admin = User::factory()->create(['name' => 'Presidenta']);
        $offers = app(OfferService::class);

        $offer = $offers->accept($this->offer(), $this->seller);

        app(\App\Services\TransferService::class)->approve($offer->fresh()->transfer, $admin);

        $last = fn (User $coach): ?string => \App\Models\Conversation::between($admin, $coach)
            ->messages()->latest('id')->value('body');

        // Aceptó el vendedor, así que él propuso: para él es una venta.
        $this->assertStringContainsString('Venta aprobada: El Fichaje (Tapajós SC)', $last($this->seller));
        // Y el comprador, que antes no se enteraba, lee su fichaje.
        $this->assertStringContainsString('Fichaje aprobado: El Fichaje (Manaos FC)', $last($this->buyer));
    }
}
