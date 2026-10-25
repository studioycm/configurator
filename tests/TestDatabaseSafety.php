<?php

namespace Tests;

use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use RuntimeException;

final class TestDatabaseSafety
{
    /** @var array<string, string> */
    private const MYSQL = [
        'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => '3307',
        'database' => 'configurator_catalog_test', 'username' => 'configurator_catalog_test',
    ];

    public static function assertBeforeBootstrap(bool $mysqlRun = false): void
    {
        self::assertEnvironment($_ENV, $_SERVER, getenv(), $mysqlRun);
        if (is_file(dirname(__DIR__).'/bootstrap/cache/config.php')) {
            self::refuse('cached configuration exists', $mysqlRun);
        }
    }

    /** @param array<string, mixed> $environment @param array<string, mixed> $server @param array<string, mixed> $process */
    public static function assertEnvironment(array $environment, array $server, array $process, bool $mysqlRun = false): void
    {
        $sources = [$environment, $server, $process];
        foreach (['DB_URL', 'DATABASE_URL', 'APP_CONFIG_CACHE', 'APP_BASE_PATH', 'LARAVEL_STORAGE_PATH', 'DB_SOCKET'] as $key) {
            foreach ($sources as $source) {
                $isPathOverride = in_array($key, ['APP_CONFIG_CACHE', 'APP_BASE_PATH', 'LARAVEL_STORAGE_PATH'], true);
                if (isset($source[$key]) && $source[$key] !== false && ($isPathOverride || $source[$key] !== '')) {
                    self::refuse($key.' overrides are forbidden', $mysqlRun);
                }
            }
        }
        $expected = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:'];
        if ($mysqlRun) {
            $expected = [
                'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql',
                'DB_HOST' => self::MYSQL['host'], 'DB_PORT' => self::MYSQL['port'],
                'DB_DATABASE' => self::MYSQL['database'], 'DB_USERNAME' => self::MYSQL['username'],
            ];
        }
        foreach ($expected as $key => $value) {
            $found = false;
            foreach ($sources as $source) {
                if (! isset($source[$key]) || $source[$key] === false) {
                    continue;
                }
                $found = true;
                if ((string) $source[$key] !== $value) {
                    self::refuse($key.' differs from the disposable target', $mysqlRun);
                }
            }
            if (! $found) {
                self::refuse($key.' must be explicit', $mysqlRun);
            }
        }
    }

    /** @param array<string, mixed> $connection */
    public static function assertConnection(array $connection, bool $mysqlRun = false): void
    {
        foreach (['url', 'read', 'write', 'direct', 'unix_socket', 'options'] as $key) {
            if (! empty($connection[$key])) {
                self::refuse('connection '.$key.' overrides are forbidden', $mysqlRun);
            }
        }
        if (($connection['driver'] ?? null) === 'sqlite' && ($connection['database'] ?? null) === ':memory:') {
            return;
        }
        if ($mysqlRun) {
            foreach (self::MYSQL as $key => $expected) {
                if (! isset($connection[$key]) || ! is_scalar($connection[$key]) || (string) $connection[$key] !== $expected) {
                    self::refuse('resolved connection '.$key.' differs from the disposable target', true);
                }
            }

            return;
        }
        self::refuse('resolved target is not SQLite memory', false);
    }

    public static function register(Application $app, bool $mysqlRun = false): void
    {
        $app->beforeBootstrapping(LoadConfiguration::class, function () use ($mysqlRun): void {
            self::assertBeforeBootstrap($mysqlRun);
        });
        $app->extend('db.factory', fn (ConnectionFactory $factory, Application $container): TestDatabaseConnectionFactory => new TestDatabaseConnectionFactory($container, $mysqlRun));
        $app->beforeBootstrapping(RegisterProviders::class, function (Application $application) use ($mysqlRun): void {
            self::assertApplication($application, $mysqlRun);
        });
    }

    public static function assertApplication(Application $app, bool $mysqlRun = false): void
    {
        if (! $app->environment('testing')) {
            self::refuse('resolved environment is not testing', $mysqlRun);
        }
        $name = $app['config']->get('database.default');
        $connection = $app['config']->get('database.connections.'.$name);
        if (! is_array($connection) || ($connection['driver'] ?? null) !== ($mysqlRun ? 'mysql' : 'sqlite')) {
            self::refuse('resolved default driver differs from the selected test mode', $mysqlRun);
        }
        self::assertConnection($connection, $mysqlRun);
    }

    private static function refuse(string $reason, bool $mysqlRun): never
    {
        throw new RuntimeException(($mysqlRun ? 'Unsafe catalog MySQL test configuration: ' : 'Unsafe test database configuration: ').$reason.'.');
    }
}
