<?php

use App\Models\Attribute;
use App\Models\CatalogContextSettings;
use App\Models\Configurator;
use App\Models\ConfiguratorAttribute;
use App\Models\ConfiguratorOption;
use App\Models\ConfiguratorRule;
use App\Models\Option;
use App\Models\RuleCondition;
use App\Models\RuleConditionGroup;
use App\Models\RuleEffect;
use App\Models\User;
use App\Models\Value;
use App\Services\ConfiguratorDefinitionLoader;
use App\Services\ConfiguratorRuleExchange;
use App\Services\ConfiguratorRuleImport;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

function ruleImportFixture(): array
{
    $configurator = Configurator::factory()->create();
    $attribute = Attribute::factory()->create(['key' => 'body']);
    $included = ConfiguratorAttribute::factory()->for($configurator)->for($attribute)->create();
    $value = Value::factory()->create(['semantic_id' => ' 001|a\\b ']);
    $option = Option::factory()->for($attribute)->for($value)->create(['code' => '01']);
    $includedOption = ConfiguratorOption::factory()->for($included, 'configuratorAttribute')->for($option)->create();
    $included->update(['default_configurator_option_id' => $includedOption->id]);
    $rule = ConfiguratorRule::factory()->for($configurator)->create();
    RuleCondition::factory()->for($rule, 'rule')->create(['operand' => ' 001|x\\y ']);
    RuleEffect::factory()->for($rule, 'rule')->create(['target_configurator_attribute_id' => $included->id, 'display_value' => "=שלום\n 001 "]);
    $rows = app(ConfiguratorRuleExchange::class)->snapshot($rule)['rows'];
    $rows = array_map(fn (array $row): array => array_replace($row, ['import' => '1']), $rows);

    return [$configurator, $rule, $rows, $included, $includedOption];
}

function ruleImportCreateRows(array $rows, string $key = 'copy', string $priority = '1'): array
{
    return array_map(fn (array $row): array => array_replace($row, ['operation' => 'Create', 'rule_key' => $key, 'rule_id' => '', 'expected_revision' => '', 'priority' => $priority]), $rows);
}

function ruleImportPreview(User $actor, Configurator $configurator, array $rows, array $options = []): array
{
    return app(ConfiguratorRuleImport::class)->previewRows($actor, $rows, $options, $configurator->id);
}

test('preview makes no writes and Update roundtrips exact text and child identities', function () {
    [$configurator, $rule, $rows] = ruleImportFixture();
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $before = app(ConfiguratorDefinitionLoader::class)->draft($configurator);

    $preview = ruleImportPreview($actor, $configurator, $rows, ['allow_update' => true]);

    expect($preview['errors'])->toBe([]);
    expect($preview['can_import'])->toBeTrue();
    expect(app(ConfiguratorDefinitionLoader::class)->draft($configurator))->toBe($before);
    expect(app(ConfiguratorRuleImport::class)->apply($actor, $preview['token'], $configurator->id))->toBe(['created' => 0, 'updated' => 1, 'configurators' => 1]);
    expect(app(ConfiguratorDefinitionLoader::class)->draft($configurator))->toBe($before);
});

test('Create aliases allocate fresh IDs and keep new rules disabled while preserving absent rules', function () {
    [$configurator, $rule, $rows] = ruleImportFixture();
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $preview = ruleImportPreview($actor, $configurator, ruleImportCreateRows($rows));
    expect($preview['errors'])->toBe([]);

    app(ConfiguratorRuleImport::class)->apply($actor, $preview['token'], $configurator->id);

    $copy = $configurator->rules()->whereKeyNot($rule->id)->sole();
    expect($copy->is_active)->toBeFalse();
    expect($copy->conditions()->sole()->id)->not->toBe($rule->conditions()->sole()->id);
    expect($copy->effects()->sole()->id)->not->toBe($rule->effects()->sole()->id);
    expect($rule->fresh()->label)->toBe($rows[0]['rule_label']);
});

