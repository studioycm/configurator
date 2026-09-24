<?php

use Symfony\Component\Process\Process;

test('the mysql bootstrap refuses an unsafe target before application boot', function (array $overrides) {
    $environment = array_merge([
        'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql',
        'DB_DATABASE' => 'configurator_catalog_test', 'DB_HOST' => '127.0.0.1',
        'DB_PORT' => '3307', 'DB_USERNAME' => 'configurator_catalog_test',
        'DB_URL' => false, 'APP_CONFIG_CACHE' => false,
    ], $overrides);
    $process = new Process([PHP_BINARY, dirname(__DIR__).'/bootstrap-mysql.php'], dirname(__DIR__, 2), $environment);

    $process->run();

    expect($process->isSuccessful())->toBeFalse();
    expect($process->getErrorOutput().$process->getOutput())->toContain('Unsafe catalog MySQL test configuration');
})->with([
    'missing database' => [['DB_DATABASE' => false]],
    'current database' => [['DB_DATABASE' => 'configurator_local']],
    'development database' => [['DB_DATABASE' => 'configurator_catalog_dev']],
    'unapproved test suffix' => [['DB_DATABASE' => 'production_test']],
    'wrong environment' => [['APP_ENV' => 'local']],
    'wrong driver' => [['DB_CONNECTION' => 'sqlite']],
    'unrestricted user' => [['DB_USERNAME' => 'root']],
    'connection url override' => [['DB_URL' => 'mysql://root@localhost/configurator_local']],
    'cached configuration override' => [['APP_CONFIG_CACHE' => '/tmp/catalog-unsafe-config.php']],
]);
