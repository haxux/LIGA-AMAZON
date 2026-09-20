<?php

namespace Tests\Feature;

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

    public function test_route_and_view_caches_are_baked_into_the_image(): void
    {
        $dockerfile = file_get_contents(self::DOCKERFILE);
        $entrypoint = file_get_contents(self::ENTRYPOINT);

        $this->assertStringContainsString('route:cache', $dockerfile);
        $this->assertStringContainsString('view:cache', $dockerfile);

        // Neither reads the environment, so paying for them on every cold
        // start buys nothing.
        $this->assertStringNotContainsString('php artisan route:cache', $entrypoint);
        $this->assertStringNotContainsString('php artisan view:cache', $entrypoint);
    }
}
