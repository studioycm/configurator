<?php

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Symfony\Component\Process\Process;
use Tests\TestDatabaseConnectionFactory;
use Tests\TestDatabaseSafety;

test('the safety guard accepts only a deliberate disposable environment', function (array $environment, bool $mysqlRun) {
    TestDatabaseSafety::assertEnvironment($environment, [], [], $mysqlRun);
    expect(true)->toBeTrue();
})->with([
    'sqlite memory' => [['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:'], false],
    'approved mysql' => [['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'configurator_catalog_test', 'DB_USERNAME' => 'configurator_catalog_test', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3307'], true],
]);

test('the safety guard refuses overrides and ambiguous environment sources without booting the app', function (array $overrides, array $server) {
    $environment = array_merge(['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:'], $overrides);

    expect(fn () => TestDatabaseSafety::assertEnvironment($environment, $server, [], false))
        ->toThrow(RuntimeException::class, 'Unsafe test database configuration');
})->with([
    'connection url' => [['DB_URL' => 'mysql://root@127.0.0.1/configurator_catalog_dev'], []],
    'alternate url' => [['DATABASE_URL' => 'mysql://root@127.0.0.1/configurator_catalog_dev'], []],
    'file sqlite' => [['DB_DATABASE' => '/tmp/development.sqlite'], []],
    'driver alias' => [['DB_CONNECTION' => 'development'], []],
    'not testing' => [['APP_ENV' => 'local'], []],
    'cached config override' => [['APP_CONFIG_CACHE' => '/tmp/unsafe-config.php'], []],
    'empty cached config override' => [['APP_CONFIG_CACHE' => ''], []],
    'conflicting source' => [[], ['DB_DATABASE' => 'configurator_catalog_dev']],
]);

test('the connection guard validates the effective target rather than its alias', function (array $connection) {
    expect(fn () => TestDatabaseSafety::assertConnection($connection, false))
        ->toThrow(RuntimeException::class, 'Unsafe test database configuration');
})->with([
    'mysql behind sqlite alias' => [['driver' => 'mysql', 'database' => 'configurator_catalog_dev']],
    'sqlite file' => [['driver' => 'sqlite', 'database' => '/tmp/development.sqlite']],
    'safe base unsafe read' => [['driver' => 'sqlite', 'database' => ':memory:', 'read' => ['database' => '/tmp/development.sqlite']]],
    'safe base unsafe write' => [['driver' => 'sqlite', 'database' => ':memory:', 'write' => ['database' => '/tmp/development.sqlite']]],
    'safe base direct' => [['driver' => 'sqlite', 'database' => ':memory:', 'direct' => ['database' => '/tmp/development.sqlite']]],
    'safe base url' => [['driver' => 'sqlite', 'database' => ':memory:', 'url' => 'mysql://root@localhost/live']],
]);

test('an in-memory alias is accepted without opening a connection', function () {
    TestDatabaseSafety::assertConnection(['driver' => 'sqlite', 'database' => ':memory:', 'name' => 'custom_alias'], false);
    expect(true)->toBeTrue();
});

test('connection creation rejects a late unsafe alias before constructing its PDO resolver', function () {
    $factory = new TestDatabaseConnectionFactory(new Container, false);

    expect(fn () => $factory->make(['driver' => 'sqlite', 'database' => '/tmp/unsafe-alias.sqlite'], 'runtime_alias'))
        ->toThrow(RuntimeException::class, 'Unsafe test database configuration');
});

test('a named connection cannot hide a url after framework parsing', function (array|string $declaration) {
    $container = new Container;
    $container->instance('config', new Repository([
        'database' => ['connections' => ['late_alias' => $declaration]],
    ]));
    $factory = new TestDatabaseConnectionFactory($container, false);

    expect(fn () => $factory->make(['driver' => 'sqlite', 'database' => ':memory:'], 'late_alias'))
        ->toThrow(RuntimeException::class, 'Unsafe test database configuration');
})->with([
    'array url' => [['driver' => 'sqlite', 'database' => ':memory:', 'url' => 'sqlite:///:memory:']],
    'string url' => ['sqlite:///:memory:'],
]);

