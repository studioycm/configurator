<?php

use App\Filament\Exports\AttributeExporter;
use App\Filament\Exports\AttributeValueExporter;
use App\Filament\Exports\ValueExporter;
use App\Filament\Imports\AttributeImporter;
use App\Filament\Imports\AttributeValueImporter;
use App\Filament\Imports\ValueImporter;
use App\Filament\Pages\DataExchange;
use App\Filament\Resources\Values\Pages\ListValues;
use App\Models\Attribute;
use App\Models\ConfiguratorOption;
use App\Models\Option;
use App\Models\User;
use App\Models\Value;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Models\Export;
use Filament\Actions\Imports\Models\Import;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->actor = User::factory()->create(['email' => 'beny.cr@gmail.com']);
    $this->actingAs($this->actor);
    $this->import = (new Import)->setRelation('user', $this->actor);
});

test('Value imports use semantic identities and preserve labels and JSON tags', function () {
    Value::factory()->create(['label' => 'Polypropylene', 'semantic_id' => 'polypropylene_float']);

    $record = ValueImporter::test(import: $this->import)->import([
        'semantic_id' => 'polypropylene_body', 'label' => 'Polypropylene',
        'description' => 'Body material', 'tags' => '["legacy|d060","material"]',
    ])->assertImported()->getRecord();

    expect($record->fresh()->only(['semantic_id', 'label', 'description', 'tags']))->toBe([
        'semantic_id' => 'polypropylene_body', 'label' => 'Polypropylene',
        'description' => 'Body material', 'tags' => ['legacy|d060', 'material'],
    ]);
    $this->assertDatabaseCount('values', 2);
});

test('existing Values require an explicit update and unmapped metadata stays unchanged', function () {
    $value = Value::factory()->create(['semantic_id' => 'viton', 'label' => 'Viton', 'description' => 'Keep this', 'tags' => ['seal']]);
    $option = Option::factory()->for($value)->create(['code' => 'Vt']);
    $row = ['semantic_id' => 'viton', 'label' => 'Viton seal'];

    ValueImporter::test(import: $this->import)->import($row)->assertHasRowFailure();
    expect($value->fresh()->label)->toBe('Viton');

    ValueImporter::test(columnMap: ['semantic_id' => 'semantic_id', 'label' => 'label'], options: ['update_existing' => true], import: $this->import)
        ->import($row)->assertImported();

    expect($value->fresh()->only(['label', 'semantic_id', 'description', 'tags']))->toBe([
        'label' => 'Viton seal', 'semantic_id' => 'viton', 'description' => 'Keep this', 'tags' => ['seal'],
    ]);
    expect($option->fresh()->code)->toBe('Vt');
});

test('unapproved review rows are skipped and invalid import flags are rejected', function () {
    ValueImporter::test(import: $this->import)->import(['import' => '0', 'semantic_id' => '', 'label' => 'Needs review'])->assertSkipped();
    ValueImporter::test(import: $this->import)->import(['import' => 'maybe', 'semantic_id' => 'nylon', 'label' => 'Nylon'])->assertHasErrors(['import']);

    $this->assertDatabaseCount('values', 0);
});

test('an optional blank tags cell imports as an empty list', function () {
    $record = ValueImporter::test(import: $this->import)->import([
        'semantic_id' => 'nylon', 'label' => 'Nylon', 'tags' => '',
    ])->assertImported()->getRecord();

    expect($record->fresh()->tags)->toBe([]);
});

test('invalid semantic identities and malformed tag data cannot be imported', function (array $row, string $field) {
    ValueImporter::test(import: $this->import)->import([...['semantic_id' => 'nylon', 'label' => 'Nylon'], ...$row])->assertHasErrors([$field]);

    $this->assertDatabaseCount('values', 0);
})->with([
    'unresolved ID' => [['semantic_id' => '?Nylon'], 'semantic_id'],
    'uppercase ID' => [['semantic_id' => 'Nylon'], 'semantic_id'],
    'tag object' => [['tags' => '{"name":"material"}'], 'tags'],
    'invalid tag JSON' => [['tags' => 'not json'], 'tags'],
]);