test('Update previews child deletions and does not adopt foreign child IDs', function () {
    [$configurator, $rule, $rows] = ruleImportFixture();
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $foreign = RuleEffect::factory()->create();
    $deleted = $rule->conditions()->sole()->id;
    $rows = [$rows[1]];
    $rows[0]['row_key'] = 'e_'.$foreign->id;
    $preview = ruleImportPreview($actor, $configurator, $rows, ['allow_update' => true]);
    expect($preview['errors'])->toBe([]);
    expect($preview['configurators'][0]['changes'][0]['deleted_children'])->toContain('c_'.$deleted);

    app(ConfiguratorRuleImport::class)->apply($actor, $preview['token'], $configurator->id);

    expect($rule->conditions()->count())->toBe(0);
    expect($rule->effects()->sole()->id)->not->toBe($foreign->id);
    $this->assertModelExists($foreign);
});

test('selected groups reject inconsistent or invalid content without writes', function (string $mutation) {
    [$configurator, $rule, $rows] = ruleImportFixture();
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $rows = ruleImportCreateRows($rows);
    match ($mutation) {
        'mixed flag' => $rows[1]['import'] = '0',
        'metadata' => $rows[1]['rule_label'] = 'different',
        'row key' => $rows[1]['row_key'] = $rows[0]['row_key'],
        'row order' => $rows[1]['row_order'] = $rows[0]['row_order'],
        'irrelevant' => $rows[0]['mapping_label'] = 'stale paste',
        'reference' => $rows[1]['target_attribute_key'] = 'missing',
        'priority' => $rows = ruleImportCreateRows($rows, priority: '0'),
        'escape' => [$rows[0]['condition_operator'], $rows[0]['condition_values']] = ['In', 'bad\\x'],
        'operation' => $rows = array_map(fn ($row) => array_replace($row, ['operation' => 'Upsert']), $rows),
    };

    $preview = ruleImportPreview($actor, $configurator, $rows);

    expect($preview['can_import'])->toBeFalse();
    expect($preview['errors'])->not->toBeEmpty();
    expect(fn () => app(ConfiguratorRuleImport::class)->apply($actor, $preview['token'], $configurator->id))->toThrow(ValidationException::class);
    expect($configurator->rules()->count())->toBe(1);
})->with(['mixed flag', 'metadata', 'row key', 'row order', 'irrelevant', 'reference', 'priority', 'escape', 'operation']);

test('complete import zero groups are skipped and Update requires explicit permission and revision', function () {
    [$configurator, $rule, $rows] = ruleImportFixture();
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $skipped = array_map(fn ($row) => array_replace($row, ['import' => '0']), $rows);
    expect(ruleImportPreview($actor, $configurator, $skipped)['skipped_groups'])->toBe(1);
    expect(ruleImportPreview($actor, $configurator, $rows)['can_import'])->toBeFalse();
    $rows = array_map(fn ($row) => array_replace($row, ['expected_revision' => str_repeat('0', 64)]), $rows);
    expect(ruleImportPreview($actor, $configurator, $rows, ['allow_update' => true])['can_import'])->toBeFalse();
});

test('tokens are actor and scope bound expiring and single use', function () {
    [$configurator, $rule, $rows] = ruleImportFixture();
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $other = User::factory()->create(['email' => 'beny.cr@gmail.com']);
    $preview = ruleImportPreview($actor, $configurator, ruleImportCreateRows($rows));
    expect(fn () => app(ConfiguratorRuleImport::class)->apply($other, $preview['token'], $configurator->id))->toThrow(ValidationException::class);
    expect(fn () => app(ConfiguratorRuleImport::class)->apply($actor, $preview['token']))->toThrow(ValidationException::class);
    app(ConfiguratorRuleImport::class)->apply($actor, $preview['token'], $configurator->id);
    expect(fn () => app(ConfiguratorRuleImport::class)->apply($actor, $preview['token'], $configurator->id))->toThrow(ValidationException::class);
    $preview = ruleImportPreview($actor, $configurator, ruleImportCreateRows($rows, 'later', '2'));
    $this->travel(16)->minutes();
    expect(fn () => app(ConfiguratorRuleImport::class)->apply($actor, $preview['token'], $configurator->id))->toThrow(ValidationException::class);
    expect($configurator->rules()->count())->toBe(2);
});