test('test launchers refuse a cached configuration before executing its PHP', function (array $command) {
    $marker = tempnam(sys_get_temp_dir(), 'consolidation-sentinel-');
    $cache = tempnam(sys_get_temp_dir(), 'consolidation-cache-');
    unlink($marker);
    file_put_contents($cache, '<?php file_put_contents('.var_export($marker, true).', "unsafe"); return [];');
    $process = new Process([PHP_BINARY, ...$command], dirname(__DIR__, 2), [
        'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:',
        'DB_URL' => false, 'DATABASE_URL' => false, 'APP_CONFIG_CACHE' => $cache,
    ]);
    try {
        $process->run();

        expect($process->isSuccessful())->toBeFalse();
        expect($process->getErrorOutput().$process->getOutput())->toContain('Unsafe test database configuration');
        expect(file_exists($marker))->toBeFalse();
    } finally {
        unlink($cache);
        if (file_exists($marker)) {
            unlink($marker);
        }
    }
})->with([
    'outer artisan' => [['artisan', 'test', '--help']],
    'direct pest' => [['vendor/bin/pest', '--configuration=phpunit.xml', '--filter=consolidation_nonexistent_sentinel']],
]);

test('resolved configuration is refused before provider registration', function () {
    $code = <<<'PHP'
    require 'vendor/autoload.php';
    $app = require 'bootstrap/app.php';
    Tests\TestDatabaseSafety::register($app);
    $app->afterBootstrapping(Illuminate\Foundation\Bootstrap\LoadConfiguration::class, function ($app) {
        $app['config']->set('database.connections.sqlite.database', '/tmp/unsafe-resolved.sqlite');
    });
    $app->beforeBootstrapping(Illuminate\Foundation\Bootstrap\RegisterProviders::class, function () {
        echo 'PROVIDER_SENTINEL';
    });
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    PHP;
    $process = new Process([PHP_BINARY, '-r', $code], dirname(__DIR__, 2), [
        'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:',
        'DB_URL' => false, 'DATABASE_URL' => false, 'APP_CONFIG_CACHE' => false,
    ]);
    $process->run();
    $output = $process->getErrorOutput().$process->getOutput();

    expect($process->isSuccessful())->toBeFalse();
    expect($output)->toContain('Unsafe test database configuration')->not->toContain('PROVIDER_SENTINEL');
});

test('dotenv cannot introduce executable cached configuration after the inert preflight', function () {
    $directory = sys_get_temp_dir().'/consolidation-dotenv-'.bin2hex(random_bytes(6));
    mkdir($directory);
    $marker = $directory.'/executed';
    $cache = $directory.'/config.php';
    file_put_contents($cache, '<?php file_put_contents('.var_export($marker, true).', "unsafe"); return [];');
    file_put_contents($directory.'/.env.testing', 'APP_CONFIG_CACHE='.$cache."\n");
    $code = <<<'PHP'
    require 'vendor/autoload.php';
    Tests\TestDatabaseSafety::assertBeforeBootstrap();
    $app = require 'bootstrap/app.php';
    $app->useEnvironmentPath($argv[1]);
    Tests\TestDatabaseSafety::register($app);
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    PHP;
    $process = new Process([PHP_BINARY, '-r', $code, $directory], dirname(__DIR__, 2), [
        'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:',
        'DB_URL' => false, 'DATABASE_URL' => false, 'APP_CONFIG_CACHE' => false,
    ]);
    try {
        $process->run();

        expect($process->isSuccessful())->toBeFalse();
        expect($process->getErrorOutput().$process->getOutput())->toContain('Unsafe test database configuration');
        expect(file_exists($marker))->toBeFalse();
    } finally {
        foreach (glob($directory.'/*') as $path) {
            unlink($path);
        }
        unlink($directory.'/.env.testing');
        rmdir($directory);
    }
});

test('rules profile accepts only its exact dedicated target without broadening the catalog profile', function () {
    $rules = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3307', 'DB_DATABASE' => 'configurator_rules_test', 'DB_USERNAME' => 'configurator_rules_test', 'CATALOG_MYSQL_TEST_PROFILE' => 'rules'];
    TestDatabaseSafety::assertEnvironment($rules, [], [], true);
    foreach ([['DB_USERNAME' => 'root'], ['DB_DATABASE' => 'configurator_catalog_dev'], ['DB_DATABASE' => 'configurator_catalog_test'], ['CATALOG_MYSQL_TEST_PROFILE' => 'anything'], ['CATALOG_MYSQL_TEST_PROFILE' => '']] as $override) {
        expect(fn () => TestDatabaseSafety::assertEnvironment([...$rules, ...$override], [], [], true))->toThrow(RuntimeException::class);
    }
    expect(fn () => TestDatabaseSafety::assertEnvironment($rules, ['CATALOG_MYSQL_TEST_PROFILE' => 'catalog'], [], true))->toThrow(RuntimeException::class);
    $unselected = $rules;
    unset($unselected['CATALOG_MYSQL_TEST_PROFILE']);
    expect(fn () => TestDatabaseSafety::assertEnvironment($unselected, [], [], true))->toThrow(RuntimeException::class);
});