test('Attributes import by key and keep inactive status on a round trip', function () {
    $record = AttributeImporter::test(import: $this->import)->import(['key' => 'thread_type', 'label' => 'Thread Type', 'is_active' => '0'])->assertImported()->getRecord();

    expect($record->fresh()->only(['key', 'label', 'is_active']))->toBe(['key' => 'thread_type', 'label' => 'Thread Type', 'is_active' => false]);
    AttributeImporter::test(options: ['update_existing' => true], import: $this->import)
        ->import(['key' => 'thread_type', 'label' => 'Thread Standard', 'is_active' => '1'])->assertImported();
    expect($record->fresh()->label)->toBe('Thread Standard');
    expect($record->fresh()->is_active)->toBeTrue();
    $this->assertDatabaseCount('attributes', 1);
});

test('Attribute-Values resolve exact semantic identities without creating dependencies', function () {
    $attribute = Attribute::factory()->create(['key' => 'automatic_valve_body_material']);
    Value::factory()->create(['label' => 'Polypropylene', 'semantic_id' => 'polypropylene_float']);
    $value = Value::factory()->create(['label' => 'Polypropylene', 'semantic_id' => 'polypropylene_body']);

    $record = AttributeValueImporter::test(import: $this->import)->import([
        'attribute_key' => 'automatic_valve_body_material', 'value_semantic_id' => 'polypropylene_body',
        'code' => 'Pb', 'is_active' => '0', 'is_hidden' => '1',
    ])->assertImported()->getRecord();

    expect($record->fresh()->only(['attribute_id', 'value_id', 'code', 'is_active', 'is_hidden']))->toBe([
        'attribute_id' => $attribute->id, 'value_id' => $value->id, 'code' => 'Pb', 'is_active' => false, 'is_hidden' => true,
    ]);
    $this->assertDatabaseCount('configurator_options', 0);
    $this->assertDatabaseCount('values', 2);
});

test('Attribute-Values report missing dependencies and invalid or duplicate codes', function (array $changes, string $field) {
    Attribute::factory()->create(['key' => 'thread_type']);
    Value::factory()->create(['semantic_id' => 'bspt']);
    Option::factory()->create(['code' => 'AA']);

    AttributeValueImporter::test(import: $this->import)->import([
        ...['attribute_key' => 'thread_type', 'value_semantic_id' => 'bspt', 'code' => 'BT'], ...$changes,
    ])->assertHasErrors([$field]);

    $this->assertDatabaseCount('options', 1);
})->with([
    'missing Attribute' => [['attribute_key' => 'missing'], 'attribute_key'],
    'missing Value' => [['value_semantic_id' => 'missing'], 'value_semantic_id'],
    'label instead of identity' => [['value_semantic_id' => 'BSPT'], 'value_semantic_id'],
    'long code' => [['code' => 'BSP'], 'code'],
    'punctuation code' => [['code' => 'B!'], 'code'],
    'duplicate code' => [['code' => 'AA'], 'code'],
    'padded code' => [['code' => ' BT '], 'code'],
    'invalid active state' => [['is_active' => 'maybe'], 'is_active'],
    'invalid hidden state' => [['is_hidden' => 'maybe'], 'is_hidden'],
]);