test('changed definition references or context invalidate a preview', function (string $change) {
    [$configurator, $rule, $rows, $attribute, $option] = ruleImportFixture();
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $preview = ruleImportPreview($actor, $configurator, ruleImportCreateRows($rows));
    match ($change) {
        'definition' => $rule->update(['label' => 'changed']),
        'reference' => $option->option->value->update(['semantic_id' => 'changed']),
        'context' => CatalogContextSettings::current()->update(['choices' => ['territory' => [['value' => 'NEW', 'label' => 'New']], 'application' => []]]),
    };

    expect(fn () => app(ConfiguratorRuleImport::class)->apply($actor, $preview['token'], $configurator->id))->toThrow(ValidationException::class, 'changed');
    expect($configurator->rules()->count())->toBe(1);
})->with(['definition', 'reference', 'context']);

test('apply reauthorizes and global imports require explicit allowed Configurators', function () {
    [$configurator, $rule, $rows] = ruleImportFixture();
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    expect(fn () => app(ConfiguratorRuleImport::class)->previewRows($actor, $rows))->toThrow(ValidationException::class);
    $preview = ruleImportPreview($actor, $configurator, ruleImportCreateRows($rows));
    Gate::define('manage-catalog', fn () => false);
    expect(fn () => app(ConfiguratorRuleImport::class)->apply($actor, $preview['token'], $configurator->id))->toThrow(AuthorizationException::class);
    expect(fn () => ruleImportPreview($actor, $configurator, $rows))->toThrow(AuthorizationException::class);
    expect($configurator->rules()->count())->toBe(1);
});

test('actual CSV and XLSX Rules sheets preserve exact cells and ignore helpers', function (string $format) {
    [$configurator, $rule, $rows] = ruleImportFixture();
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $path = sys_get_temp_dir().'/rules-import-'.bin2hex(random_bytes(6)).'.'.$format;
    $headers = [...ConfiguratorRuleExchange::COLUMNS, 'helper_note'];
    $values = array_map(fn ($row) => [...array_values($row), 'ignored'], $rows);
    if ($format === 'csv') {
        $stream = fopen($path, 'w');
        foreach ([$headers, ...$values] as $row) {
            fputcsv($stream, $row, escape: '');
        }
        fclose($stream);
    } else {
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('Review');
        $writer->addRow(Row::fromValues(['not rules']));
        $writer->addNewSheetAndMakeItCurrent()->setName('Rules');
        foreach ([$headers, ...$values] as $row) {
            $writer->addRow(new Row(array_map(fn (string $value) => new StringCell($value, null), $row)));
        }
        $writer->close();
    }
    try {
        $preview = app(ConfiguratorRuleImport::class)->previewFile($actor, $path, ['allow_update' => true], $configurator->id);
        expect($preview['errors'])->toBe([]);
        expect($preview['configurators'][0]['changes'][0]['changes'])->toBe([]);
    } finally {
        unlink($path);
    }
})->with(['csv', 'xlsx']);

test('malformed headers unknown columns and oversized input fail before preview', function () {
    [$configurator, $rule, $rows] = ruleImportFixture();
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $rows[0]['unexpected'] = '';
    expect(fn () => ruleImportPreview($actor, $configurator, $rows))->toThrow(ValidationException::class);
    unset($rows[0]['unexpected'], $rows[0]['mapping_label']);
    expect(fn () => ruleImportPreview($actor, $configurator, $rows))->toThrow(ValidationException::class);
    expect(fn () => ruleImportPreview($actor, $configurator, array_fill(0, 5001, $rows[1])))->toThrow(ValidationException::class);
});