test('rules mysql bootstrap refuses mismatched targets before application boot', function (array $override, bool $accepted) {
    $environment = [
        'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3307',
        'DB_DATABASE' => 'configurator_rules_test', 'DB_USERNAME' => 'configurator_rules_test', 'CATALOG_MYSQL_TEST_PROFILE' => 'rules',
        'DB_URL' => false, 'DATABASE_URL' => false, 'DB_SOCKET' => false, 'APP_CONFIG_CACHE' => false, 'APP_BASE_PATH' => false, 'LARAVEL_STORAGE_PATH' => false,
    ];
    $process = new Process([PHP_BINARY, 'tests/bootstrap-mysql.php'], dirname(__DIR__, 2), [...$environment, ...$override]);
    $process->run();
    expect($process->isSuccessful())->toBe($accepted);
    if (! $accepted) {
        expect($process->getErrorOutput().$process->getOutput())->toContain('Unsafe catalog MySQL test configuration');
    }
})->with([
    'dedicated rules target' => [[], true],
    'cross-profile database' => [['DB_DATABASE' => 'configurator_catalog_test'], false],
    'cross-profile user' => [['DB_USERNAME' => 'configurator_catalog_test'], false],
    'admin account' => [['DB_USERNAME' => 'root'], false],
    'other port' => [['DB_PORT' => '3306'], false],
    'unknown profile' => [['CATALOG_MYSQL_TEST_PROFILE' => 'production'], false],
]);

test('rules profile validates the resolved connection independently of its alias without opening PDO', function (array $override, bool $accepted) {
    $environment = [
        'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3307',
        'DB_DATABASE' => 'configurator_rules_test', 'DB_USERNAME' => 'configurator_rules_test', 'CATALOG_MYSQL_TEST_PROFILE' => 'rules',
        'DB_URL' => false, 'DATABASE_URL' => false, 'DB_SOCKET' => false, 'APP_CONFIG_CACHE' => false, 'APP_BASE_PATH' => false, 'LARAVEL_STORAGE_PATH' => false,
    ];
    $connection = ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => '3307', 'database' => 'configurator_rules_test', 'username' => 'configurator_rules_test', 'name' => 'arbitrary_alias'];
    $code = 'require "tests/bootstrap-mysql.php"; Tests\\TestDatabaseSafety::assertConnection(json_decode($argv[1], true), true); echo "SAFE_TARGET";';
    $process = new Process([PHP_BINARY, '-r', $code, json_encode([...$connection, ...$override], JSON_THROW_ON_ERROR)], dirname(__DIR__, 2), $environment);
    $process->run();
    expect($process->isSuccessful())->toBe($accepted);
    if ($accepted) {
        expect($process->getOutput())->toBe('SAFE_TARGET');
    } else {
        expect($process->getErrorOutput().$process->getOutput())->toContain('Unsafe catalog MySQL test configuration');
    }
})->with([
    'exact target through alias' => [[], true],
    'catalog target' => [['database' => 'configurator_catalog_test'], false],
    'root account' => [['username' => 'root'], false],
    'other port' => [['port' => 3306], false],
    'read override' => [['read' => ['database' => 'configurator_catalog_dev']], false],
    'url override' => [['url' => 'mysql://root@127.0.0.1/configurator_catalog_dev'], false],
]);

test('Writing MySQL entry refuses the catalog profile before fixtures can run', function () {
    $environment = [
        'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3307',
        'DB_DATABASE' => 'configurator_catalog_test', 'DB_USERNAME' => 'configurator_catalog_test', 'CATALOG_MYSQL_TEST_PROFILE' => 'catalog',
        'DB_URL' => false, 'DATABASE_URL' => false, 'DB_SOCKET' => false, 'APP_CONFIG_CACHE' => false, 'APP_BASE_PATH' => false, 'LARAVEL_STORAGE_PATH' => false,
    ];
    $code = 'require "tests/Feature/Catalog/MySql/ConfiguratorRuleWritingConcurrencyTest.php";';
    $process = new Process([PHP_BINARY, '-r', $code], dirname(__DIR__, 2), $environment);
    $process->run();
    expect($process->isSuccessful())->toBeFalse();
    expect($process->getErrorOutput().$process->getOutput())->toContain('Rules Writing MySQL tests require the reviewed reconciliation profile');
});
