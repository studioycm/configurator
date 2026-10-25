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
