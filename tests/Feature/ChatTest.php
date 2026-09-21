<?php

namespace Tests\Feature;

use App\Livewire\Chat;
use App\Models\Club;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Offer;
use App\Models\Player;
use App\Models\Season;
use App\Models\SquadMembership;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El chat: uno a uno, por sondeo, con contador de no leídos.
 *
 * Desde la corrección posterior a la Fase 13 vive en el sitio público y no en
 * los paneles, así que una misma pantalla sirve a técnicos y presidentes: el
 * componente resuelve al usuario por los dos guards.
 */
class ChatTest extends TestCase
{
    use RefreshDatabase;

    private Club $clubA;

    private Club $clubB;

    private User $coachA;

    private User $coachB;

    protected function setUp(): void
    {
        parent::setUp();

        Season::factory()->create(['is_current' => true, 'name' => '2026/27']);
        $this->clubA = Club::factory()->create(['name' => 'Manaos FC']);
        $this->clubB = Club::factory()->create(['name' => 'Tapajós SC']);
        $this->coachA = User::factory()->coachOf($this->clubA)->create(['name' => 'Técnico A']);
        $this->coachB = User::factory()->coachOf($this->clubB)->create(['name' => 'Técnico B']);
    }

    /**
     * Por el guard `club`, que es por donde entra un técnico; el componente
     * público resuelve al usuario mirando ese y, si no, el del administrador.
     */
    private function asCoach(User $coach): void
    {
        $this->actingAs($coach, 'club');
    }