test('Mapping roundtrip preserves gates labels orders behavior and owned set IDs', function () {
    [$configurator, $rule, $rows, $driver, $source] = ruleImportFixture();
    $targetAttribute = Attribute::factory()->create(['key' => 'bolt']);
    $target = ConfiguratorAttribute::factory()->for($configurator)->for($targetAttribute, 'attribute')->create(['display_order' => 1, 'code_order' => 1]);
    $allowed = ConfiguratorOption::factory()->for($target, 'configuratorAttribute')->for(Option::factory()->for($targetAttribute, 'attribute'))->create();
    $target->update(['default_configurator_option_id' => $allowed->id]);
    $rule->effects()->delete();
    $rule->update(['kind' => 'Mapping', 'driver_configurator_attribute_id' => $driver->id, 'target_configurator_attribute_id' => $target->id]);
    $set = $rule->mappingSets()->create(['label' => ' קבוצה ', 'sort_order' => 7, 'disallowed_target_behavior' => 'Hide']);
    $set->sources()->create(['rule_id' => $rule->id, 'configurator_option_id' => $source->id]);
    $set->targets()->create(['rule_id' => $rule->id, 'configurator_option_id' => $allowed->id]);
    $rows = app(ConfiguratorRuleExchange::class)->snapshot($rule)['rows'];
    $rows = array_map(fn ($row) => array_replace($row, ['import' => '1']), $rows);
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $before = app(ConfiguratorDefinitionLoader::class)->draft($configurator);

    $preview = ruleImportPreview($actor, $configurator, $rows, ['allow_update' => true]);
    expect($preview['errors'])->toBe([]);
    app(ConfiguratorRuleImport::class)->apply($actor, $preview['token'], $configurator->id);
    expect(app(ConfiguratorDefinitionLoader::class)->draft($configurator))->toBe($before);

    $preview = ruleImportPreview($actor, $configurator, ruleImportCreateRows($rows));
    expect($preview['errors'])->toBe([]);
    app(ConfiguratorRuleImport::class)->apply($actor, $preview['token'], $configurator->id);
    $copy = $configurator->rules()->whereKeyNot($rule->id)->sole();
    expect($copy->mappingSets()->sole()->id)->not->toBe($set->id);
    expect($copy->mappingSets()->sole()->label)->toBe(' קבוצה ');
});

test('Advanced roundtrip preserves all effects grouped SelectionCode and empty or escaped Product operands', function () {
    [$configurator, $rule, $rows, $attribute, $option] = ruleImportFixture();
    $rule->conditions()->sole()->update(['operand' => '']);
    RuleCondition::factory()->for($rule, 'rule')->create(['operator' => 'In', 'operand' => ['', 'a|b', 'c\\d'], 'sort_order' => 1]);
    $group = RuleConditionGroup::factory()->for($rule, 'rule')->create(['operator' => 'Any']);
    $condition = RuleCondition::factory()->for($rule, 'rule')->create(['source_kind' => 'SelectionCode', 'source_configurator_attribute_id' => $attribute->id, 'condition_group_id' => $group->id, 'property_key' => null, 'operand' => null]);
    $condition->optionReferences()->create(['configurator_option_id' => $option->id]);
    $effectTarget = Attribute::factory()->create(['key' => 'target']);
    $target = ConfiguratorAttribute::factory()->for($configurator)->for($effectTarget, 'attribute')->create(['display_order' => 1, 'code_order' => 1]);
    $targetOption = ConfiguratorOption::factory()->for($target, 'configuratorAttribute')->for(Option::factory()->for($effectTarget, 'attribute'))->create();
    $target->update(['default_configurator_option_id' => $targetOption->id]);
    foreach (['AllowOptions', 'ExcludeOptions', 'HideOptions', 'DisableOptions', 'HideAttribute', 'SetDisplayValue', 'SetHint'] as $kind) {
        $effect = RuleEffect::factory()->for($rule, 'rule')->create(['kind' => $kind, 'target_configurator_attribute_id' => $target->id, 'target_scope' => $kind === 'HideAttribute' ? 'Attribute' : 'Options', 'display_value' => str_starts_with($kind, 'Set') ? ' Exact | text\\ ' : null]);
        if ($kind !== 'HideAttribute') {
            $effect->optionReferences()->create(['configurator_option_id' => $targetOption->id]);
        }
    }
    $rows = array_map(fn ($row) => array_replace($row, ['import' => '1']), app(ConfiguratorRuleExchange::class)->snapshot($rule)['rows']);
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $before = app(ConfiguratorDefinitionLoader::class)->draft($configurator);

    $preview = ruleImportPreview($actor, $configurator, $rows, ['allow_update' => true]);
    expect($preview['errors'])->toBe([]);
    expect($preview['configurators'][0]['changes'][0]['deleted_children'])->toBe([]);
    app(ConfiguratorRuleImport::class)->apply($actor, $preview['token'], $configurator->id);
    expect(app(ConfiguratorDefinitionLoader::class)->draft($configurator))->toBe($before);
});

