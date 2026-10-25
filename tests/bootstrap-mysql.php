<?php

use Illuminate\Foundation\Application;
use Tests\TestDatabaseSafety;

require_once __DIR__.'/TestDatabaseSafety.php';

function assertCatalogMySqlSafety(?Application $app = null): void
{
    TestDatabaseSafety::assertBeforeBootstrap(true);
    if ($app !== null) {
        TestDatabaseSafety::assertApplication($app, true);
    }
}

assertCatalogMySqlSafety();
if (! defined('CATALOG_MYSQL_TEST_RUN')) {
    define('CATALOG_MYSQL_TEST_RUN', true);
}

require_once dirname(__DIR__).'/vendor/autoload.php';
