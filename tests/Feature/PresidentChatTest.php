<?php

namespace Tests\Feature;

use App\Livewire\Chat;
use App\Models\Club;
use App\Models\Conversation;
use App\Models\FcPlayer;
use App\Models\Offer;
use App\Models\Player;
use App\Models\Season;
use App\Models\SquadMembership;
use App\Models\Team;
use App\Models\Transfer;
use App\Models\User;
use App\Services\OfferService;
use App\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El chat con el presidente: el técnico pide jugadores del catálogo de EA FC
 * (o vende los suyos), el presidente sólo contraoferta y fija la tensión, y lo
 * acordado aparece en Transfers como traspaso externo.
 */
class PresidentChatTest extends TestCase
{
    use RefreshDatabase;

    private Season $season;

    private Club $club;

    private Team $team;

    private User $coach;

    private User $president;

    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->season = Season::factory()->create(['is_current' => true]);
        $this->club = Club::factory()->create(['initial_balance' => 1_000_000]);
        $this->team = Team::factory()->create(['club_id' => $this->club->id, 'season_id' => $this->season->id]);
        $this->coach = User::factory()->coachOf($this->club)->create();
        $this->president = User::factory()->create();
        $this->conversation = Conversation::between($this->coach, $this->president);
    }

    private function ficha(int $fcId = 555, string $name = 'Ekitike'): FcPlayer
    {
        return FcPlayer::create([
            'fc_id' => $fcId, 'common_name' => $name, 'search_name' => mb_strtolower($name),
            'club' => 'PSG', 'position' => 'ST', 'overall_rating' => 84,
        ]);
    }

    private function askForCatalogue(int $fcId = 555, string $name = 'Ekitike', int $amount = 400_000): Offer
    {
        return Offer::create([
            'conversation_id' => $this->conversation->id,
            'fc_id' => $fcId,
            'external_player' => $name,
            'kind' => Offer::KIND_BUY,
            'from_club_id' => $this->club->id,
            'amount' => $amount,
            'status' => Offer::STATUS_SENT,
            'moved_by' => $this->coach->id,
        ]);
    }

    private function ownPlayer(string $type = SquadMembership::TYPE_OWNED): Player
    {
        $player = Player::factory()->create(['club_id' => $this->club->id, 'name' => 'Mío']);
        SquadMembership::factory()->create(['team_id' => $this->team->id, 'player_id' => $player->id, 'type' => $type]);

        return $player;
    }

    public function test_a_coach_asks_the_president_for_a_catalogue_player_through_the_chat(): void
    {
        $this->ficha();

        Livewire::actingAs($this->coach, 'club')
            ->test(Chat::class)
            ->call('open', $this->conversation->id)
            ->set('offerKind', Offer::KIND_BUY)
            ->set('catalogSearch', 'ekit')
            ->call('selectCatalog', 555)
            ->set('offerAmount', '400000')
            ->call('sendOffer')
            ->assertSet('error', null);

        $offer = Offer::sole();
        $this->assertNull($offer->player_id);
        $this->assertSame(555, (int) $offer->fc_id);
        $this->assertSame('Ekitike', $offer->external_player);
    }

    public function test_the_catalogue_search_hides_players_already_in_a_club(): void
    {
        $this->ficha(555, 'Ekitike');
        $this->ficha(556, 'Ekitiano');
        Player::factory()->create(['club_id' => $this->club->id, 'fc_id' => 556]);

        $names = Livewire::actingAs($this->coach, 'club')
            ->test(Chat::class)
            ->call('open', $this->conversation->id)
            ->set('offerKind', Offer::KIND_BUY)
            ->set('catalogSearch', 'ekiti')
            ->instance()
            ->catalogResults()
            ->map->nombre()
            ->all();

        $this->assertSame(['Ekitike'], $names);
    }

    public function test_a_player_already_in_a_club_cannot_be_asked_of_the_president(): void
    {
        $this->ficha();
        Player::factory()->create(['club_id' => Club::factory()->create()->id, 'fc_id' => 555]);

        $this->expectException(ValidationException::class);

        $this->askForCatalogue();
    }

    public function test_a_player_who_left_the_league_can_be_signed_again(): void
    {
        $this->ficha();
        $former = Player::factory()->create([
            'club_id' => Club::factory()->create()->id, 'fc_id' => 555, 'left_at' => now()->subMonth()->toDateString(),
        ]);

        $offer = $this->askForCatalogue();
        app(OfferService::class)->accept($offer, $this->president);

        $this->assertSame($former->id, Transfer::sole()->player_id);
    }

    public function test_the_president_cannot_open_a_negotiation(): void
    {
        Livewire::actingAs($this->president)
            ->test(Chat::class)
            ->call('open', $this->conversation->id)
            ->assertSet('offerKind', Offer::KIND_BUY);

        $this->assertFalse(
            Livewire::actingAs($this->president)->test(Chat::class)
                ->call('open', $this->conversation->id)->instance()->mayOffer()
        );
    }

    public function test_the_president_counters_and_the_coach_answers(): void
    {
        $offer = $this->askForCatalogue();

        $counter = app(OfferService::class)->counter($offer, $this->president, 600_000);

        $this->assertSame(Offer::KIND_BUY, $counter->kind);
        $this->assertSame(555, (int) $counter->fc_id);
        $this->assertSame(600_000, $counter->amount);
        $this->assertTrue(app(OfferService::class)->mayAnswer($counter, $this->coach));
        $this->assertFalse(app(OfferService::class)->mayAnswer($counter, $this->president));
    }

    public function test_only_the_president_sets_the_tension_and_it_travels_with_the_counter(): void
    {
        $offer = $this->askForCatalogue();

        try {
            app(OfferService::class)->setTension($offer, $this->coach, 50);
            $this->fail('El técnico no fija la tensión.');
        } catch (ValidationException) {
        }

        app(OfferService::class)->setTension($offer, $this->president, 80);
        $counter = app(OfferService::class)->counter($offer->fresh(), $this->president, 500_000);

        $this->assertSame(80, $counter->tension);
    }

    public function test_the_tension_goes_from_1_to_100(): void
    {
        $offer = $this->askForCatalogue();

        $this->expectException(ValidationException::class);

        $offer->update(['tension' => 101]);
    }

    public function test_accepting_a_catalogue_signing_proposes_an_external_transfer(): void
    {
        $this->ficha();
        $offer = $this->askForCatalogue();

        app(OfferService::class)->accept($offer, $this->president);

        $transfer = Transfer::sole();
        $this->assertSame(Transfer::SCOPE_EXTERNAL, $transfer->scope);
        $this->assertSame(Transfer::TYPE_SIGNING, $transfer->type);
        $this->assertSame(Transfer::STATUS_PROPOSED, $transfer->status);
        $this->assertNull($transfer->player_id);
        $this->assertSame('Ekitike', $transfer->external_player);
        $this->assertSame(555, (int) $transfer->fc_id);
        $this->assertSame($this->club->id, $transfer->to_club_id);
        $this->assertSame('PSG', $transfer->external_club);
        $this->assertSame($this->coach->id, $transfer->proposed_by);
        $this->assertSame(0, Player::where('fc_id', 555)->count(), 'La ficha nace al aprobar, no al aceptar.');
    }

    public function test_approving_creates_the_player_from_the_catalogue(): void
    {
        $this->ficha();
        app(OfferService::class)->accept($this->askForCatalogue(), $this->president);

        app(TransferService::class)->approve(Transfer::sole(), $this->president, [
            'player_name' => 'Ekitike', 'position' => 'Forward', 'external_club' => 'PSG', 'fee' => 400_000,
        ]);

        $player = Player::where('fc_id', 555)->sole();
        $this->assertSame($this->club->id, $player->club_id);
        $this->assertSame(1, $player->memberships()->count());
    }

    public function test_a_sale_to_the_president_takes_the_player_out_of_the_squad_and_keeps_the_history(): void
    {
        $player = $this->ownPlayer();
        $offer = Offer::create([
            'conversation_id' => $this->conversation->id,
            'player_id' => $player->id,
            'kind' => Offer::KIND_SELL,
            'from_club_id' => $this->club->id,
            'amount' => 200_000,
            'status' => Offer::STATUS_SENT,
            'moved_by' => $this->coach->id,
        ]);

        app(OfferService::class)->accept($offer, $this->president);
        $transfer = Transfer::sole();

        $this->assertSame(Transfer::TYPE_SALE, $transfer->type);
        $this->assertSame($this->club->id, $transfer->from_club_id);
        $this->assertNull($transfer->external_club);

        app(TransferService::class)->approve($transfer, $this->president);

        $this->assertSame(0, $player->memberships()->count());
        $this->assertSame(1, $player->allMemberships()->count(), 'Su paso por la plantilla se conserva.');
        $this->assertNotNull($player->fresh()->left_at);
    }

    public function test_a_loaned_in_player_cannot_be_sold_to_the_president(): void
    {
        $loaned = $this->ownPlayer(SquadMembership::TYPE_LOAN);

        $this->expectException(ValidationException::class);

        Offer::create([
            'conversation_id' => $this->conversation->id,
            'player_id' => $loaned->id,
            'kind' => Offer::KIND_SELL,
            'from_club_id' => $this->club->id,
            'amount' => 1000,
            'status' => Offer::STATUS_SENT,
            'moved_by' => $this->coach->id,
        ]);
    }

    public function test_a_loaned_player_is_not_offered_in_the_chat_either_way(): void
    {
        $this->ownPlayer();
        $this->ownPlayer(SquadMembership::TYPE_LOAN);

        $rival = Club::factory()->create();
        $rivalTeam = Team::factory()->create(['club_id' => $rival->id, 'season_id' => $this->season->id]);
        $rivalCoach = User::factory()->coachOf($rival)->create();
        $onLoan = Player::factory()->create(['club_id' => $rival->id, 'name' => 'Cedido Rival']);
        SquadMembership::factory()->create(['team_id' => $rivalTeam->id, 'player_id' => $onLoan->id, 'type' => SquadMembership::TYPE_LOAN]);
        $free = Player::factory()->create(['club_id' => $rival->id, 'name' => 'Libre Rival']);
        SquadMembership::factory()->create(['team_id' => $rivalTeam->id, 'player_id' => $free->id]);

        $conversation = Conversation::between($this->coach, $rivalCoach);
        $chat = Livewire::actingAs($this->coach, 'club')->test(Chat::class)->call('open', $conversation->id);

        $chat->set('offerKind', Offer::KIND_SELL);
        $this->assertSame(['Mío'], array_values($chat->instance()->offerableOptions()));

        $chat->set('offerKind', Offer::KIND_BUY);
        $this->assertSame(['Libre Rival'], array_values($chat->instance()->offerableOptions()));
    }

    public function test_a_loaned_player_cannot_be_bought_from_another_coach(): void
    {
        $rival = Club::factory()->create();
        $rivalTeam = Team::factory()->create(['club_id' => $rival->id, 'season_id' => $this->season->id]);
        $rivalCoach = User::factory()->coachOf($rival)->create();
        $onLoan = Player::factory()->create(['club_id' => $rival->id]);
        SquadMembership::factory()->create(['team_id' => $rivalTeam->id, 'player_id' => $onLoan->id, 'type' => SquadMembership::TYPE_LOAN]);

        $this->expectException(ValidationException::class);

        Offer::create([
            'conversation_id' => Conversation::between($this->coach, $rivalCoach)->id,
            'player_id' => $onLoan->id,
            'kind' => Offer::KIND_BUY,
            'from_club_id' => $this->club->id,
            'amount' => 1000,
            'status' => Offer::STATUS_SENT,
            'moved_by' => $this->coach->id,
        ]);
    }

    public function test_the_loan_block_ends_with_the_loan(): void
    {
        $player = $this->ownPlayer(SquadMembership::TYPE_LOAN);
        $this->assertTrue($player->isOnLoan());

        $player->memberships()->first()->close();

        $this->assertFalse($player->fresh()->isOnLoan());
    }

    public function test_a_loan_counter_keeps_its_kind_and_changes_the_term(): void
    {
        $this->ficha();
        $offer = Offer::create([
            'conversation_id' => $this->conversation->id,
            'fc_id' => 555,
            'external_player' => 'Ekitike',
            'kind' => Offer::KIND_LOAN_IN,
            'from_club_id' => $this->club->id,
            'loan_term' => Transfer::TERM_SIX_MONTHS,
            'status' => Offer::STATUS_SENT,
            'moved_by' => $this->coach->id,
        ]);

        $counter = app(OfferService::class)->counter($offer, $this->president, null, Transfer::TERM_ONE_YEAR);

        $this->assertSame(Offer::KIND_LOAN_IN, $counter->kind);
        $this->assertSame(Transfer::TERM_ONE_YEAR, $counter->loan_term);
    }

    public function test_an_offer_the_buyer_cannot_pay_is_refused_at_the_start(): void
    {
        $this->ficha();

        try {
            $this->askForCatalogue(555, 'Ekitike', 5_000_000);
            $this->fail('Con 1.000.000 no se oferta 5.000.000.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('no tiene saldo suficiente', $exception->validator->errors()->first());
        }

        $this->assertSame(0, Offer::count());
    }

    public function test_accepting_is_refused_if_the_balance_dropped_in_the_meantime(): void
    {
        $this->ficha();
        $offer = $this->askForCatalogue(555, 'Ekitike', 800_000);

        $this->club->update(['initial_balance' => 100_000]);

        $this->expectException(ValidationException::class);

        app(OfferService::class)->accept($offer, $this->president);
    }

    public function test_approving_a_transfer_the_club_cannot_pay_changes_nothing(): void
    {
        $this->ficha();
        app(OfferService::class)->accept($this->askForCatalogue(), $this->president);

        try {
            app(TransferService::class)->approve(Transfer::sole(), $this->president, [
                'player_name' => 'Ekitike', 'position' => 'Forward', 'external_club' => 'PSG', 'fee' => 9_000_000,
            ]);
            $this->fail('Se aprobó sin saldo.');
        } catch (ValidationException) {
        }

        $this->assertSame(Transfer::STATUS_PROPOSED, Transfer::sole()->status);
        $this->assertSame(0, Player::where('fc_id', 555)->count(), 'Ni siquiera nace la ficha.');
    }

    public function test_a_sale_to_the_president_does_not_ask_the_club_for_money(): void
    {
        $this->club->update(['initial_balance' => 0]);
        $player = $this->ownPlayer();

        $offer = Offer::create([
            'conversation_id' => $this->conversation->id,
            'player_id' => $player->id,
            'kind' => Offer::KIND_SELL,
            'from_club_id' => $this->club->id,
            'amount' => 900_000,
            'status' => Offer::STATUS_SENT,
            'moved_by' => $this->coach->id,
        ]);

        app(OfferService::class)->accept($offer, $this->president);

        $this->assertSame(1, Transfer::count());
    }
}