test('duplicate Update targets and scope tampering are blocked', function () {
    [$configurator, $rule, $rows] = ruleImportFixture();
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $duplicate = array_map(fn ($row) => array_replace($row, ['rule_key' => 'duplicate']), $rows);
    $preview = ruleImportPreview($actor, $configurator, [...$rows, ...$duplicate], ['allow_update' => true]);
    expect($preview['can_import'])->toBeFalse();
    expect(implode(' ', $preview['errors']))->toContain('Duplicate Update');
    $other = Configurator::factory()->create();
    $preview = ruleImportPreview($actor, $other, ruleImportCreateRows($rows), ['configurator_ids' => [$configurator->id]]);
    expect($preview['can_import'])->toBeFalse();
    expect(implode(' ', $preview['errors']))->toContain('outside');
});

test('file apply rolls back earlier Configurators when a later save fails', function () {
    [$configurator, $rule, $rows, $attribute, $option] = ruleImportFixture();
    $other = Configurator::factory()->create();
    $included = ConfiguratorAttribute::factory()->for($other, 'configurator')->for($attribute->attribute)->create();
    $includedOption = ConfiguratorOption::factory()->for($included, 'configuratorAttribute')->for($option->option)->create();
    $included->update(['default_configurator_option_id' => $includedOption->id]);
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $first = ruleImportCreateRows($rows);
    $second = array_map(fn ($row) => array_replace($row, ['configurator_id' => (string) $other->id, 'priority' => '0']), $first);
    $preview = app(ConfiguratorRuleImport::class)->previewRows($actor, [...$first, ...$second], ['configurator_ids' => [$configurator->id, $other->id]]);
    expect($preview['errors'])->toBe([]);
    ConfiguratorRule::creating(function (ConfiguratorRule $creating) use ($other): void {
        if ($creating->configurator_id === $other->id) {
            throw new RuntimeException('second save fails');
        }
    });

    expect(fn () => app(ConfiguratorRuleImport::class)->apply($actor, $preview['token']))->toThrow(RuntimeException::class, 'second save fails');

    expect($configurator->rules()->count())->toBe(1);
    expect($other->rules()->count())->toBe(0);
});

test('preview exposes exact old and new semantic references and explicit spreadsheet restoration', function () {
    [$configurator, $rule, $rows, $attribute, $option] = ruleImportFixture();
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $rows[1]['display_value'] = "'=changed";
    $preview = ruleImportPreview($actor, $configurator, $rows, ['allow_update' => true]);
    expect(implode(' ', $preview['configurators'][0]['changes'][0]['changes']))->toContain('body', "'=changed", 'שלום');
    app(ConfiguratorRuleImport::class)->apply($actor, $preview['token'], $configurator->id);
    expect($rule->effects()->sole()->display_value)->toBe("'=changed");
    $rows = array_map(fn ($row) => array_replace($row, ['import' => '1']), app(ConfiguratorRuleExchange::class)->snapshot($rule->fresh())['rows']);
    $preview = ruleImportPreview($actor, $configurator, $rows, ['allow_update' => true, 'restore_spreadsheet_text' => true]);
    app(ConfiguratorRuleImport::class)->apply($actor, $preview['token'], $configurator->id);
    expect($rule->effects()->sole()->display_value)->toBe('=changed');
});

