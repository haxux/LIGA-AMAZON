<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\PushSubscription;
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
}
