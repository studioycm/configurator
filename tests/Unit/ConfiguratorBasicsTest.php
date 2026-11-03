<?php

use App\ConditionSource;
use App\ConfigInputType;
use App\RuleKind;
use Filament\Forms\Components\Select;
use Filament\Support\Contracts\HasLabel;
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

test('Rule kinds retain backed identities and expose labels through the native Filament enum options', function () {
    foreach (RuleKind::cases() as $case) {
        expect($case)->toBeInstanceOf(HasLabel::class);
    }
    expect(RuleKind::Mapping->getLabel())->toBe('Mapping')
        ->and(RuleKind::Advanced->getLabel())->toBe('Advanced')
        ->and(Select::make('kind')->options(RuleKind::class)->getOptions())->toBe(['Mapping' => 'Mapping', 'Advanced' => 'Advanced'])
        ->and(array_map(fn (RuleKind $case): string => $case->value, RuleKind::cases()))->toBe(['Mapping', 'Advanced'])
        ->and(RuleKind::from('Mapping'))->toBe(RuleKind::Mapping)
        ->and(RuleKind::tryFrom('Advanced'))->toBe(RuleKind::Advanced)
        ->and(RuleKind::tryFrom('Other'))->toBeNull();
});
