<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use RuntimeException;

trait CreatesApplication
{
    /**
     * Creates the application.
     *
     * @return Application
     */
    public function createApplication()
    {
        /*
         * Never allow PHPUnit to boot using a cached local configuration.
         * A cached config keeps the live MySQL connection even though
         * phpunit.xml requests SQLite :memory:, which makes RefreshDatabase
         * dangerously target the local development database.
         */
        $cachedConfig = __DIR__.'/../bootstrap/cache/config.php';
        if (is_file($cachedConfig)) {
            throw new RuntimeException(
                'Refusing to run tests while bootstrap/cache/config.php exists. '
                .'Run "php artisan config:clear" first; tests must use SQLite :memory:.'
            );
        }

        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        $isSafeTestingConnection = $app['config']->get('database.default') === 'sqlite'
            && $app['config']->get('database.connections.sqlite.database') === ':memory:';

        if (! $isSafeTestingConnection) {
            throw new RuntimeException(
                'Refusing to run tests without the isolated SQLite :memory: database.'
            );
        }

        return $app;
    }
}
