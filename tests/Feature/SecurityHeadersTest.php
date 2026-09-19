<?php

namespace Tests\Feature;

use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Guards the response-header, HTTPS and rate-limit posture (design D3, D4, D5).
 */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    private const EXPECTED = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
    ];

    public function test_public_response_carries_hardening_headers(): void
    {
        $response = $this->get('/noticias')->assertSuccessful();

        foreach (self::EXPECTED as $header => $value) {
            $response->assertHeader($header, $value);
        }

        $this->assertStringContainsString('camera=()', $response->headers->get('Permissions-Policy'));
    }

    /**
     * The panel is the surface that matters most, and it does NOT run through
     * the `web` middleware group — it carries its own list. A group-scoped
     * registration would leave it bare, so this is asserted separately rather
     * than assumed from the public case (design D3).
     */
    public function test_admin_response_carries_the_same_headers(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get('/admin')
            ->assertSuccessful();

        foreach (self::EXPECTED as $header => $value) {
            $response->assertHeader($header, $value);
        }
    }

    public function test_plain_http_response_omits_hsts(): void
    {
        $this->get('http://localhost/noticias')
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_https_response_carries_hsts_without_preload(): void
    {
        $header = $this->get('https://localhost/noticias')
            ->headers->get('Strict-Transport-Security');

        $this->assertNotNull($header, 'HSTS missing on a secure request.');
        $this->assertMatchesRegularExpression('/max-age=[1-9]\d*/', $header);
        $this->assertStringNotContainsString('preload', $header);
    }

    public function test_https_is_not_forced_by_default(): void
    {
        $this->assertFalse(config('app.force_https'));
        $this->assertStringStartsWith('http://', url('/'));
    }

    public function test_https_is_forced_when_the_flag_is_enabled(): void
    {
        config()->set('app.force_https', true);
        (new AppServiceProvider($this->app))->boot();

        $this->assertStringStartsWith('https://', url('/'));
    }

    public function test_public_routes_are_rate_limited(): void
    {
        $names = ['site.standings', 'site.fixtures', 'site.scorers', 'site.news.index', 'site.news.show'];

        foreach ($names as $name) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, "Route {$name} disappeared.");
            $this->assertContains('throttle:60,1', $route->gatherMiddleware(), "Route {$name} is not throttled.");
        }
    }

    public function test_exceeding_the_public_budget_returns_429(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->get('/noticias')->assertSuccessful();
        }

        $this->get('/noticias')->assertStatus(429);
    }

    /**
     * Filament already throttles the one endpoint that matters (login, 5
     * attempts). A blanket public limit on the panel would fire on an
     * administrator doing bulk data entry (design D5).
     */
    public function test_panel_routes_do_not_carry_the_public_rate_limit(): void
    {
        $route = Route::getRoutes()->getByName('filament.admin.pages.dashboard');

        $this->assertNotNull($route);
        $this->assertNotContains('throttle:60,1', $route->gatherMiddleware());
    }
}
