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
     *
     * TEST_AGAINST_REAL_DB is the one deliberate way out, used by
     * scripts/test-tidb.sh to validate the schema on the managed MySQL the
     * application will actually be deployed against. It is set only by
     * phpunit.tidb.xml, never by phpunit.xml, so the protection above still
     * holds for every ordinary run: opting out takes a second config file and
     * a named flag, which is not something you do by accident.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        if (filter_var(env('TEST_AGAINST_REAL_DB', false), FILTER_VALIDATE_BOOL)) {
            return $app;
        }

        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');

        return $app;
    }
}
