<?php

namespace Tests;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Connection;
use Illuminate\Database\Connectors\ConnectionFactory;

final class TestDatabaseConnectionFactory extends ConnectionFactory
{
    public function __construct(Container $container, private bool $mysqlRun)
    {
        parent::__construct($container);
    }

    /** @param array<string, mixed> $config */
    public function make(array $config, mixed $name = null): Connection
    {
        if (is_string($name) && $this->container->bound('config')) {
            $declared = $this->container['config']->get('database.connections.'.$name);
            if (is_string($declared)) {
                TestDatabaseSafety::assertConnection(['url' => $declared], $this->mysqlRun);
            }
            if (is_array($declared)) {
                TestDatabaseSafety::assertConnection($declared, $this->mysqlRun);
            }
        }
        TestDatabaseSafety::assertConnection($config, $this->mysqlRun);

        return parent::make($config, $name);
    }
}
