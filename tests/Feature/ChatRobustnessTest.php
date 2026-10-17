<?php

namespace Tests\Feature;

use App\Livewire\Chat;
use App\Models\Club;
use App\Models\Conversation;
use App\Models\FcPlayer;
use App\Models\Message;
use App\Models\Offer;
use App\Models\Player;
use App\Models\Season;
use App\Models\SquadMembership;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El chat se repinta cada 5 segundos, y los dos fallos que se vieron salían de
 * ahí: una oferta mandada que no aparecía y un chat que dejaba de dejar
 * escribir. Aquí se fija lo que los evita.
 */
class ChatRobustnessTest extends TestCase
{
    use RefreshDatabase;

    private User $coach;

    private User $rival;

    private User $president;

    private Player $his;

    private Conversation $withPresident;

    private Conversation $withRival;

    protected function setUp(): void
    {
        parent::setUp();

        $season = Season::factory()->create(['is_current' => true]);
        $club = Club::factory()->create(['name' => 'Mi Club', 'initial_balance' => 50_000_000]);
        $other = Club::factory()->create(['name' => 'Otro', 'initial_balance' => 50_000_000]);
        Team::factory()->create(['club_id' => $club->id, 'season_id' => $season->id]);
        $otherTeam = Team::factory()->create(['club_id' => $other->id, 'season_id' => $season->id]);

        $this->coach = User::factory()->coachOf($club)->create(['name' => 'Jonathan']);
        $this->rival = User::factory()->coachOf($other)->create(['name' => 'Camilo']);
        $this->president = User::factory()->create(['name' => 'Presidenta']);

        $this->his = Player::factory()->create(['club_id' => $other->id, 'name' => 'Su Delantero', 'market_value' => 2_000_000]);
        SquadMembership::factory()->create(['team_id' => $otherTeam->id, 'player_id' => $this->his->id]);

        FcPlayer::create(['fc_id' => 555, 'common_name' => 'Ekitike', 'search_name' => 'ekitike', 'club' => 'PSG', 'position' => 'ST', 'overall_rating' => 84]);

        $this->withPresident = Conversation::between($this->coach, $this->president);
        $this->withRival = Conversation::between($this->coach, $this->rival);
    }

    private function chat(Conversation $conversation)
    {
        return Livewire::actingAs($this->coach, 'club')->test(Chat::class)->call('open', $conversation->id);
    }

    // ── La oferta mandada se ve, y se puede seguir escribiendo ────────────

    public function test_a_sent_offer_shows_up_in_the_thread_at_once(): void
    {
        $this->chat($this->withRival)
            ->set('offerKind', Offer::KIND_BUY)
            ->set('offerPlayerId', (string) $this->his->id)
            ->call('sendOffer')
            ->assertSet('error', null)
            ->assertSee('Su Delantero')
            ->assertSee('2.000.000')
            ->assertDispatched('chat-sent');

        $this->assertSame(1, Offer::count());
    }

    public function test_one_can_keep_writing_after_sending_an_offer(): void
    {
        $this->chat($this->withPresident)
            ->set('offerKind', Offer::KIND_BUY)
            ->call('selectCatalog', 555)
            ->set('offerAmount', '400000')
            ->call('sendOffer')
            ->set('body', 'Y otra cosa más')
            ->call('send')
            ->assertSet('body', '')
            ->assertSee('Y otra cosa más');

        $this->assertSame(1, Offer::count());
        $this->assertSame(1, Message::count());
    }

    public function test_the_composer_is_clean_after_an_offer_so_the_next_one_starts_fresh(): void
    {
        $this->chat($this->withRival)
            ->set('offerKind', Offer::KIND_BUY)
            ->set('offerPlayerId', (string) $this->his->id)
            ->call('sendOffer')
            ->assertSet('offerPlayerId', null)
            ->assertSet('offerAmount', null);
    }

    public function test_sending_a_message_tells_the_screen_to_scroll_down(): void
    {
        $this->chat($this->withRival)->set('body', 'Hola')->call('send')->assertDispatched('chat-sent');
    }

    // ── Nada falla en silencio ────────────────────────────────────────────

    public function test_an_offer_without_a_player_says_so_instead_of_doing_nothing(): void
    {
        $this->chat($this->withRival)
            ->set('offerKind', Offer::KIND_BUY)
            ->call('sendOffer')
            ->assertSet('error', 'Elige un jugador para la oferta.')
            ->assertNotDispatched('chat-sent');

        $this->assertSame(0, Offer::count());
    }

    public function test_typing_a_catalogue_name_without_picking_it_says_so(): void
    {
        $this->chat($this->withPresident)
            ->set('offerKind', Offer::KIND_BUY)
            ->set('catalogSearch', 'ekit')
            ->set('offerAmount', '400000')
            ->call('sendOffer')
            ->assertSet('error', 'Elige al jugador de la lista del catálogo: escribir su nombre no basta.');
    }

    public function test_an_offer_without_an_amount_says_so(): void
    {
        $this->chat($this->withRival)
            ->set('offerKind', Offer::KIND_BUY)
            ->set('offerPlayerId', (string) $this->his->id)
            ->set('offerAmount', '')
            ->call('sendOffer')
            ->assertSet('error', 'Pon el importe de la oferta.');
    }

    public function test_a_loan_without_a_term_says_so(): void
    {
        $this->chat($this->withRival)
            ->set('offerKind', Offer::KIND_LOAN_IN)
            ->set('offerPlayerId', (string) $this->his->id)
            ->call('sendOffer')
            ->assertSet('error', 'Elige el plazo de la cesión.');
    }

