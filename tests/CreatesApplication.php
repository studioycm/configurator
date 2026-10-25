<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

trait CreatesApplication
{
    public function createApplication(): Application
    {
        $mysqlRun = defined('CATALOG_MYSQL_TEST_RUN');
        TestDatabaseSafety::assertBeforeBootstrap($mysqlRun);
        $app = require __DIR__.'/../bootstrap/app.php';
        TestDatabaseSafety::register($app, $mysqlRun);
        $app->make(Kernel::class)->bootstrap();
        TestDatabaseSafety::assertApplication($app, $mysqlRun);

        return $app;
    }
}
