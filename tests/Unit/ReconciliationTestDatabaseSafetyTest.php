<?php

use Symfony\Component\Process\Process;
use Tests\TestDatabaseSafety;

/** @return array<string, string|false> */
function reconciliationTestEnvironment(): array
{
    return [
        'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3307',
        'DB_DATABASE' => 'configurator_reconciliation_test', 'DB_USERNAME' => 'configurator_reconciliation_test',
        'CATALOG_MYSQL_TEST_PROFILE' => 'reconciliation',
        'DB_URL' => false, 'DATABASE_URL' => false, 'DB_SOCKET' => false, 'APP_CONFIG_CACHE' => false,
        'APP_BASE_PATH' => false, 'LARAVEL_STORAGE_PATH' => false,
    ];
}

test('reconciliation bootstrap admits only its exact disposable test target before application boot', function (array $override, bool $accepted) {
    $process = new Process([PHP_BINARY, 'tests/bootstrap-mysql.php'], dirname(__DIR__, 2), [...reconciliationTestEnvironment(), ...$override]);
    $process->run();
    expect($process->isSuccessful())->toBe($accepted);
    if (! $accepted) {
        expect($process->getErrorOutput().$process->getOutput())->toContain('Unsafe catalog MySQL test configuration');
    }
})->with([
    'exact test target' => [[], true],
    'browser database' => [['DB_DATABASE' => 'configurator_reconciliation_qa'], false],
    'browser user' => [['DB_USERNAME' => 'configurator_reconciliation_qa'], false],
    'catalog database' => [['DB_DATABASE' => 'configurator_catalog_test'], false],
    'rules user' => [['DB_USERNAME' => 'configurator_rules_test'], false],
    'development database' => [['DB_DATABASE' => 'configurator_catalog_dev'], false],
    'admin account' => [['DB_USERNAME' => 'root'], false],
    'other host' => [['DB_HOST' => 'localhost'], false],
    'other port' => [['DB_PORT' => '3306'], false],
    'unselected profile' => [['CATALOG_MYSQL_TEST_PROFILE' => false], false],
    'other profile' => [['CATALOG_MYSQL_TEST_PROFILE' => 'rules'], false],
    'unknown profile' => [['CATALOG_MYSQL_TEST_PROFILE' => 'qa'], false],
    'url override' => [['DB_URL' => 'mysql://root@127.0.0.1/live'], false],
    'socket override' => [['DB_SOCKET' => '/tmp/mysql.sock'], false],
    'configuration override' => [['APP_CONFIG_CACHE' => '/tmp/config.php'], false],
]);

test('reconciliation profile refuses conflicting environment sources', function () {
    expect(fn () => TestDatabaseSafety::assertEnvironment(reconciliationTestEnvironment(), ['CATALOG_MYSQL_TEST_PROFILE' => 'catalog'], [], true))
        ->toThrow(RuntimeException::class, 'conflicting MySQL test profiles');
    expect(fn () => TestDatabaseSafety::assertEnvironment(reconciliationTestEnvironment(), [], ['DB_DATABASE' => 'configurator_reconciliation_qa'], true))
        ->toThrow(RuntimeException::class, 'differs from the disposable target');
});

test('reconciliation profile checks resolved targets and overrides independently of the connection alias without opening PDO', function (array $override, bool $accepted) {
    $connection = [
        'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => '3307',
        'database' => 'configurator_reconciliation_test', 'username' => 'configurator_reconciliation_test', 'name' => 'custom_alias',
    ];
    $code = 'require "tests/bootstrap-mysql.php"; Tests\\TestDatabaseSafety::assertConnection(json_decode($argv[1], true), true); echo "SAFE_TARGET";';
    $process = new Process([PHP_BINARY, '-r', $code, json_encode([...$connection, ...$override], JSON_THROW_ON_ERROR)], dirname(__DIR__, 2), reconciliationTestEnvironment());
    $process->run();
    expect($process->isSuccessful())->toBe($accepted);
    if ($accepted) {
        expect($process->getOutput())->toBe('SAFE_TARGET');
    } else {
        expect($process->getErrorOutput().$process->getOutput())->toContain('Unsafe catalog MySQL test configuration');
    }
})->with([
    'exact target through alias' => [[], true],
    'browser database' => [['database' => 'configurator_reconciliation_qa'], false],
    'browser user' => [['username' => 'configurator_reconciliation_qa'], false],
    'rules database' => [['database' => 'configurator_rules_test'], false],
    'root account' => [['username' => 'root'], false],
    'other port' => [['port' => 3306], false],
    'read override' => [['read' => ['database' => 'configurator_reconciliation_qa']], false],
    'write override' => [['write' => ['database' => 'configurator_reconciliation_qa']], false],
    'direct override' => [['direct' => ['database' => 'configurator_reconciliation_qa']], false],
    'url override' => [['url' => 'mysql://root@127.0.0.1/live'], false],
    'socket override' => [['unix_socket' => '/tmp/mysql.sock'], false],
    'PDO options override' => [['options' => [1002 => 'USE live']], false],
]);

test('adding reconciliation keeps existing catalog and rules target selection strict', function (string $profile, string $target) {
    $environment = [...reconciliationTestEnvironment(), 'CATALOG_MYSQL_TEST_PROFILE' => $profile, 'DB_DATABASE' => $target, 'DB_USERNAME' => $target];
    TestDatabaseSafety::assertEnvironment($environment, [], [], true);
    expect(fn () => TestDatabaseSafety::assertEnvironment([...$environment, 'DB_DATABASE' => 'configurator_reconciliation_test'], [], [], true))
        ->toThrow(RuntimeException::class);
})->with([
    'catalog' => ['catalog', 'configurator_catalog_test'],
    'rules' => ['rules', 'configurator_rules_test'],
]);

test('a late QA alias is refused by the real connection factory before PDO is opened', function () {
    $code = <<<'PHP'
require 'tests/bootstrap-mysql.php';
$container = new Illuminate\Container\Container;
$target = ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => '3307', 'database' => 'configurator_reconciliation_test', 'username' => 'configurator_reconciliation_test'];
$container->instance('config', new Illuminate\Config\Repository(['database' => ['connections' => ['qa_alias' => [...$target, 'database' => 'configurator_reconciliation_qa']]]]));
$factory = new Tests\TestDatabaseConnectionFactory($container, true);
$factory->make($target, 'qa_alias');
echo 'PDO_REACHED';
PHP;
    $process = new Process([PHP_BINARY, '-r', $code], dirname(__DIR__, 2), reconciliationTestEnvironment());
    $process->run();
    expect($process->isSuccessful())->toBeFalse();
    expect($process->getErrorOutput().$process->getOutput())
        ->toContain('resolved connection database differs from the disposable target')
        ->not->toContain('PDO_REACHED');
});

test('the persistent QA tuple cannot enter any test environment mode', function (?string $profile) {
    $environment = [...reconciliationTestEnvironment(), 'DB_DATABASE' => 'configurator_reconciliation_qa', 'DB_USERNAME' => 'configurator_reconciliation_qa'];
    if ($profile === null) {
        unset($environment['CATALOG_MYSQL_TEST_PROFILE']);
    } else {
        $environment['CATALOG_MYSQL_TEST_PROFILE'] = $profile;
    }
    expect(fn () => TestDatabaseSafety::assertEnvironment($environment, [], [], $profile !== null))
        ->toThrow(RuntimeException::class);
})->with(['SQLite' => [null], 'catalog' => ['catalog'], 'rules' => ['rules'], 'reconciliation' => ['reconciliation']]);
