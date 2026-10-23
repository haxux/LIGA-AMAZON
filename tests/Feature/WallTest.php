<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\PushSubscription;
use App\Models\Season;
use App\Models\User;
use App\Models\WallPost;
use App\Models\WallReaction;
use App\Services\PushNotificationService;
use App\Services\WallService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Minishlink\WebPush\WebPush;
use Tests\TestCase;

/**
 * «A Segunda», el foro interno de Noticias (decisión del propietario,
 * 2026-10-23): sólo con sesión, con fotos, respuestas, «me gusta» y «no me
 * gusta», y un aviso sólo cuando alguien te responde.
 */
class WallTest extends TestCase
{
    use RefreshDatabase;

    private User $coach;

    private User $other;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('filesystems.uploads'));
        Season::factory()->create(['is_current' => true]);
        $this->coach = User::factory()->coachOf(Club::factory()->create(['name' => 'SV Apoera']))->create(['name' => 'Josue Puello']);
        $this->other = User::factory()->coachOf(Club::factory()->create(['name' => 'La Fábrica']))->create(['name' => 'Dominus']);
        $this->admin = User::factory()->create(['name' => 'Presidente']);
    }

    private function publish(User $author, string $body = 'Hola liga', ?WallPost $parent = null): WallPost
    {
        return app(WallService::class)->publish($author, $body, [], $parent);
    }

    public function test_a_visitor_neither_sees_it_nor_its_tab(): void
    {
        $this->get(route('site.wall.index'))->assertRedirect(route('filament.club.auth.login'));
        $this->get(route('site.news.index'))->assertOk()->assertDontSee('A Segunda');
        $this->post(route('site.wall.store'), ['body' => 'Hola'])->assertForbidden();

        $this->assertDatabaseCount('wall_posts', 0);
    }

    public function test_a_coach_reads_and_finds_the_tab_in_news(): void
    {
        $this->publish($this->other, 'Se viene el clásico');

        $this->actingAs($this->coach, 'club')->get(route('site.news.index'))
            ->assertOk()->assertSee(route('site.wall.index'));

        $this->get(route('site.wall.index'))
            ->assertOk()
            ->assertSee('Se viene el clásico')
            ->assertSee('Dominus');
    }

    public function test_a_photo_is_shrunk_and_saved_as_jpeg(): void
    {
        $this->actingAs($this->coach, 'club')
            ->post(route('site.wall.store'), [
                'body' => 'Mirad esto',
                'images' => [UploadedFile::fake()->image('grande.png', 3200, 2400)],
            ])
            ->assertRedirect();

        $image = WallPost::query()->sole()->images()->sole();

        $this->assertStringEndsWith('.jpg', $image->path);
        $this->assertSame([1600, 1200], [$image->width, $image->height]);
        $this->assertSame('image/jpeg', getimagesizefromstring(Storage::disk(config('filesystems.uploads'))->get($image->path))['mime']);
    }

    public function test_a_post_needs_text_or_a_photo_and_at_most_four_photos(): void
    {
        $this->actingAs($this->coach, 'club');

        $this->post(route('site.wall.store'), ['body' => '   '])->assertSessionHasErrors('body');
        $this->post(route('site.wall.store'), ['body' => str_repeat('a', WallPost::MAX_BODY + 1)])->assertSessionHasErrors('body');
        $this->post(route('site.wall.store'), [
            'images' => array_map(fn ($i) => UploadedFile::fake()->image("f{$i}.jpg"), range(1, 5)),
        ])->assertSessionHasErrors('images');
        $this->post(route('site.wall.store'), [
            'images' => [UploadedFile::fake()->create('virus.pdf', 10, 'application/pdf')],
        ])->assertSessionHasErrors('images.0');

        $this->assertDatabaseCount('wall_posts', 0);
    }

    public function test_replying_tells_only_the_author_of_the_post(): void
    {
        $push = $this->spy(PushNotificationService::class);
        $post = $this->publish($this->coach);

        $this->actingAs($this->other, 'club')
            ->post(route('site.wall.store'), ['body' => 'De acuerdo', 'parent_id' => $post->id])
            ->assertRedirect();

        $push->shouldHaveReceived('wallReply')->once();
        $this->assertSame($post->id, WallPost::query()->where('body', 'De acuerdo')->sole()->parent_id);
    }

    public function test_the_reply_push_goes_to_the_author_and_not_to_oneself(): void
    {
        // Un navegador por técnico; el cliente de push, de mentira, apunta a
        // qué navegador se manda cada aviso.
        foreach ([$this->coach, $this->other] as $user) {
            PushSubscription::create([
                'endpoint' => 'https://push.example/'.$user->id,
                'public_key' => 'k', 'auth_token' => 'a',
                'topics' => [PushSubscription::TOPIC_CHAT],
            ])->users()->attach($user);
        }

        $sent = [];
        $client = \Mockery::mock(WebPush::class);
        $client->shouldReceive('queueNotification')->andReturnUsing(function ($subscription, $payload) use (&$sent): void {
            $sent[] = [$subscription->getEndpoint(), json_decode($payload, true)['title']];
        });
        $client->shouldReceive('flush')->andReturn([]);
        $push = app(PushNotificationService::class);
        (new \ReflectionProperty($push, 'client'))->setValue($push, $client);
        $this->instance(PushNotificationService::class, $push);
        $this->withoutDefer();

        $post = $this->publish($this->coach);
        $this->publish($this->other, 'Respuesta', $post);
        $this->publish($this->coach, 'Me respondo a mí mismo', $post);

        $this->assertSame([['https://push.example/'.$this->coach->id, 'Dominus respondió en A Segunda']], $sent);
    }

    public function test_a_reply_to_a_reply_hangs_from_the_post(): void
    {
        $post = $this->publish($this->coach);
        $reply = $this->publish($this->other, 'Uno', $post);

        $this->assertSame($post->id, $this->publish($this->admin, 'Dos', $reply)->parent_id);
    }

    public function test_reacting_toggles_and_switches(): void
    {
        $post = $this->publish($this->other);
        $this->actingAs($this->coach, 'club');

        $this->postJson(route('site.wall.react', $post), ['value' => WallReaction::LIKE])
            ->assertJson(['mine' => 1, 'likes' => 1, 'dislikes' => 0]);
        $this->postJson(route('site.wall.react', $post), ['value' => WallReaction::DISLIKE])
            ->assertJson(['mine' => -1, 'likes' => 0, 'dislikes' => 1]);
        $this->postJson(route('site.wall.react', $post), ['value' => WallReaction::DISLIKE])
            ->assertJson(['mine' => 0, 'likes' => 0, 'dislikes' => 0]);
    }

    public function test_only_the_author_or_the_president_deletes_and_photos_go_with_it(): void
    {
        $post = app(WallService::class)->publish($this->coach, 'Con foto', [UploadedFile::fake()->image('a.jpg')]);
        $reply = $this->publish($this->other, 'Respuesta', $post);
        $path = $post->images()->sole()->path;

        $this->actingAs($this->other, 'club')->delete(route('site.wall.destroy', $post))->assertForbidden();
        $this->delete(route('site.wall.destroy', $reply))->assertRedirect();
        $this->assertModelMissing($reply);

        // Con las dos sesiones abiertas manda la de técnico (ver SiteAccount).
        auth('club')->logout();
        $this->actingAs($this->admin, 'web')->delete(route('site.wall.destroy', $post))->assertRedirect();

        $this->assertModelMissing($post);
        Storage::disk(config('filesystems.uploads'))->assertMissing($path);
    }

    public function test_nobody_floods_the_wall(): void
    {
        foreach (range(1, WallService::PER_HOUR) as $i) {
            $this->publish($this->coach, "Mensaje {$i}");
        }

        $this->actingAs($this->coach, 'club')
            ->post(route('site.wall.store'), ['body' => 'Uno más'])
            ->assertSessionHasErrors('body');
    }

    public function test_a_reply_link_opens_its_thread(): void
    {
        $post = $this->publish($this->coach);
        $reply = $this->publish($this->other, 'Respuesta', $post);

        $this->actingAs($this->coach, 'club')
            ->get(route('site.wall.show', $reply))
            ->assertRedirect(route('site.wall.show', $post).'#publicacion-'.$reply->id);

        $this->get(route('site.wall.show', $post))->assertOk()->assertSee('Respuesta')->assertSee('Responder');
    }
}