    /**
     * Una conversación por pareja: dos hilos entre los mismos dos partirían la
     * historia y dejarían el contador de no leídos sin significado.
     */
    public function test_opening_a_chat_twice_reuses_the_same_thread(): void
    {
        $first = Conversation::between($this->coachA, $this->coachB);
        $second = Conversation::between($this->coachB, $this->coachA);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, Conversation::query()->count());
    }

    public function test_a_coach_sends_a_message_and_the_other_reads_it(): void
    {
        $this->asCoach($this->coachA);

        Livewire::test(Chat::class)
            ->call('openWith', $this->coachB->id)
            ->set('body', 'Hola, ¿hablamos del delantero?')
            ->call('send')
            ->assertSet('body', '');

        $this->assertDatabaseHas('messages', [
            'user_id' => $this->coachA->id,
            'body' => 'Hola, ¿hablamos del delantero?',
        ]);

        $conversation = Conversation::query()->with('participants')->first();

        $this->assertSame(1, $conversation->unreadFor($this->coachB));
        $this->assertSame(0, $conversation->unreadFor($this->coachA));
    }

    public function test_opening_the_thread_clears_the_unread_count(): void
    {
        $conversation = Conversation::between($this->coachA, $this->coachB);
        Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $this->coachA->id,
            'body' => 'Buenas',
        ]);

        $this->asCoach($this->coachB);

        Livewire::test(Chat::class)->call('open', $conversation->id);

        $this->assertSame(0, $conversation->fresh()->load('participants')->unreadFor($this->coachB));
    }

    public function test_a_third_person_cannot_open_someone_elses_thread(): void
    {
        $conversation = Conversation::between($this->coachA, $this->coachB);
        $outsider = User::factory()->coachOf(Club::factory()->create(['name' => 'Club Tercero']))->create();

        $this->asCoach($outsider);

        $page = Livewire::test(Chat::class)->call('open', $conversation->id)->instance();

        $this->assertNull($page->conversation());
    }

    public function test_the_coach_lists_only_their_own_conversations(): void
    {
        $mine = Conversation::between($this->coachA, $this->coachB);
        $outsider = User::factory()->coachOf(Club::factory()->create(['name' => 'Club Cuarto']))->create();
        $theirs = Conversation::between($this->coachB, $outsider);

        $this->asCoach($this->coachA);

        $conversations = Livewire::test(Chat::class)->instance()->conversations();

        $this->assertTrue($conversations->contains(fn (Conversation $conversation) => $conversation->is($mine)));
        $this->assertFalse($conversations->contains(fn (Conversation $conversation) => $conversation->is($theirs)));
    }

    // ── La página que lo monta ────────────────────────────────────────────

    public function test_the_chat_page_needs_a_session(): void
    {
        $this->get(route('site.chat'))->assertRedirect(route('filament.club.auth.login'));
    }

    public function test_a_coach_and_a_president_both_open_the_page(): void
    {
        $this->actingAs($this->coachA, 'club')->get(route('site.chat'))->assertOk()->assertSee('Chats');

        $this->actingAs(User::factory()->create(), 'web')->get(route('site.chat'))->assertOk();
    }

    public function test_the_panels_no_longer_serve_a_chat(): void
    {
        $this->actingAs($this->coachA, 'club')->get('/club/chat')->assertNotFound();
        $this->actingAs(User::factory()->create(), 'web')->get('/admin/chat')->assertNotFound();
    }

    /**
     * El aviso del chat es el contador: no hay correos ni websockets, así que
     * los no leídos viajan en la cabecera de cualquier página del sitio.
     */
    public function test_the_header_carries_the_unread_count(): void
    {
        $conversation = Conversation::between($this->coachA, $this->coachB);

        foreach (['Uno', 'Dos'] as $body) {
            Message::create([
                'conversation_id' => $conversation->id,
                'user_id' => $this->coachA->id,
                'body' => $body,
            ]);
        }

        $this->actingAs($this->coachB, 'club')
            ->get(route('site.standings'))
            ->assertOk()
            ->assertSeeInOrder(['Chat', '2']);
    }

    /**
     * El escudo del club hace de avatar del técnico —no hay fotos de personas
     * en este bloque—, y el presidente, que no dirige ninguno, se queda con sus
     * iniciales.
     */
    public function test_a_coach_is_shown_by_their_clubs_crest(): void
    {
        $this->clubB->update(['crest_path' => 'crests/tapajos.png']);

        $this->asCoach($this->coachA);

        $crest = Storage::disk(config('filesystems.uploads'))->url('crests/tapajos.png');

        Livewire::test(Chat::class)
            ->call('openWith', $this->coachB->id)
            ->assertSee($crest, escape: false);
    }

    /**
     * Los administradores se llaman presidentes en el chat (decisión cerrada).
     */
    public function test_an_administrator_is_a_presidente_in_the_chat(): void
    {
        $admin = User::factory()->create(['name' => 'Jefa Máxima']);

        $this->asCoach($this->coachA);
        $page = Livewire::test(Chat::class)->instance();

        $this->assertSame('Jefa Máxima · Presidente', $page->displayName($admin));
        $this->assertSame('Técnico B · Tapajós SC', $page->displayName($this->coachB));
    }

    public function test_a_coach_and_a_president_can_talk(): void
    {
        $admin = User::factory()->create(['name' => 'Presidenta']);
        $this->actingAs($admin);

        Livewire::test(Chat::class)
            ->call('openWith', $this->coachA->id)
            ->set('body', 'Enhorabuena por el partido')
            ->call('send');

        $this->assertDatabaseHas('messages', ['user_id' => $admin->id]);
        $this->assertSame(1, Conversation::query()->count());
    }

    /**
     * Un presidente habla con los técnicos; entre presidentes no hace falta un
     * chat, comparten panel.
     */
    public function test_a_president_only_lists_coaches_as_contacts(): void
    {
        $admin = User::factory()->create();
        $otherAdmin = User::factory()->create();
        $this->actingAs($admin);

        $contacts = Livewire::test(Chat::class)->instance()->contacts();

        $this->assertTrue($contacts->contains(fn (User $user) => $user->is($this->coachA)));
        $this->assertFalse($contacts->contains(fn (User $user) => $user->is($otherAdmin)));
    }

    public function test_an_empty_message_is_not_sent(): void
    {
        $this->asCoach($this->coachA);

        Livewire::test(Chat::class)
            ->call('openWith', $this->coachB->id)
            ->set('body', '   ')
            ->call('send');

        $this->assertSame(0, Message::query()->count());
    }

    public function test_the_unread_badge_counts_what_is_waiting(): void
    {
        $conversation = Conversation::between($this->coachA, $this->coachB);

        foreach (['Uno', 'Dos'] as $body) {
            Message::create([
                'conversation_id' => $conversation->id,
                'user_id' => $this->coachA->id,
                'body' => $body,
            ]);
        }

        $this->asCoach($this->coachB);

        $this->assertSame('2', (string) Conversation::unreadTotalFor($this->coachB));
    }

    /**
     * REGRESIÓN: el hilo se pintaba bien con una entrada y reventaba con dos.
     * El comparador del multiorden de `Collection` sólo se ejecuta cuando hay
     * algo que comparar, así que ningún test lo tocaba — y en el navegador
     * fallaba cada cinco segundos, uno por sondeo.
     */
    public function test_a_thread_with_several_entries_renders(): void
    {
        $conversation = Conversation::between($this->coachA, $this->coachB);

        foreach (['Primero', 'Segundo', 'Tercero'] as $body) {
            Message::create([
                'conversation_id' => $conversation->id,
                'user_id' => $this->coachA->id,
                'body' => $body,
            ]);
        }

        $theirs = Player::factory()->create(['club_id' => $this->clubB->id, 'name' => 'Su Delantero']);
        Offer::create([
            'conversation_id' => $conversation->id,
            'player_id' => $theirs->id,
            'from_club_id' => $this->clubA->id,
            'amount' => 150_000,
            'status' => Offer::STATUS_SENT,
            'moved_by' => $this->coachA->id,
        ]);

        $this->asCoach($this->coachA);

        Livewire::test(Chat::class)
            ->call('open', $conversation->id)
            ->assertOk()
            ->assertSeeInOrder(['Primero', 'Segundo', 'Tercero'])
            ->assertSee('Su Delantero')
            ->assertSee('150.000');
    }

    /**
     * El mismo hilo leído por el presidente: es la misma pantalla para los dos,
     * y el fallo del multiorden se vio precisamente ahí.
     */
    public function test_a_thread_renders_for_a_president_too(): void
    {
        $admin = User::factory()->create(['name' => 'Presidenta']);
        $conversation = Conversation::between($admin, $this->coachA);

        // Sin acentos a propósito: la respuesta de Livewire viaja como JSON y
        // ahí una "é" llega escapada, así que assertSee no la encontraría.
        foreach (['Hola presidenta', 'Segundo mensaje'] as $body) {
            Message::create([
                'conversation_id' => $conversation->id,
                'user_id' => $this->coachA->id,
                'body' => $body,
            ]);
        }

        $this->actingAs($admin);

        Livewire::test(Chat::class)
            ->call('open', $conversation->id)
            ->assertOk()
            ->assertSeeInOrder(['Hola presidenta', 'Segundo mensaje']);
    }

    /**
     * Las opciones para ofertar son la plantilla del interlocutor en la
     * temporada vigente: no se oferta por un jugador propio.
     */
    public function test_the_offer_options_are_the_other_clubs_squad(): void
    {
        $season = Season::query()->where('is_current', true)->first();
        $teamB = Team::factory()->create(['club_id' => $this->clubB->id, 'season_id' => $season->id]);
        $theirs = Player::factory()->create(['club_id' => $this->clubB->id, 'name' => 'Su Delantero']);
        SquadMembership::factory()->create(['team_id' => $teamB->id, 'player_id' => $theirs->id, 'shirt_number' => 9]);

        $teamA = Team::factory()->create(['club_id' => $this->clubA->id, 'season_id' => $season->id]);
        $mine = Player::factory()->create(['club_id' => $this->clubA->id, 'name' => 'Mi Delantero']);
        SquadMembership::factory()->create(['team_id' => $teamA->id, 'player_id' => $mine->id, 'shirt_number' => 9]);

        $this->asCoach($this->coachA);

        $options = Livewire::test(Chat::class)
            ->call('openWith', $this->coachB->id)
            ->instance()
            ->offerableOptions();

        $this->assertSame([$theirs->id => 'Su Delantero'], $options);
    }

    /**
     * Ofertar exige un club que compre, y un presidente no dirige ninguno.
     */
    public function test_a_president_cannot_offer(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $page = Livewire::test(Chat::class)->call('openWith', $this->coachA->id)->instance();

        $this->assertFalse($page->mayOffer());
        $this->assertSame([], $page->offerableOptions());
    }
}
