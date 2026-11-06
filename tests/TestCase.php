<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = require Application::inferBasePath() . '/bootstrap/app.php';
        $app->loadEnvironmentFrom('.env.testing');
        $app->afterBootstrapping(LoadConfiguration::class, function (Application $app): void {
            $app['config']->set([
                'database.default' => 'sqlite',
                'database.connections.sqlite.database' => ':memory:',
                'database.connections.sqlite.url' => null,
                'session.driver' => 'array',
                'cache.default' => 'array',
                'queue.default' => 'sync',
            ]);
        });
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
