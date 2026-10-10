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
     * Al revés que antes: el servidor de Hostinger tiene disco propio, así que
     * las subidas van al disco local y los logs a fichero. Lo que aquí se
     * vigila es que no se cuele de vuelta la configuración de un host efímero
     * —subidas a un bucket, logs a stderr—, que ya no describe nada real y
     * dejaría las imágenes apuntando a un sitio donde no están.
     */
    public function test_template_assumes_the_servers_own_disk(): void
    {
        $contents = file_get_contents(self::PATH);

        $this->assertStringContainsString('UPLOADS_DISK=public', $contents);
        $this->assertStringContainsString('LOG_CHANNEL=stack', $contents);

        $activas = array_filter(
            file(self::PATH, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES),
            fn (string $l) => ! str_starts_with(trim($l), '#'),
        );

        foreach (['UPLOADS_DISK=s3', 'LOG_CHANNEL=stderr'] as $viejo) {
            $this->assertEmpty(
                array_filter($activas, fn (string $l) => trim($l) === $viejo),
                "{$viejo} describe el despliegue efímero que ya no existe.",
            );
        }
    }

    /**
     * La base vive en la misma máquina, y Laravel trae un driver propio para
     * MariaDB desde la 11. Con `mysql` funcionaría, pero `mariadb` conoce sus
     * diferencias.
     */
    public function test_the_template_points_at_mariadb_on_the_same_machine(): void
    {
        $contents = file_get_contents(self::PATH);

        $this->assertStringContainsString('DB_CONNECTION=mariadb', $contents);
        $this->assertStringContainsString('DB_HOST=localhost', $contents);
        $this->assertStringNotContainsString('MYSQL_ATTR_SSL_CA', $contents);
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
            'VAPID_PRIVATE_KEY',
            'CRON_SECRET',
        ];
        $lines = file(self::PATH, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($keys as $key) {
            $matches = array_values(array_filter($lines, fn (string $l) => str_starts_with($l, $key.'=')));

            $this->assertCount(1, $matches, "{$key} must appear exactly once in the template.");
            $this->assertSame($key.'=', $matches[0], "{$key} must be present but empty.");
        }
    }

    /**
     * El almacenamiento de objetos se abandonó al mudarse: fuera el bucket,
     * fuera el disco y fuera el paquete que lo leía. Ninguna credencial suya
     * debe seguir pidiéndose, o el próximo que despliegue creerá que hace falta
     * conseguirla.
     */
    public function test_no_object_storage_remains(): void
    {
        $plantilla = file_get_contents(self::PATH);

        foreach (['R2_ACCESS_KEY_ID', 'R2_SECRET_ACCESS_KEY', 'AWS_BUCKET', 'AWS_ENDPOINT'] as $clave) {
            $this->assertStringNotContainsString($clave, $plantilla);
        }

        // Y que no vuelva por la puerta de atrás: sin el paquete no hay driver,
        // así que usar un disco de objetos reventaría al primer fichero.
        $this->assertStringNotContainsString(
            'league/flysystem-aws-s3-v3',
            file_get_contents(base_path('composer.json')),
        );

        // Se mira el FICHERO de configuración, no `config()`: desde Laravel 11
        // el framework trae su propio config/filesystems.php y lo fusiona por
        // debajo del nuestro, así que la clave 's3' sigue ahí hagamos lo que
        // hagamos. Lo que está en nuestra mano es no declararla.
        $this->assertStringNotContainsString(
            "'s3' => [",
            file_get_contents(config_path('filesystems.php')),
        );

        // El disco de subidas tiene que ser uno local: es lo que hace que una
        // imagen subida desde el panel siga ahí mañana.
        $this->assertSame('local', config('filesystems.disks.'.config('filesystems.uploads').'.driver'));
    }
}