test('preview reports every missing Attribute and Value reference in a selected group', function () {
    [$configurator, $rule, $rows] = ruleImportFixture();
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $rows[0] = array_replace($rows[0], ['condition_source' => 'SelectionOption', 'condition_field' => 'body', 'condition_operator' => 'In', 'condition_values' => 'missing_one|missing_two']);
    $rows[1]['target_attribute_key'] = 'missing_attribute';

    $preview = ruleImportPreview($actor, $configurator, $rows, ['allow_update' => true]);

    expect(implode(' ', $preview['errors']))->toContain('missing_one', 'missing_two', 'missing_attribute');
});

test('CSV accepts UTF8 BOM before a quoted first header', function () {
    [$configurator, $rule, $rows] = ruleImportFixture();
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $path = tempnam(sys_get_temp_dir(), 'rules-bom-');
    $stream = fopen($path, 'wb');
    fwrite($stream, "\xEF\xBB\xBF\"format_version\",".implode(',', array_slice(ConfiguratorRuleExchange::COLUMNS, 1))."\n");
    foreach ($rows as $row) {
        fputcsv($stream, $row, escape: '');
    }
    fclose($stream);
    try {
        $preview = app(ConfiguratorRuleImport::class)->previewFile($actor, $path, ['allow_update' => true], $configurator->id);
        expect($preview['can_import'])->toBeTrue();
    } finally {
        unlink($path);
    }
});

test('skipped groups still require consistent shared metadata', function () {
    [$configurator, $rule, $rows] = ruleImportFixture();
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $rows = array_map(fn ($row) => array_replace($row, ['import' => '0']), $rows);
    $rows[1]['rule_label'] = 'different';
    $preview = ruleImportPreview($actor, $configurator, $rows);
    expect(implode(' ', $preview['errors']))->toContain('rule_label');
});

test('Update rejects effect ordering that cannot preserve saved child identities', function (string $order) {
    [$configurator, $rule, $rows, $attribute] = ruleImportFixture();
    RuleEffect::factory()->for($rule, 'rule')->create(['target_configurator_attribute_id' => $attribute->id, 'kind' => 'SetHint', 'display_value' => 'second']);
    $rows = array_map(fn ($row) => array_replace($row, ['import' => '1']), app(ConfiguratorRuleExchange::class)->snapshot($rule)['rows']);
    if ($order === 'reverse saved') {
        [$rows[1]['row_order'], $rows[2]['row_order']] = [$rows[2]['row_order'], $rows[1]['row_order']];
    } else {
        $rows[1]['row_key'] = 'new_effect';
    }
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $before = app(ConfiguratorDefinitionLoader::class)->draft($configurator);

    $preview = ruleImportPreview($actor, $configurator, $rows, ['allow_update' => true]);

    expect($preview['can_import'])->toBeFalse();
    expect(implode(' ', $preview['errors']))->toContain('effect order');
    expect(app(ConfiguratorDefinitionLoader::class)->draft($configurator))->toBe($before);
})->with(['reverse saved', 'new before saved']);

test('installation IDs above signed32 remain valid import scopes', function () {
    $configurator = Configurator::factory()->create(['id' => 2147483648]);
    $attribute = ConfiguratorAttribute::factory()->for($configurator)->create();
    $option = ConfiguratorOption::factory()->for($attribute, 'configuratorAttribute')->for(Option::factory()->for($attribute->attribute))->create();
    $attribute->update(['default_configurator_option_id' => $option->id]);
    $rule = ConfiguratorRule::factory()->for($configurator)->create(['id' => 2147483648]);
    RuleEffect::factory()->for($rule, 'rule')->create(['target_configurator_attribute_id' => $attribute->id]);
    $rows = array_map(fn ($row) => array_replace($row, ['import' => '1']), app(ConfiguratorRuleExchange::class)->snapshot($rule)['rows']);
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);

    $preview = ruleImportPreview($actor, $configurator, $rows, ['allow_update' => true]);

    expect($preview['errors'])->toBe([]);
    expect($preview['can_import'])->toBeTrue();
});

