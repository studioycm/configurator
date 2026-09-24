<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

trait CreatesApplication
{
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        if (defined('CATALOG_MYSQL_TEST_RUN') || $app['config']->get('database.default') === 'mysql') {
            require_once __DIR__.'/bootstrap-mysql.php';
            assertCatalogMySqlSafety($app);
        }

        return $app;
    }
}
