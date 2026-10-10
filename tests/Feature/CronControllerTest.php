<?php

namespace Tests\Feature;

use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La puerta que el cron del servidor usa para disparar lo que, en este despliegue sin
 * worker, nada más dispararía solo (ver GameSchedulingService).
 */
class CronControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_request_without_the_secret_is_rejected(): void
    {
        config(['services.cron.secret' => 'topsecret']);

        $this->getJson('/cron/tick')->assertForbidden();
    }

    public function test_a_request_with_the_wrong_secret_is_rejected(): void
    {
        config(['services.cron.secret' => 'topsecret']);

        $this->withHeader('Authorization', 'Bearer wrong')
            ->getJson('/cron/tick')
            ->assertForbidden();
    }

    public function test_an_unconfigured_secret_refuses_every_request(): void
    {
        config(['services.cron.secret' => null]);

        $this->withHeader('Authorization', 'Bearer anything')
            ->getJson('/cron/tick')
            ->assertForbidden();
    }

    public function test_the_right_secret_runs_the_sweep_and_reports_counts(): void
    {
        config(['services.cron.secret' => 'topsecret']);
        Game::factory()->create(['kickoff_at' => now()->subDays(2)]);

        $response = $this->withHeader('Authorization', 'Bearer topsecret')
            ->getJson('/cron/tick')
            ->assertOk();

        $response->assertJson(['voided' => 1]);
    }
}
