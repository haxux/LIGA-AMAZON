<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\PushSubscription;
use App\Models\Season;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushSubscriptionControllerTest extends TestCase
{
    use RefreshDatabase;

    private function payload(string $topic, string $endpoint = 'https://push.example/abc'): array
    {
        return [
            'endpoint' => $endpoint,
            'keys' => ['p256dh' => 'public-key', 'auth' => 'auth-token'],
            'topic' => $topic,
        ];
    }

    public function test_anyone_can_subscribe_to_match_notifications_without_a_session(): void
    {
        $this->postJson('/push/subscribe', $this->payload(PushSubscription::TOPIC_MATCHES))
            ->assertOk()
            ->assertJson(['topics' => [PushSubscription::TOPIC_MATCHES]]);

        $this->assertDatabaseCount('push_subscriptions', 1);
        $this->assertNull(PushSubscription::query()->sole()->user_id);
    }

    public function test_subscribing_to_chat_without_a_session_is_rejected(): void
    {
        $this->postJson('/push/subscribe', $this->payload(PushSubscription::TOPIC_CHAT))
            ->assertStatus(401);

        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_a_coach_can_subscribe_to_chat_notifications(): void
    {
        $club = Club::factory()->create();
        $coach = User::factory()->coachOf($club)->create();

        $this->actingAs($coach, 'club')
            ->postJson('/push/subscribe', $this->payload(PushSubscription::TOPIC_CHAT))
            ->assertOk();

        $subscription = PushSubscription::query()->sole();
        $this->assertSame($coach->id, $subscription->user_id);
        $this->assertTrue($subscription->hasTopic(PushSubscription::TOPIC_CHAT));
    }

    public function test_the_same_browser_can_hold_both_topics_on_one_endpoint(): void
    {
        $club = Club::factory()->create();
        $coach = User::factory()->coachOf($club)->create();
        $endpoint = 'https://push.example/same-browser';

        $this->postJson('/push/subscribe', $this->payload(PushSubscription::TOPIC_MATCHES, $endpoint))->assertOk();
        $this->actingAs($coach, 'club')
            ->postJson('/push/subscribe', $this->payload(PushSubscription::TOPIC_CHAT, $endpoint))
            ->assertOk();

        $this->assertDatabaseCount('push_subscriptions', 1);
        $subscription = PushSubscription::query()->sole();
        $this->assertTrue($subscription->hasTopic(PushSubscription::TOPIC_MATCHES));
        $this->assertTrue($subscription->hasTopic(PushSubscription::TOPIC_CHAT));
    }

    public function test_unsubscribing_from_one_topic_keeps_the_other(): void
    {
        $endpoint = 'https://push.example/two-topics';
        $club = Club::factory()->create();
        $coach = User::factory()->coachOf($club)->create();

        $this->postJson('/push/subscribe', $this->payload(PushSubscription::TOPIC_MATCHES, $endpoint))->assertOk();
        $this->actingAs($coach, 'club')
            ->postJson('/push/subscribe', $this->payload(PushSubscription::TOPIC_CHAT, $endpoint))
            ->assertOk();

        $this->deleteJson('/push/subscribe', ['endpoint' => $endpoint, 'topic' => PushSubscription::TOPIC_CHAT])
            ->assertOk();

        $subscription = PushSubscription::query()->sole();
        $this->assertTrue($subscription->hasTopic(PushSubscription::TOPIC_MATCHES));
        $this->assertFalse($subscription->hasTopic(PushSubscription::TOPIC_CHAT));
    }

    public function test_unsubscribing_from_the_last_topic_deletes_the_row(): void
    {
        $endpoint = 'https://push.example/only-topic';
        $this->postJson('/push/subscribe', $this->payload(PushSubscription::TOPIC_MATCHES, $endpoint))->assertOk();

        $this->deleteJson('/push/subscribe', ['endpoint' => $endpoint, 'topic' => PushSubscription::TOPIC_MATCHES])
            ->assertOk();

        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    /**
     * El permiso para los avisos de chat se pedía SÓLO dentro de los dos
     * paneles de Filament, y el chat se mudó al sitio público hace tiempo. Un
     * técnico que entrara por el sitio —que es por donde se entra— no llegaba a
     * suscribirse nunca, así que nadie le avisaba de un mensaje aunque todo lo
     * demás estuviera puesto. En producción había dos suscripciones, las dos
     * sólo de partidos.
     */
    public function test_a_logged_in_coach_is_asked_for_chat_notifications_on_the_site(): void
    {
        Season::factory()->create(['is_current' => true]);
        $coach = User::factory()->coachOf(Club::factory()->create())->create();

        $this->actingAs($coach, 'club')
            ->get(route('site.standings'))
            ->assertOk()
            ->assertSee('autoPromptAll', false)
            ->assertSee("['matches', 'chat']", false);
    }

    /**
     * A quien no ha iniciado sesión no se le pide: el servidor rechazaría esa
     * alta —el chat es de los que entran— y preguntar por nada sólo gasta el
     * único permiso que el navegador concede.
     */
    public function test_an_anonymous_visitor_is_only_asked_about_matches(): void
    {
        Season::factory()->create(['is_current' => true]);

        $this->get(route('site.standings'))
            ->assertOk()
            ->assertSee("['matches']", false)
            ->assertDontSee("'chat'", false);
    }

    /**
     * Y la pantalla del chat, que es donde más falta hace.
     */
    public function test_the_chat_page_also_asks_a_coach(): void
    {
        $coach = User::factory()->coachOf(Club::factory()->create())->create();

        $this->actingAs($coach, 'club')
            ->get(route('site.chat'))
            ->assertOk()
            ->assertSee("['matches', 'chat']", false);
    }

    /**
     * El chat dice si le van a avisar o no, y ofrece activarlo.
     *
     * Sin esto no hay forma de diagnosticarlo: el permiso vive en el navegador
     * y la suscripción en el servidor, y cuando falta una de las dos la página
     * se queda callada. Llevaba meses sin notificar a nadie y nada lo decía.
     */
    public function test_the_chat_page_shows_whether_notifications_are_on(): void
    {
        $coach = User::factory()->coachOf(Club::factory()->create())->create();

        $this->actingAs($coach, 'club')
            ->get(route('site.chat'))
            ->assertOk()
            ->assertSee('data-push-toggle', false)
            ->assertSee('Activar avisos');
    }
}