test('actual XLSX formula cells are rejected with paste values guidance', function (bool $blankRow) {
    [$configurator, $rule, $rows] = ruleImportFixture();
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $path = sys_get_temp_dir().'/rules-formula-'.bin2hex(random_bytes(6)).'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->getCurrentSheet()->setName('Rules');
    $writer->addRow(Row::fromValues(ConfiguratorRuleExchange::COLUMNS));
    if ($blankRow) {
        $writer->addRow(Row::fromValues(['']));
    }
    foreach ($rows as $row) {
        $writer->addRow(Row::fromValues(array_values($row)));
    }
    $writer->close();
    try {
        expect(fn () => app(ConfiguratorRuleImport::class)->previewFile($actor, $path, ['allow_update' => true], $configurator->id))->toThrow(ValidationException::class, 'Paste values');
    } finally {
        unlink($path);
    }
})->with(['consecutive rows' => false, 'blank physical row' => true]);

test('deleted preview actors cannot confirm using an old model instance', function () {
    [$configurator, $rule, $rows] = ruleImportFixture();
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $preview = ruleImportPreview($actor, $configurator, ruleImportCreateRows($rows));
    $actor->delete();

    expect(fn () => app(ConfiguratorRuleImport::class)->apply($actor, $preview['token'], $configurator->id))->toThrow(AuthorizationException::class);
    expect($configurator->rules()->count())->toBe(1);
});

test('file size and missing Rules sheet are rejected', function () {
    [$configurator] = ruleImportFixture();
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $path = tempnam(sys_get_temp_dir(), 'rules-size-');
    $stream = fopen($path, 'wb');
    ftruncate($stream, 10 * 1024 * 1024 + 1);
    fclose($stream);
    try {
        expect(fn () => app(ConfiguratorRuleImport::class)->previewFile($actor, $path, [], $configurator->id))->toThrow(ValidationException::class, '10 MB');
    } finally {
        unlink($path);
    }
    $path = sys_get_temp_dir().'/rules-missing-'.bin2hex(random_bytes(6)).'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->getCurrentSheet()->setName('Rules_helpers');
    $writer->addRow(Row::fromValues(ConfiguratorRuleExchange::COLUMNS));
    $writer->close();
    try {
        expect(fn () => app(ConfiguratorRuleImport::class)->previewFile($actor, $path, [], $configurator->id))->toThrow(ValidationException::class, 'named Rules');
    } finally {
        unlink($path);
    }
});

test('whole-definition compilation rejects cycles introduced across selected groups', function () {
    [$configurator, $rule, $rows, $body, $bodyValue] = ruleImportFixture();
    $other = ConfiguratorAttribute::factory()->for($configurator)->for(Attribute::factory()->state(['key' => 'bolt']))->create(['display_order' => 1, 'code_order' => 1]);
    $otherValue = ConfiguratorOption::factory()->for($other, 'configuratorAttribute')->for(Option::factory()->for($other->attribute))->create();
    $other->update(['default_configurator_option_id' => $otherValue->id]);
    $first = ruleImportCreateRows($rows, 'forward', '1');
    $first[0] = array_replace($first[0], ['condition_source' => 'SelectionOption', 'condition_field' => 'body', 'condition_operator' => 'Equals', 'condition_values' => app(ConfiguratorRuleExchange::class)->encodeList([$bodyValue->option->value->semantic_id])]);
    $first[1] = array_replace($first[1], ['target_attribute_key' => 'bolt', 'effect_kind' => 'DisableOptions', 'target_scope' => 'Options', 'target_values' => $otherValue->option->value->semantic_id, 'display_value' => '']);
    $second = ruleImportCreateRows($first, 'reverse', '2');
    $second[0] = array_replace($second[0], ['condition_field' => 'bolt', 'condition_values' => $first[1]['target_values']]);
    $second[1] = array_replace($second[1], ['target_attribute_key' => 'body', 'target_values' => $first[0]['condition_values']]);
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);

    $preview = ruleImportPreview($actor, $configurator, [...$first, ...$second]);

    expect($preview['can_import'])->toBeFalse();
    expect(implode(' ', $preview['errors']))->toContain('cycle');
    expect($configurator->rules()->count())->toBe(1);
});