test('Attribute-Value imports preserve existing codes and configurator memberships', function () {
    $option = Option::factory()->create(['code' => 'Ab']);
    $inclusion = ConfiguratorOption::factory()->for($option)->create();
    $row = ['attribute_key' => $option->attribute->key, 'value_semantic_id' => $option->value->semantic_id, 'code' => 'ZZ'];

    AttributeValueImporter::test(options: ['update_existing' => true], import: $this->import)->import($row)->assertHasErrors(['code']);
    expect($option->fresh()->code)->toBe('Ab');

    AttributeValueImporter::test(options: ['update_existing' => true], import: $this->import)
        ->import([...$row, 'code' => 'Ab', 'is_active' => '0', 'is_hidden' => '1'])->assertImported();

    expect($option->fresh()->only(['code', 'is_active', 'is_hidden']))->toBe(['code' => 'Ab', 'is_active' => false, 'is_hidden' => true]);
    expect($inclusion->fresh()->option_id)->toBe($option->id);
    $this->assertDatabaseCount('options', 1);
});

test('import authorization uses the saved import owner rather than the worker login', function (string $importer, array $row) {
    $outsider = User::factory()->create();
    $import = (new Import)->setRelation('user', $outsider);

    expect(fn () => $importer::test(import: $import)->import($row))->toThrow(AuthorizationException::class);
})->with([
    [ValueImporter::class, ['semantic_id' => 'nylon', 'label' => 'Nylon']],
    [AttributeImporter::class, ['key' => 'thread_type', 'label' => 'Thread Type']],
    [AttributeValueImporter::class, ['attribute_key' => 'thread_type', 'value_semantic_id' => 'bspt', 'code' => 'BT']],
]);

test('an Attribute-Value code changed during import is not overwritten', function () {
    $option = Option::factory()->create(['code' => 'Ab']);
    $row = ['attribute_key' => $option->attribute->key, 'value_semantic_id' => $option->value->semantic_id, 'code' => 'Ab'];
    $changed = false;
    Option::retrieved(function (Option $record) use ($option, &$changed): void {
        if ($record->id === $option->id && ! $changed) {
            $changed = true;
            DB::table('options')->where('id', $record->id)->update(['code' => 'Xy']);
        }
    });

    try {
        AttributeValueImporter::test(options: ['update_existing' => true], import: $this->import)->import($row)->assertHasErrors(['code']);
    } finally {
        Option::flushEventListeners();
    }

    expect($option->fresh()->code)->toBe('Xy');
});

test('default exports use portable headers and retain all editable data', function () {
    $attribute = Attribute::factory()->create(['key' => 'thread_type', 'label' => 'Thread Type', 'is_active' => false]);
    $value = Value::factory()->create(['semantic_id' => 'bspt', 'label' => 'BSPT', 'description' => 'Thread', 'tags' => ['a|b', 'c']]);
    $option = Option::factory()->for($attribute)->for($value)->create(['code' => 'Bt', 'is_active' => false, 'is_hidden' => true]);

    expect(ValueExporter::test()->export($value))->toBe(['1', 'bspt', 'BSPT', 'Thread', '["a|b","c"]']);
    expect(AttributeExporter::test()->export($attribute))->toBe(['1', 'thread_type', 'Thread Type', '0']);
    expect(AttributeValueExporter::test()->export($option))->toBe(['1', 'thread_type', 'bspt', 'Bt', '0', '1']);
});

