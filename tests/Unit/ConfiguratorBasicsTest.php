<?php

use App\ConditionSource;
use App\ConfigInputType;
use App\RuleKind;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, DatabaseMigrations::class);

test('core configurator tables are migrated', function () {
    $tables = [
        'groups',
        'products',
        'configurators',
        'attributes',
        'values',
        'options',
        'configurator_attributes',
        'configurator_options',
        'configurator_rules',
        'rule_conditions',
        'rule_effects',
        'mapping_sets',
    ];

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }
});

test('enums resolve expected values', function () {
    expect(ConditionSource::SelectionCode->value)->toBe('SelectionCode')
        ->and(RuleKind::Mapping->value)->toBe('Mapping')
        ->and(ConfigInputType::Select->value)->toBe('select')
        ->and(ConfigInputType::Toggle->value)->toBe('toggle');
});
