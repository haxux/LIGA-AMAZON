<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The production environment template must be safe by construction, so it is
 * checked by machine rather than by a reviewer's eye. The failure mode it
 * guards against — shipping APP_DEBUG=true — leaks the entire environment,
 * database credentials included, to anyone who triggers an exception.
 */
class ProductionEnvTemplateTest extends TestCase
{
    private const PATH = __DIR__.'/../../.env.production.example';

    public function test_template_exists(): void
    {
        $this->assertFileExists(self::PATH);
    }

    public function test_debug_is_off_and_env_is_production(): void
    {
        $contents = file_get_contents(self::PATH);

        $this->assertStringContainsString('APP_ENV=production', $contents);
        $this->assertStringContainsString('APP_DEBUG=false', $contents);
        $this->assertStringNotContainsString('APP_DEBUG=true', $contents);
    }

    public function test_session_cookie_is_hardened(): void
    {
        $contents = file_get_contents(self::PATH);

        $this->assertStringContainsString('SESSION_SECURE_COOKIE=true', $contents);
        $this->assertStringContainsString('SESSION_ENCRYPT=true', $contents);
    }

    public function test_log_level_is_not_debug(): void
    {
        $this->assertStringNotContainsString('LOG_LEVEL=debug', file_get_contents(self::PATH));
    }

    /**
     * The template targets a host with an ephemeral, possibly read-only
     * filesystem. Two settings would fail silently there rather than loudly:
     * uploads written to a local disk vanish with the container that received
     * them, and file logs are lost — or refused — along with them. Neither
     * surfaces as an error the operator would notice before the damage.
     */
    public function test_template_assumes_no_durable_local_filesystem(): void
    {
        $contents = file_get_contents(self::PATH);

        $this->assertStringContainsString('UPLOADS_DISK=s3', $contents);
        $this->assertStringContainsString('LOG_CHANNEL=stderr', $contents);

        // Line-anchored, not a substring search: the comment above LOG_CHANNEL
        // names LOG_STACK=daily as the setting to restore on a host with a real
        // disk, and a naive assertStringNotContainsString would trip over it.
        $active = array_filter(
            file(self::PATH, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES),
            fn (string $l) => ! str_starts_with(trim($l), '#'),
        );
        $this->assertEmpty(
            array_filter($active, fn (string $l) => str_starts_with($l, 'LOG_STACK=')),
            'LOG_STACK must not be set: file logs do not survive an ephemeral filesystem.',
        );
    }

    /**
     * Every secret-bearing key must be present but empty, so the template can
     * never be the source of a leaked credential or a reused APP_KEY.
     */
    public function test_no_secret_carries_a_value(): void
    {
        $keys = [
            'APP_KEY',
            'DB_PASSWORD',
            'DB_USERNAME',
            'MAIL_PASSWORD',
            'R2_ACCESS_KEY_ID',
            'R2_SECRET_ACCESS_KEY',
        ];
        $lines = file(self::PATH, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($keys as $key) {
            $matches = array_values(array_filter($lines, fn (string $l) => str_starts_with($l, $key.'=')));

            $this->assertCount(1, $matches, "{$key} must appear exactly once in the template.");
            $this->assertSame($key.'=', $matches[0], "{$key} must be present but empty.");
        }
    }
}
