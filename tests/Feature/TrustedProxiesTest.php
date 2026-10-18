<?php

namespace Tests\Feature;

use App\Models\Season;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * En Hostinger nadie se sienta delante del servidor web, así que las cabeceras
 * X-Forwarded-* sólo pueden venir del propio visitante y no se creen.
 *
 * Hasta el 2026-10-17 se confiaba en cualquiera (`'*'`, herencia de Vercel), y
 * cambiar X-Forwarded-For en cada petición daba un contador nuevo cada vez: el
 * tope de 60 por minuto y el de intentos de acceso no frenaban a nadie.
 */
class TrustedProxiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_forged_forwarded_for_does_not_change_the_visitors_ip(): void
    {
        Season::factory()->create(['is_current' => true]);

        for ($i = 1; $i <= 60; $i++) {
            $this->withHeader('X-Forwarded-For', "203.0.113.{$i}")
                ->get(route('site.news.index'))
                ->assertOk();
        }

        $this->withHeader('X-Forwarded-For', '203.0.113.200')
            ->get(route('site.news.index'))
            ->assertStatus(429);
    }

    public function test_a_proxy_listed_in_the_config_is_believed(): void
    {
        config(['trustedproxy.proxies' => '127.0.0.1']);

        $this->withHeader('X-Forwarded-For', '203.0.113.7')
            ->get('/up')
            ->assertOk();

        $this->assertSame('203.0.113.7', request()->ip());
    }
}