test('spreadsheet text exports are protected and round trip with an explicit import option', function (string $text, string $exported) {
    $attribute = Attribute::factory()->create(['key' => $text, 'label' => $text]);
    $value = Value::factory()->create(['label' => $text, 'description' => $text]);
    $option = Option::factory()->for($attribute)->for($value)->create();
    $valueRow = ValueExporter::test()->export($value);
    $attributeRow = AttributeExporter::test()->export($attribute);
    $optionRow = AttributeValueExporter::test()->export($option);
    $helperRow = AttributeValueExporter::test(columnMap: ['attribute.label' => 'helper_attribute_label', 'value.label' => 'helper_value_label'])->export($option);

    expect([$valueRow[2], $valueRow[3], $attributeRow[1], $attributeRow[2], $optionRow[1], ...$helperRow])->each->toBe($exported);

    $options = ['update_existing' => true, 'restore_spreadsheet_text' => true];
    ValueImporter::test(options: $options, import: $this->import)->import(array_combine(
        ['import', 'semantic_id', 'label', 'description', 'tags'], $valueRow,
    ))->assertImported();
    AttributeImporter::test(options: $options, import: $this->import)->import(array_combine(
        ['import', 'key', 'label', 'is_active'], $attributeRow,
    ))->assertImported();
    AttributeValueImporter::test(options: $options, import: $this->import)->import(array_combine(
        ['import', 'attribute_key', 'value_semantic_id', 'code', 'is_active', 'is_hidden'], $optionRow,
    ))->assertImported();

    expect($value->fresh()->label)->toBe($text);
    expect($value->fresh()->description)->toBe($text);
    expect($attribute->fresh()->key)->toBe($text);
    expect($attribute->fresh()->label)->toBe($text);
    expect(ValueExporter::test()->export($value->fresh()))->toBe($valueRow);
    $this->assertDatabaseCount('options', 1);
})->with([
    ['=1+1', "'=1+1"],
    ['+SUM(A1)', "'+SUM(A1)"],
    ['-SUM(A1)', "'-SUM(A1)"],
    ['@example', "'@example"],
    ["\ttext", "'\ttext"],
    ["\rtext", "'\rtext"],
    ['-5', '-5'],
    ["'literal", "'literal"],
    ["'=1+1", "''=1+1"],
    ["''=1+1", "'''=1+1"],
    ["'-5", "''-5"],
]);

test('ordinary imports preserve literal spreadsheet text prefixes', function () {
    $record = ValueImporter::test(import: $this->import)->import([
        'semantic_id' => 'literal', 'label' => "'=1+1", 'description' => "'+text",
    ])->assertImported()->getRecord();

    expect($record->fresh()->label)->toBe("'=1+1");
    expect($record->fresh()->description)->toBe("'+text");
});

test('a mixed Value export distinguishes formula text from an intentional apostrophe', function () {
    $formula = Value::factory()->create(['label' => '=1+1']);
    $literal = Value::factory()->create(['label' => "'=1+1"]);
    $rows = [ValueExporter::test()->export($formula), ValueExporter::test()->export($literal)];

    expect($rows[0][2])->toBe("'=1+1");
    expect($rows[1][2])->toBe("''=1+1");

    foreach ($rows as $row) {
        ValueImporter::test(options: ['update_existing' => true, 'restore_spreadsheet_text' => true], import: $this->import)
            ->import(array_combine(['import', 'semantic_id', 'label', 'description', 'tags'], $row))->assertImported();
    }

    expect($formula->fresh()->label)->toBe('=1+1');
    expect($literal->fresh()->label)->toBe("'=1+1");
});

test('the central page exports all records while resource export follows the current table search', function () {
    Storage::fake('local');
    $matching = Value::factory()->create(['label' => 'Nylon']);
    Value::factory()->create(['label' => 'Brass']);
    $exports = ExportAction::fake();

    Livewire::test(DataExchange::class)->callAction(TestAction::make('exportValues')->schemaComponent(true, 'content'))->assertHasNoFormErrors();

    $exports->assertDispatched(ValueExporter::class, function (Export $export, Builder $query): bool {
        expect($query->count())->toBe(2);

        return true;
    });

    Livewire::test(ListValues::class)->searchTable('Nylon')->callAction('export')->assertHasNoFormErrors();

    $exports->assertDispatched(ValueExporter::class, function (Export $export, Builder $query) use ($matching): bool {
        return $query->pluck('id')->all() === [$matching->id];
    });
});

test('Data Exchange is available to catalog managers and denies other users', function () {
    $this->get(DataExchange::getUrl())->assertSee('Attribute Values');

    $this->actingAs(User::factory()->create());
    $this->get(DataExchange::getUrl())->assertForbidden();
});
