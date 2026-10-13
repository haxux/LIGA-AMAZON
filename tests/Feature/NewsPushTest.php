<?php

namespace Tests\Feature;

use App\Models\News;
use App\Models\PushSubscription;
use App\Models\Season;
use App\Services\PushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Al publicar una noticia se avisa por push, con sesión o sin ella (decisión
 * del propietario, 2026-10-13).
 *
 * Lo que de verdad hay que vigilar aquí es que ocurra UNA vez: la anuncian dos
 * caminos —guardarla ya publicada y el barrido del cron para las programadas—
 * y editar una vieja no puede volver a avisar a toda la liga.
 */
class NewsPushTest extends TestCase
{
    use RefreshDatabase;

    private function service(): PushNotificationService
    {
        return app(PushNotificationService::class);
    }

    public function test_a_published_item_is_announced_once_and_marked(): void
    {
        $item = News::factory()->create(['published_at' => now()->subMinute()]);

        // Su observador ya lo anunció al guardarla.
        $this->assertNotNull($item->fresh()->notified_at, 'Queda marcada al publicarse');

        // Y no queda nada pendiente: un segundo barrido no anuncia nada.
        $this->assertSame(0, $this->service()->publishedNews());
    }

    public function test_a_draft_is_not_announced(): void
    {
        $item = News::factory()->create(['published_at' => null]);

        $this->assertNull($item->fresh()->notified_at);
        $this->assertSame(0, $this->service()->publishedNews());
    }

    /**
     * Una programada no se anuncia hasta que llega su fecha. Hasta entonces no
     * es pública, y avisar de algo que al pulsarlo no se puede leer es peor que
     * no avisar.
     */
    public function test_a_scheduled_item_waits_for_its_date(): void
    {
        $item = News::factory()->create(['published_at' => now()->addDay()]);

        $this->assertNull($item->fresh()->notified_at);
        $this->assertSame(0, $this->service()->publishedNews());

        $this->travel(2)->days();

        $this->assertSame(1, $this->service()->publishedNews(), 'Llegada su fecha, el barrido la anuncia');
        $this->assertNotNull($item->fresh()->notified_at);
    }

    /**
     * El caso que la marca existe para impedir: corregir una errata en una
     * noticia de hace un mes no vuelve a avisar a toda la liga.
     */
    public function test_editing_an_old_item_does_not_announce_it_again(): void
    {
        $item = News::factory()->create(['published_at' => now()->subMonth()]);
        $cuando = $item->fresh()->notified_at;

        $item->update(['title' => 'Título corregido']);

        $this->assertSame(0, $this->service()->publishedNews());
        $this->assertEquals($cuando, $item->fresh()->notified_at, 'La marca no se mueve');
    }

    /**
     * Un borrador que se publica al editarlo es tan nuevo para el lector como
     * uno que nace publicado, así que también se anuncia.
     */
    public function test_publishing_a_draft_later_announces_it(): void
    {
        $item = News::factory()->create(['published_at' => null]);
        $this->assertNull($item->fresh()->notified_at);

        $item->update(['published_at' => now()->subMinute()]);

        $this->assertNotNull($item->fresh()->notified_at);
    }

    /**
     * El tema es el de noticias y no pide sesión: a una noticia la lee
     * cualquiera, así que avisar de ella tampoco debería exigir entrar.
     */
    public function test_anyone_can_subscribe_to_news_without_a_session(): void
    {
        $this->postJson('/push/subscribe', [
            'endpoint' => 'https://example.test/endpoint-noticias',
            'keys' => ['p256dh' => 'clave', 'auth' => 'token'],
            'topic' => PushSubscription::TOPIC_NEWS,
        ])->assertOk();

        $sub = PushSubscription::query()->where('endpoint', 'https://example.test/endpoint-noticias')->first();

        $this->assertNotNull($sub);
        $this->assertTrue($sub->hasTopic(PushSubscription::TOPIC_NEWS));
        $this->assertNull($sub->user_id, 'Sin sesión, y aun así vale');
    }

    public function test_the_site_asks_for_the_news_topic_even_to_a_visitor(): void
    {
        Season::factory()->create(['is_current' => true]);

        $this->get(route('site.standings'))
            ->assertOk()
            ->assertSee("['matches', 'news']", false);
    }
}
