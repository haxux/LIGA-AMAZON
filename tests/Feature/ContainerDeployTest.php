<?php

namespace Tests\Feature;

use Livewire\Mechanisms\HandleRequests\EndpointResolver;
use Tests\TestCase;

/**
 * The container's boot sequence is checked by machine for the same reason as
 * the production env template: its failure modes are silent. A deploy that
 * forgets to migrate serves the old schema against new code — which, the one
 * time it happened, surfaced as a page answering 200 with no data and nothing
 * in the logs — and a cacheable step left in the entrypoint is paid again on
 * every cold start, of which there is one every time the host scales to zero.
 */
class ContainerDeployTest extends TestCase
{
    private const ENTRYPOINT = __DIR__.'/../../docker/vercel/entrypoint.sh';

    private const DOCKERFILE = __DIR__.'/../../Dockerfile.vercel';

    public function test_entrypoint_exists_and_stops_on_the_first_failure(): void
    {
        $this->assertFileExists(self::ENTRYPOINT);
        $this->assertStringContainsString('set -e', file_get_contents(self::ENTRYPOINT));
    }

    public function test_entrypoint_applies_migrations_without_prompting(): void
    {
        $contents = file_get_contents(self::ENTRYPOINT);

        $this->assertStringContainsString('php artisan migrate --force', $contents);
        $this->assertStringNotContainsString('--seed', $contents);
    }

    /**
     * Medido contra la base real: `migrate:status` mas `migrate --force
     * --isolated` cuestan unos 4,2 s, y conectar y contar filas 0,6 s. El
     * contenedor escala a cero tras cinco minutos y arranca muchas veces al
     * dia, asi que esos 3,6 s se pagaban una y otra vez para descubrir, casi
     * siempre, que no habia nada pendiente.
     */
    public function test_the_migrator_only_runs_when_the_cheap_check_says_so(): void
    {
        $entrypoint = file_get_contents(self::ENTRYPOINT);

        $this->assertStringContainsString('migraciones-aplicadas.php', $entrypoint);
        $this->assertFileExists(__DIR__.'/../../docker/vercel/migraciones-aplicadas.php');

        // El numero se hornea en la imagen, donde la lista de ficheros ya es
        // definitiva.
        $this->assertStringContainsString('.migraciones-esperadas', file_get_contents(self::DOCKERFILE));

        // Y la sonda se pregunta ANTES de arrancar el migrador, que es lo unico
        // que hace que ahorre algo.
        $this->assertLessThan(
            mb_strpos($entrypoint, 'migrate --force --isolated'),
            mb_strpos($entrypoint, 'migraciones-aplicadas.php'),
        );
    }

    /**
     * Sin fichero horneado se migra, no se salta: el fallo cae del lado seguro,
     * porque servir codigo nuevo contra un esquema viejo falla en silencio.
     */
    public function test_a_missing_stamp_makes_the_container_migrate(): void
    {
        $this->assertMatchesRegularExpression(
            '/\.migraciones-esperadas 2>\/dev\/null \|\| echo 9+/',
            file_get_contents(self::ENTRYPOINT),
        );
    }

    /**
     * The host may start several instances at once; the lock lives in the
     * cache store, which production points at the database, so it is shared
     * between them.
     */
    public function test_migrations_are_isolated_against_concurrent_instances(): void
    {
        $this->assertStringContainsString('migrate --force --isolated', file_get_contents(self::ENTRYPOINT));
        $this->assertStringContainsString('CACHE_STORE=database', file_get_contents(__DIR__.'/../../.env.production.example'));
    }

    public function test_config_is_cached_at_boot_and_never_baked_into_the_image(): void
    {
        // config:cache freezes every env() call, and the build has neither
        // APP_KEY nor database credentials — baking it ships an application
        // pointing at nothing.
        $this->assertStringContainsString('php artisan config:cache', file_get_contents(self::ENTRYPOINT));
        $this->assertStringNotContainsString('php artisan config:cache', file_get_contents(self::DOCKERFILE));
    }

    public function test_only_the_view_cache_is_baked_into_the_image(): void
    {
        $dockerfile = file_get_contents(self::DOCKERFILE);
        $entrypoint = file_get_contents(self::ENTRYPOINT);

        // Blade reads no environment, so compiling the views at build time is
        // free savings on every cold start.
        $this->assertStringContainsString('view:cache', $dockerfile);
        $this->assertStringNotContainsString('php artisan view:cache', $entrypoint);
    }

    /**
     * Regression, 2026-09-20: the route cache WAS baked into the image on the
     * argument that routes read no environment. Livewire's do, through
     * APP_KEY — EndpointResolver::prefix() hashes it to build the prefix of
     * livewire.min.js and of the /update endpoint every interaction posts to.
     * With no APP_KEY at build time the cached routes answer on a hash no
     * served page ever generates again, so the whole panel loses its
     * JavaScript while the public site, which uses no Livewire, looks fine.
     */
    public function test_routes_are_cached_at_boot_where_the_app_key_exists(): void
    {
        $this->assertStringContainsString('php artisan route:cache', file_get_contents(self::ENTRYPOINT));
        // El Dockerfile lo menciona en un comentario que explica por qué NO se hornea,
        // así que lo que se comprueba es que no lo EJECUTE.
        $this->assertStringNotContainsString('artisan route:cache', file_get_contents(self::DOCKERFILE));
    }

    /**
     * Pins the dependency this regression rests on: if Livewire ever stops
     * deriving its endpoints from APP_KEY, the constraint above can be
     * revisited — and if it keeps doing so, this test says why.
     */
    public function test_livewire_endpoints_depend_on_the_app_key(): void
    {
        $prefix = EndpointResolver::prefix();

        config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $withAnotherKey = EndpointResolver::prefix();

        $this->assertNotSame($prefix, $withAnotherKey);
    }
}
