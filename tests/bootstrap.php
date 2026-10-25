<?php

use Tests\TestDatabaseSafety;

require_once __DIR__.'/TestDatabaseSafety.php';

TestDatabaseSafety::assertBeforeBootstrap();

require_once dirname(__DIR__).'/vendor/autoload.php';