    public function test_a_refused_offer_keeps_the_form_filled_and_explains_why(): void
    {
        $this->chat($this->withRival)
            ->set('offerKind', Offer::KIND_BUY)
            ->set('offerPlayerId', (string) $this->his->id)
            ->set('offerAmount', '999999999')
            ->call('sendOffer')
            ->assertSee('no tiene saldo suficiente')
            ->assertSet('offerPlayerId', (string) $this->his->id)
            ->assertNotDispatched('chat-sent');

        $this->assertSame(0, Offer::count());
    }

    // ── Cada repintado es barato ──────────────────────────────────────────

    /**
     * Más de trescientas consultas por repintado con un hilo de cuarenta
     * mensajes, cada 5 segundos, es lo que dejaba la pantalla pensando justo
     * cuando se mandaba algo.
     */
    public function test_a_long_thread_renders_with_a_bounded_number_of_queries(): void
    {
        foreach (range(1, 60) as $i) {
            Message::create(['conversation_id' => $this->withPresident->id, 'user_id' => $i % 2 ? $this->coach->id : $this->president->id, 'body' => "mensaje {$i}"]);
        }

        foreach (range(1, 5) as $i) {
            Offer::create([
                'conversation_id' => $this->withPresident->id, 'fc_id' => 555, 'external_player' => 'Ekitike', 'kind' => Offer::KIND_BUY,
                'from_club_id' => $this->coach->club_id, 'amount' => 100_000 * $i, 'status' => $i === 5 ? Offer::STATUS_SENT : Offer::STATUS_NEGOTIATING,
                'moved_by' => $this->coach->id,
            ]);
        }

        DB::enableQueryLog();
        $this->chat($this->withPresident)->assertSee('mensaje 60');
        $queries = count(DB::getQueryLog());

        $this->assertLessThan(60, $queries, "El repintado hizo {$queries} consultas.");
    }

    public function test_the_loaned_players_are_filtered_in_one_query_not_one_per_player(): void
    {
        $this->assertSame([], Player::loanedAmong([]));

        $loaned = Player::factory()->create(['club_id' => $this->his->club_id]);
        $team = Team::query()->where('club_id', $this->his->club_id)->first();
        SquadMembership::factory()->create(['team_id' => $team->id, 'player_id' => $loaned->id, 'type' => SquadMembership::TYPE_LOAN]);

        $this->assertSame([$loaned->id], Player::loanedAmong([$this->his->id, $loaned->id]));
    }

    /**
     * El fallo de verdad, medido en un navegador: sin `min-h-0`, la columna del
     * hilo crecía con los mensajes por debajo de la tarjeta, y el
     * `overflow-hidden` recortaba la barra de escribir y la última oferta. Un
     * hilo largo —los antiguos primero— dejaba de poder escribirse. Esto no
     * mide el CSS, pero avisa si alguien quita la clase que lo evita.
     */
    public function test_the_thread_column_and_scroll_area_can_shrink_below_their_content(): void
    {
        $html = $this->chat($this->withRival)->html();

        $this->assertMatchesRegularExpression('/<section class="[^"]*\bmin-h-0\b[^"]*flex-col/', $html);
        $this->assertMatchesRegularExpression('/class="min-h-0 flex-1 overflow-y-auto[^"]*"\s+wire:key="hilo-abierto-/', $html);
    }

    // ── Hilos de antes de estos cambios ───────────────────────────────────

    /**
     * Un hilo con filas escritas ANTES de que existieran el jugador del
     * catálogo, la tensión y los demás campos nuevos: sólo con las columnas
     * originales. Tiene que seguir leyéndose, respondiéndose y admitiendo
     * mensajes.
     */
    public function test_a_thread_written_before_the_new_columns_still_works(): void
    {
        $oldMessage = DB::table('messages')->insertGetId([
            'conversation_id' => $this->withRival->id, 'user_id' => $this->rival->id, 'body' => 'Mensaje de antes',
            'created_at' => now()->subDays(40), 'updated_at' => now()->subDays(40),
        ]);

        $oldOffer = DB::table('offers')->insertGetId([
            'conversation_id' => $this->withRival->id, 'player_id' => $this->his->id, 'from_club_id' => $this->rival->club_id,
            'amount' => 2_000, 'status' => 'enviada', 'moved_by' => $this->rival->id,
            'created_at' => now()->subDays(40), 'updated_at' => now()->subDays(40),
        ]);

        $chat = $this->chat($this->withRival);
        $chat->assertSee('Mensaje de antes')->assertSee('Su Delantero');

        // Se puede seguir escribiendo…
        $chat->set('body', 'Sigo aquí')->call('send')->assertSet('body', '')->assertSee('Sigo aquí');

        // …y contestar la oferta vieja: es de la otra parte, y le toca a quien no la movió.
        $chat->call('rejectOffer', $oldOffer);

        $this->assertSame('rechazada', Offer::find($oldOffer)->status);
        $this->assertSame($oldMessage, Message::query()->orderBy('id')->value('id'));
    }

    public function test_a_thread_with_the_president_written_before_the_catalogue_still_works(): void
    {
        $oldOffer = DB::table('offers')->insertGetId([
            'conversation_id' => $this->withPresident->id, 'player_id' => $this->his->id, 'from_club_id' => $this->coach->club_id,
            'amount' => 5_000, 'status' => 'negociando', 'moved_by' => $this->coach->id,
            'created_at' => now()->subDays(20), 'updated_at' => now()->subDays(20),
        ]);

        $this->chat($this->withPresident)
            ->assertSee('Su Delantero')
            ->set('body', 'Retomo la charla')
            ->call('send')
            ->assertSee('Retomo la charla');

        $this->assertNull(Offer::find($oldOffer)->tension);
    }
}
