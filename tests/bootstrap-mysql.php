<?php

use Illuminate\Foundation\Application;

require_once dirname(__DIR__).'/vendor/autoload.php';

function assertCatalogMySqlSafety(?Application $app = null): void
{
    $expected = [
        'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql',
        'DB_DATABASE' => 'configurator_catalog_test', 'DB_USERNAME' => 'configurator_catalog_test',
        'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3307',
    ];
    foreach ($expected as $key => $value) {
        $actual = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($actual !== $value) {
            throw new RuntimeException('Unsafe catalog MySQL test configuration: '.$key.' must explicitly match the disposable target.');
        }
    }
    foreach (['DB_URL', 'DATABASE_URL', 'APP_CONFIG_CACHE'] as $key) {
        if ($_ENV[$key] ?? $_SERVER[$key] ?? getenv($key)) {
            throw new RuntimeException('Unsafe catalog MySQL test configuration: connection/configuration overrides are forbidden.');
        }
    }
    if (is_file(dirname(__DIR__).'/bootstrap/cache/config.php')) {
        throw new RuntimeException('Unsafe catalog MySQL test configuration: cached configuration exists.');
    }
    if ($app !== null) {
        $config = $app['config'];
        if (! $app->environment('testing') || $config->get('database.default') !== 'mysql'
            || $config->get('database.connections.mysql.database') !== 'configurator_catalog_test'
            || $config->get('database.connections.mysql.username') !== 'configurator_catalog_test'
            || $config->get('database.connections.mysql.host') !== '127.0.0.1'
            || (string) $config->get('database.connections.mysql.port') !== '3307'
            || $config->get('database.connections.mysql.url')) {
            throw new RuntimeException('Unsafe catalog MySQL test configuration: resolved application target differs.');
        }
    }
}

assertCatalogMySqlSafety();
define('CATALOG_MYSQL_TEST_RUN', true);
