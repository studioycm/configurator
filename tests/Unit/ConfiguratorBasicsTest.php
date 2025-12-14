<?php

use App\ConfigInputType;
use App\ConfigProfileScope;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class, DatabaseMigrations::class);

test('core configurator tables are migrated', function () {
    $tables = [
        'catalog_groups',
        'product_profiles',
        'config_profiles',
        'config_attributes',
        'config_options',
        'option_rules',
        'file_attachments',
    ];

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }
});

test('enums resolve expected values', function () {
    expect(ConfigProfileScope::ConfigurationSelection->value)->toBe('configuration_selection')
        ->and(ConfigInputType::Select->value)->toBe('select');
});
