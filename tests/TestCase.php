<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Force the isolated SQLite :memory: test connection (see phpunit.xml),
     * regardless of any real process-level DB_* environment variables.
     *
     * docker-compose.yml sets DB_CONNECTION=mysql etc. as real OS-level env
     * vars for the `app` container. Those win over phpunit.xml's <env> block
     * in Laravel's env() resolution (Illuminate\Support\Env prioritizes
     * $_SERVER/$_ENV over the putenv adapter), so without this override the
     * test suite would silently run against the real dev MySQL database.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');

        return $app;
    }
}
