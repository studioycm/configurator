<?php

use AnourValar\EloquentSerialize\Facades\EloquentSerializeFacade;
use App\Filament\Exports\ConfiguratorRuleExporter;
use App\Filament\Exports\Jobs\CreateCatalogXlsxFile;
use App\Filament\Exports\Jobs\CreateConfiguratorRulesXlsxFile;
use App\Filament\Exports\Jobs\ExportConfiguratorRulesCsv;
use App\Filament\Exports\ValueExporter;
use App\Filament\Pages\DataExchange;
use App\Filament\Resources\Configurators\Pages\EditConfigurator;
use App\Filament\Resources\Configurators\RelationManagers\RulesRelationManager;
use App\Models\Configurator;
use App\Models\ConfiguratorAttribute;
use App\Models\ConfiguratorRule;
use App\Models\RuleCondition;
use App\Models\RuleEffect;
use App\Models\User;
use App\Services\ConfiguratorRuleExchange;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Jobs\CreateXlsxFile;
use Filament\Actions\Exports\Models\Export;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use League\Csv\Reader;
use League\Csv\Writer;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

function ruleExportFixture(): ConfiguratorRule
{
    $rule = ConfiguratorRule::factory()->create(['label' => '=כותרת', 'is_active' => false]);
    $attribute = ConfiguratorAttribute::factory()->create(['configurator_id' => $rule->configurator_id]);
    RuleCondition::factory()->for($rule, 'rule')->create(['operand' => ' 001|exact\\text ']);
    RuleEffect::factory()->for($rule, 'rule')->create(['target_configurator_attribute_id' => $attribute->id, 'display_value' => "'=1+1"]);
    RuleEffect::factory()->for($rule, 'rule')->create(['target_configurator_attribute_id' => $attribute->id, 'kind' => 'SetHint', 'display_value' => "שורה\n 001 "]);

    return $rule;
}

function ruleExportRecord(User $actor, int $total = 1): Export
{
    return Export::create(['user_id' => $actor->id, 'exporter' => ConfiguratorRuleExporter::class, 'total_rows' => $total, 'processed_rows' => 0, 'successful_rows' => 0, 'file_disk' => 'local', 'file_name' => 'rules']);
}

test('Rule export exposes fixed choices immediately and preserves scoped defaults and reactive helpers', function (bool $scoped) {
    $this->actingAs(User::factory()->create(['email' => 'beny.cr@gmail.com']));
    $configurator = Configurator::factory()->create();
    $page = $scoped
        ? Livewire::test(RulesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])->mountAction(TestAction::make('exportRules')->table())
        : Livewire::test(DataExchange::class)->mountAction(TestAction::make('exportRules')->schemaComponent(true, 'content'));
    $schema = $page->instance()->getSchema($page->instance()->getMountedActionSchemaName());
    $choices = [
        'preset' => [['review' => __('data_exchange.rules.review'), 'import' => __('data_exchange.rules.import_template')], 'review'],
        'kind' => [['all' => __('data_exchange.rules.both'), 'Mapping' => __('data_exchange.rules.mapping'), 'Advanced' => __('data_exchange.rules.advanced')], 'all'],
        'state' => [['all' => __('data_exchange.rules.all'), 'enabled' => __('data_exchange.rules.enabled'), 'disabled' => __('data_exchange.rules.disabled')], 'all'],
        'format' => [['csv' => 'CSV', 'xlsx' => 'XLSX', 'both' => 'CSV + XLSX'], 'both'],
    ];

    foreach ($choices as $name => [$options, $default]) {
        $field = $schema->getComponentByStatePath($name);
        expect($field)->toBeInstanceOf(ToggleButtons::class);
        expect($field->getOptions())->toBe($options)
            ->and($field->getState())->toBe($default)
            ->and($field->isRequired())->toBeTrue()
            ->and($field->isInline())->toBeTrue()
            ->and($field->isGrouped())->toBeFalse();
        expect($field->toHtml())->toContain('role="radiogroup"', 'fi-inline', ...array_values($options));
    }

    $lookup = $schema->getComponentByStatePath('configurator_ids');
    if ($scoped) {
        expect($lookup)->toBeNull();
    } else {
        expect($lookup)->toBeInstanceOf(Select::class);
        expect($lookup->isMultiple())->toBeTrue()
            ->and($lookup->isSearchable())->toBeTrue()
            ->and($lookup->getState())->toBe([]);
    }

    foreach (['import' => false, 'review' => true] as $preset => $helpers) {
        $page->set('mountedActions.0.data.preset', $preset);
        foreach (array_keys(ConfiguratorRuleExchange::HELPERS) as $group) {
            $page->assertSet('mountedActions.0.data.helper_'.$group, $helpers);
        }
    }
    expect(Export::count())->toBe(0);
})->with(['all Configurators' => false, 'one Configurator' => true]);

test('replaying a Rules export page preserves its captured bytes and accounts for it only once', function () {
    $disk = Storage::fake('local');
    $actor = User::factory()->create(['email' => 'beny.cr@gmail.com']);
    $rule = ruleExportFixture();
    $export = ruleExportRecord($actor, 2);
    $job = new ExportConfiguratorRulesCsv($export, EloquentSerializeFacade::serialize(ConfiguratorRule::query()), [$rule->id], 1, [], ['preset' => 'import']);
    $job->handle();
    $path = $export->getFileDirectory().'/0000000000000001.csv';
    $captured = $disk->get($path);
    $rule->update(['label' => 'Changed after captured export']);
    $job->handle();
    expect($export->fresh()->only(['processed_rows', 'successful_rows']))->toBe(['processed_rows' => 1, 'successful_rows' => 1])
        ->and($disk->get($path))->toBe($captured);
});

test('a post-savepoint export callback failure retains its valid page and retry does not add accounting', function () {
    $disk = Storage::fake('local');
    $actor = User::factory()->create(['email' => 'beny.cr@gmail.com']);
    $this->actingAs($actor);
    $rule = ruleExportFixture();
    $export = ruleExportRecord($actor);
    $disk->put($export->getFileDirectory().'/headers.csv', implode(',', ConfiguratorRuleExchange::COLUMNS)."\n");
    $job = new ExportConfiguratorRulesCsv($export, EloquentSerializeFacade::serialize(ConfiguratorRule::query()), [$rule->id], 1, [], ['preset' => 'import']);
    $pending = true;
    Event::listen(TransactionCommitted::class, function (TransactionCommitted $event) use (&$pending, $export): void {
        if ($pending && $event->connection->transactionLevel() === 1 && $export->fresh()->processed_rows === 1) {
            throw new RuntimeException('Injected export committed callback failure');
        }
    });
    try {
        expect($job->handle(...))->toThrow(RuntimeException::class, 'committed callback failure');
    } finally {
        $pending = false;
    }
    $path = $export->getFileDirectory().'/0000000000000001.csv';
    $disk->assertExists($path);
    $captured = $disk->get($path);
    $job->handle();
    expect($export->fresh()->only(['processed_rows', 'successful_rows']))->toBe(['processed_rows' => 1, 'successful_rows' => 1])
        ->and($disk->get($path))->toBe($captured);
    $url = URL::signedRoute('filament.exports.download', ['authGuard' => 'web', 'export' => $export, 'format' => 'csv']);
    $this->get($url)->assertDownload('rules.csv');
});

test('the real native export pipeline writes fixed core Rules and optional helper and snapshot lookup sheets', function () {
    Storage::fake('local');
    config(['queue.default' => 'sync']);
    $actor = User::factory()->create(['email' => 'beny.cr@gmail.com']);
    $this->actingAs($actor);
    $rule = ruleExportFixture();

    Livewire::test(DataExchange::class)->callAction(TestAction::make('exportRules')->schemaComponent(true, 'content'), ['preset' => 'review', 'format' => 'both'])->assertHasNoFormErrors();

    $export = Export::latest('id')->firstOrFail();
    expect($export->only(['total_rows', 'processed_rows', 'successful_rows']))->toBe(['total_rows' => 1, 'processed_rows' => 1, 'successful_rows' => 1]);
    $headers = Reader::fromString(Storage::disk('local')->get($export->getFileDirectory().'/headers.csv'))->first();
    expect($headers)->toHaveCount(34);
    expect(array_slice($headers, 0, 4))->toBe(['format_version', 'import', 'operation', 'configurator_id']);
    $rows = iterator_to_array(Reader::fromString(Storage::disk('local')->get($export->getFileDirectory().'/0000000000000001.csv'))->getRecords(), false);
    expect($rows)->toHaveCount(3);
    expect($rows[0][7])->toBe("'=כותרת");
    expect($rows[0][19])->toBe(' 001|exact\\text ');
    expect($rows[1][24])->toBe("''=1+1");
    expect($rows[2][24])->toBe("שורה\n 001 ");
    $reader = new XlsxReader;
    $reader->open(Storage::disk('local')->path($export->getFileDirectory().'/'.$export->file_name.'.xlsx'));
    $sheets = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        $sheets[$sheet->getName()] = [];
        foreach ($sheet->getRowIterator() as $row) {
            $sheets[$sheet->getName()][] = $row->toArray();
        }
    }
    $reader->close();
    expect(array_keys($sheets))->toBe(['Rules', 'Rules_helpers', 'Lookups']);
    expect($sheets['Rules'][0])->toHaveCount(28);
    expect($sheets['Rules'])->toHaveCount(4);
    expect($sheets['Rules_helpers'][1])->toBe($rows[0]);
    expect($sheets['Rules'][1])->toBe(array_slice($rows[0], 0, 28));
    expect($sheets['Lookups'][1][0])->toBe((string) $rule->configurator_id);
});

test('central export filters configurators kind and state while scoped export cannot accept a foreign owner', function () {
    Storage::fake('local');
    $this->actingAs(User::factory()->create(['email' => 'beny.cr@gmail.com']));
    $rule = ruleExportFixture();
    ConfiguratorRule::factory()->create(['kind' => 'Mapping']);
    $exports = ExportAction::fake();

    Livewire::test(DataExchange::class)->callAction(TestAction::make('exportRules')->schemaComponent(true, 'content'), ['configurator_ids' => [$rule->configurator_id], 'kind' => 'Advanced', 'state' => 'disabled'])->assertHasNoFormErrors();
    $exports->assertDispatched(ConfiguratorRuleExporter::class, fn (Export $export, Builder $query): bool => $query->pluck('id')->all() === [$rule->id]);

    $foreign = Configurator::factory()->create();
    Livewire::test(RulesRelationManager::class, ['ownerRecord' => $rule->configurator, 'pageClass' => EditConfigurator::class])->callAction(TestAction::make('exportRules')->table(), ['configurator_ids' => [$foreign->id]])->assertHasNoFormErrors();
    $exports->assertDispatched(ConfiguratorRuleExporter::class, fn (Export $export, Builder $query): bool => $query->pluck('id')->all() === [$rule->id]);
});

test('a failed rule emits no partial rows and native progress counts rules including missing records', function () {
    Storage::fake('local');
    $actor = User::factory()->create(['email' => 'beny.cr@gmail.com']);
    $good = ruleExportFixture();
    $bad = ruleExportFixture();
    $bad->conditionGroups()->create(['operator' => 'Any', 'sort_order' => 1]);
    $export = ruleExportRecord($actor, 3);
    $query = EloquentSerializeFacade::serialize(ConfiguratorRule::query());

    (new ExportConfiguratorRulesCsv($export, $query, [$good->id, $bad->id, 99999], 1, [], ['preset' => 'import']))->handle();

    expect($export->fresh()->only(['processed_rows', 'successful_rows']))->toBe(['processed_rows' => 3, 'successful_rows' => 1]);
    $rows = iterator_to_array(Reader::fromString(Storage::disk('local')->get($export->getFileDirectory().'/0000000000000001.csv'))->getRecords(), false);
    expect($rows)->toHaveCount(3);
    expect(array_column($rows, 5))->each->toBe((string) $good->id);
    expect($rows[0])->toHaveCount(28);
});

test('queued exports authorize the saved export owner independently of the current worker user', function () {
    Storage::fake('local');
    $manager = User::factory()->create(['email' => 'beny.cr@gmail.com']);
    $this->actingAs($manager);
    $outsider = User::factory()->create();
    $rule = ruleExportFixture();
    $export = ruleExportRecord($outsider);

    expect(fn () => (new ExportConfiguratorRulesCsv($export, EloquentSerializeFacade::serialize(ConfiguratorRule::query()), [$rule->id], 1, []))->handle())->toThrow(AuthorizationException::class);
    expect(Storage::disk('local')->allFiles())->toBe([]);
    expect($export->fresh()->processed_rows)->toBe(0);
});

test('XLSX rejects oversized cells explicitly while CSV retains their complete text', function () {
    Storage::fake('local');
    $actor = User::factory()->create(['email' => 'beny.cr@gmail.com']);
    $rule = ruleExportFixture();
    $export = ruleExportRecord($actor);
    $exporter = new ConfiguratorRuleExporter($export, [], ['preset' => 'import']);
    $snapshot = app(ConfiguratorRuleExchange::class)->snapshot($rule);
    $snapshot['rows'][1]['display_value'] = str_repeat('א', 32768);
    $csv = Writer::fromString();
    $csv->setEscape('');
    $csv->insertAll($exporter->formatRows($snapshot));
    $rows = iterator_to_array(Reader::fromString($csv->toString())->getRecords(), false);

    expect(mb_strlen($rows[1][24]))->toBe(32768);
    expect(fn () => $exporter->makeXlsxRow($rows[1]))->toThrow(RuntimeException::class, '32,767');
});

test('Import template resets helpers and users can override an individual helper without changing core columns', function () {
    Storage::fake('local');
    config(['queue.default' => 'sync']);
    $this->actingAs(User::factory()->create(['email' => 'beny.cr@gmail.com']));
    ruleExportFixture();
    $page = Livewire::test(DataExchange::class)->mountAction(TestAction::make('exportRules')->schemaComponent(true, 'content'));

    $page->set('mountedActions.0.data.preset', 'import')
        ->assertSet('mountedActions.0.data.helper_labels', false)
        ->assertSet('mountedActions.0.data.helper_codes', false)
        ->assertSet('mountedActions.0.data.helper_summaries', false)
        ->assertSet('mountedActions.0.data.helper_diagnostics', false)
        ->set('mountedActions.0.data.helper_codes', true)
        ->set('mountedActions.0.data.format', 'csv')
        ->callMountedAction()->assertHasNoFormErrors();

    $export = Export::latest('id')->firstOrFail();
    $headers = Reader::fromString(Storage::disk('local')->get($export->getFileDirectory().'/headers.csv'))->first();
    expect($headers)->toHaveCount(29);
    expect($headers[28])->toBe('helper_reference_codes_snapshot');
    Storage::disk('local')->assertMissing($export->getFileDirectory().'/'.$export->file_name.'.xlsx');
});

test('XLSX lookup snapshots retain captured references after the shared records change', function () {
    Storage::fake('local');
    $actor = User::factory()->create(['email' => 'beny.cr@gmail.com']);
    $this->actingAs($actor);
    $rule = ruleExportFixture();
    $attribute = $rule->effects()->first()->targetAttribute->attribute;
    $attribute->update(['key' => 'before_export', 'label' => 'Original UI Name']);
    $export = ruleExportRecord($actor);
    $query = EloquentSerializeFacade::serialize(ConfiguratorRule::query());
    $map = array_combine(ConfiguratorRuleExchange::COLUMNS, ConfiguratorRuleExchange::COLUMNS);
    Storage::disk('local')->put($export->getFileDirectory().'/headers.csv', implode(',', $map)."\n");
    (new ExportConfiguratorRulesCsv($export, $query, [$rule->id], 1, [], ['preset' => 'import']))->handle();
    $attribute->update(['key' => 'after_export', 'label' => 'Changed UI Name']);

    app(CreateXlsxFile::class, ['export' => $export, 'columnMap' => $map, 'options' => ['preset' => 'import']])->handle();

    $reader = new XlsxReader;
    $reader->open(Storage::disk('local')->path($export->getFileDirectory().'/rules.xlsx'));
    $names = [];
    $lookups = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        $names[] = $sheet->getName();
        if ($sheet->getName() === 'Lookups') {
            foreach ($sheet->getRowIterator() as $row) {
                $lookups[] = $row->toArray();
            }
        }
    }
    $reader->close();
    expect($names)->toBe(['Rules', 'Lookups']);
    expect(array_column($lookups, 3))->toContain('before_export')->not->toContain('after_export');
    expect(array_column($lookups, 4))->toContain('Original UI Name')->not->toContain('Changed UI Name');
});

test('formula protection preserves whitespace-only Product operands in actual CSV rows', function () {
    Storage::fake('local');
    $actor = User::factory()->create(['email' => 'beny.cr@gmail.com']);
    $rule = ruleExportFixture();
    $rule->conditions()->first()->update(['operand' => '   ']);
    $export = ruleExportRecord($actor);

    (new ExportConfiguratorRulesCsv($export, EloquentSerializeFacade::serialize(ConfiguratorRule::query()), [$rule->id], 1, [], ['preset' => 'import']))->handle();

    $rows = iterator_to_array(Reader::fromString(Storage::disk('local')->get($export->getFileDirectory().'/0000000000000001.csv'))->getRecords(), false);
    expect($rows[0][19])->toBe('   ');
});

test('native XLSX-only export can omit both helpers and Lookups', function () {
    Storage::fake('local');
    config(['queue.default' => 'sync']);
    $this->actingAs(User::factory()->create(['email' => 'beny.cr@gmail.com']));
    ruleExportFixture();

    Livewire::test(DataExchange::class)->callAction(TestAction::make('exportRules')->schemaComponent(true, 'content'), ['preset' => 'import', 'format' => 'xlsx', 'helper_labels' => false, 'helper_codes' => false, 'helper_summaries' => false, 'helper_diagnostics' => false, 'lookups' => false])->assertHasNoFormErrors();

    $export = Export::latest('id')->firstOrFail();
    $reader = new XlsxReader;
    $reader->open(Storage::disk('local')->path($export->getFileDirectory().'/'.$export->file_name.'.xlsx'));
    $sheets = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        $sheets[] = $sheet->getName();
        foreach ($sheet->getRowIterator() as $row) {
            expect($row->toArray())->toHaveCount(28);
        }
    }
    $reader->close();
    expect($sheets)->toBe(['Rules']);
    expect($export->completed_at)->not->toBeNull();
});

test('native downloads require the saved export owner even with a valid signed URL', function () {
    Storage::fake('local');
    config(['queue.default' => 'sync']);
    $this->actingAs(User::factory()->create(['email' => 'beny.cr@gmail.com']));
    ruleExportFixture();
    Livewire::test(DataExchange::class)->callAction(TestAction::make('exportRules')->schemaComponent(true, 'content'), ['format' => 'csv'])->assertHasNoFormErrors();
    $export = Export::latest('id')->firstOrFail();
    $url = URL::signedRoute('filament.exports.download', ['authGuard' => 'web', 'export' => $export, 'format' => 'csv']);

    $this->get($url)->assertDownload();
    $this->actingAs(User::factory()->create());
    $this->get($url)->assertForbidden();
});

test('actual CSV and XLSX preserve trailing backslashes and adjacent quotation marks', function () {
    Storage::fake('local');
    config(['queue.default' => 'sync']);
    $this->actingAs(User::factory()->create(['email' => 'beny.cr@gmail.com']));
    $rule = ruleExportFixture();
    $literal = 'end\\';
    $display = 'quote"\\';
    $rule->update(['label' => $literal]);
    $rule->conditions()->first()->update(['operand' => $literal]);
    $rule->effects()->first()->update(['display_value' => $display]);

    Livewire::test(DataExchange::class)->callAction(TestAction::make('exportRules')->schemaComponent(true, 'content'), ['format' => 'both'])->assertHasNoFormErrors();

    $export = Export::latest('id')->firstOrFail();
    $csv = Reader::fromString(Storage::disk('local')->get($export->getFileDirectory().'/0000000000000001.csv'));
    $csv->setEscape('');
    $rows = iterator_to_array($csv->getRecords(), false);
    expect($rows)->toHaveCount(3);
    expect($rows[0])->toHaveCount(34);
    expect($rows[0][7])->toBe($literal);
    expect($rows[0][19])->toBe($literal);
    expect($rows[1][24])->toBe($display);
    $reader = new XlsxReader;
    $reader->open(Storage::disk('local')->path($export->getFileDirectory().'/'.$export->file_name.'.xlsx'));
    $sheets = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $sheets[$sheet->getName()][] = $row->toArray();
        }
    }
    $reader->close();
    expect($sheets['Rules'][1][7])->toBe($literal);
    expect($sheets['Rules'][1][19])->toBe($literal);
    expect($sheets['Rules'][2][24])->toBe($display);
    expect($sheets['Rules_helpers'][1])->toBe($rows[0]);
});

test('Rules and managed canonical exports use scoped XLSX jobs while historical exports keep native behavior', function () {
    $actor = User::factory()->create(['email' => 'beny.cr@gmail.com']);
    $rules = ruleExportRecord($actor);
    $catalog = ruleExportRecord($actor);
    $catalog->update(['exporter' => ValueExporter::class]);

    $rulesJob = app(CreateXlsxFile::class, ['export' => $rules, 'columnMap' => [], 'options' => []]);
    $catalogJob = app(CreateXlsxFile::class, ['export' => $catalog, 'columnMap' => [], 'options' => []]);

    expect($rulesJob)->toBeInstanceOf(CreateConfiguratorRulesXlsxFile::class);
    expect($catalogJob::class)->toBe(CreateXlsxFile::class);
    $catalog->update(['catalog_artifact_version' => 1]);
    expect(app(CreateXlsxFile::class, ['export' => $catalog, 'columnMap' => [], 'options' => []]))->toBeInstanceOf(CreateCatalogXlsxFile::class);
});

test('failed JSON publication leaves no downloadable chunk or counters', function (bool $throws) {
    $disk = Storage::fake('local');
    $actor = User::factory()->create(['email' => 'beny.cr@gmail.com']);
    $rule = ruleExportFixture();
    $export = ruleExportRecord($actor);
    $failingDisk = Mockery::mock($disk);
    $failingDisk->shouldReceive('put')->andReturnUsing(function (string $path, mixed $contents, mixed $options = []) use ($disk, $throws): bool {
        if (str_contains($path, '.json')) {
            if ($throws) {
                throw new RuntimeException('Simulated private Rules export sidecar failure.');
            }

            return false;
        }

        return $disk->put($path, $contents, $options);
    });
    Storage::shouldReceive('disk')->with('local')->andReturn($failingDisk);

    expect(fn () => (new ExportConfiguratorRulesCsv($export, EloquentSerializeFacade::serialize(ConfiguratorRule::query()), [$rule->id], 1, []))->handle())->toThrow(RuntimeException::class, 'private Rules export');

    expect($disk->allFiles())->toBe([]);
    expect($export->fresh()->only(['processed_rows', 'successful_rows']))->toBe(['processed_rows' => 0, 'successful_rows' => 0]);
})->with(['false return' => false, 'exception' => true]);

test('a database save failure retains private preparation for reconciliation without exposing a partial export', function () {
    $disk = Storage::fake('local');
    $actor = User::factory()->create(['email' => 'beny.cr@gmail.com']);
    $rule = ruleExportFixture();
    $export = ruleExportRecord($actor);
    $job = new ExportConfiguratorRulesCsv($export, EloquentSerializeFacade::serialize(ConfiguratorRule::query()), [$rule->id], 1, []);
    Export::saving(function (): void {
        throw new RuntimeException('Simulated export accounting failure.');
    });

    try {
        expect($job->handle(...))->toThrow(RuntimeException::class, 'accounting failure');
    } finally {
        Export::flushEventListeners();
    }

    $base = $export->getFileDirectory().'/0000000000000001';
    $disk->assertExists($base.'.json');
    $disk->assertMissing($base.'.csv');
    expect($export->fresh()->only(['processed_rows', 'successful_rows']))->toBe(['processed_rows' => 0, 'successful_rows' => 0]);
    $rule->update(['label' => 'Changed after failed accounting']);
    $job->handle();
    $disk->assertExists($base.'.csv');
    expect($disk->get($base.'.csv'))->not->toContain('Changed after failed accounting')
        ->and($export->fresh()->only(['processed_rows', 'successful_rows']))->toBe(['processed_rows' => 1, 'successful_rows' => 1]);
});

test('a failed page publication stays unavailable and replay reconciles all pages without recapturing source Rules', function () {
    $disk = Storage::fake('local');
    $actor = User::factory()->create(['email' => 'beny.cr@gmail.com']);
    $this->actingAs($actor);
    $first = ruleExportFixture();
    $second = ruleExportFixture();
    $export = ruleExportRecord($actor, 2);
    $disk->put($export->getFileDirectory().'/headers.csv', implode(',', ConfiguratorRuleExchange::COLUMNS)."\n");
    $failingDisk = Mockery::mock($disk);
    $fail = true;
    $failingDisk->shouldReceive('move')->andReturnUsing(function (string $from, string $to) use ($disk, &$fail): bool {
        return $fail && str_ends_with($to, '.csv') ? false : $disk->move($from, $to);
    });
    Storage::shouldReceive('disk')->with('local')->andReturn($failingDisk);
    $query = EloquentSerializeFacade::serialize(ConfiguratorRule::query());
    $job = new ExportConfiguratorRulesCsv($export, $query, [$first->id], 1, [], ['preset' => 'import']);
    expect($job->handle(...))->toThrow(RuntimeException::class, 'publish');
    $url = URL::signedRoute('filament.exports.download', ['authGuard' => 'web', 'export' => $export, 'format' => 'csv']);
    $this->get($url)->assertStatus(409)->assertHeader('Retry-After', '5');
    $notification = ConfiguratorRuleExporter::modifyCompletedNotification(Notification::make()->success(), $export->fresh())->toArray();
    expect($notification['status'])->toBe('warning');
    expect(ConfiguratorRuleExporter::getCompletedNotificationBody($export->fresh()))->toContain('not available');
    expect(fn () => (new CreateConfiguratorRulesXlsxFile($export, [], ['preset' => 'import']))->handle())->toThrow(RuntimeException::class, 'not ready');
    $fail = false;
    (new ExportConfiguratorRulesCsv($export, $query, [$second->id], 2, [], ['preset' => 'import']))->handle();
    $this->get($url)->assertStatus(409);
    $first->update(['label' => 'Must not enter captured export']);
    $job->handle();
    expect($export->fresh()->only(['processed_rows', 'successful_rows']))->toBe(['processed_rows' => 2, 'successful_rows' => 2]);
    $response = $this->get($url)->assertDownload('rules.csv');
    expect($response->streamedContent())->not->toContain('Must not enter captured export');
    $disk->put($export->getFileDirectory().'/headers.csv', 'wrong headers');
    $this->get($url)->assertStatus(409);
});

test('completed legacy Rules export pages remain downloadable but cannot be blindly replayed', function () {
    $disk = Storage::fake('local');
    $actor = User::factory()->create(['email' => 'beny.cr@gmail.com']);
    $this->actingAs($actor);
    $rule = ruleExportFixture();
    $export = ruleExportRecord($actor);
    $disk->put($export->getFileDirectory().'/headers.csv', implode(',', ConfiguratorRuleExchange::COLUMNS)."\n");
    $job = new ExportConfiguratorRulesCsv($export, EloquentSerializeFacade::serialize(ConfiguratorRule::query()), [$rule->id], 1, [], ['preset' => 'import']);
    $job->handle();
    $path = $export->getFileDirectory().'/0000000000000001.json';
    $metadata = json_decode($disk->get($path), true, flags: JSON_THROW_ON_ERROR);
    unset($metadata['recovery']);
    $disk->put($path, json_encode($metadata, JSON_THROW_ON_ERROR));
    $url = URL::signedRoute('filament.exports.download', ['authGuard' => 'web', 'export' => $export, 'format' => 'csv']);
    $this->get($url)->assertDownload('rules.csv');
    expect($job->handle(...))->toThrow(RuntimeException::class, 'older inputs');
    expect($export->fresh()->processed_rows)->toBe(1);
});

test('signed Rules XLSX downloads return a retry message until the generated workbook is available', function () {
    Storage::fake('local');
    config(['queue.default' => 'sync']);
    $this->actingAs(User::factory()->create(['email' => 'beny.cr@gmail.com']));
    ruleExportFixture();
    Livewire::test(DataExchange::class)->callAction(TestAction::make('exportRules')->schemaComponent(true, 'content'), ['format' => 'csv'])->assertHasNoFormErrors();
    $export = Export::latest('id')->firstOrFail();
    $url = URL::signedRoute('filament.exports.download', ['authGuard' => 'web', 'export' => $export, 'format' => 'xlsx']);

    $this->get($url)->assertStatus(409)->assertSee('Workbook not available yet')->assertSee('CSV');

    Storage::disk('local')->assertMissing($export->getFileDirectory().'/'.$export->file_name.'.xlsx');
});

test('signed ready Rules XLSX downloads deliver the exact generated workbook through the native downloader', function () {
    Storage::fake('local');
    config(['queue.default' => 'sync']);
    $this->actingAs(User::factory()->create(['email' => 'beny.cr@gmail.com']));
    ruleExportFixture();
    Livewire::test(DataExchange::class)->callAction(TestAction::make('exportRules')->schemaComponent(true, 'content'), ['format' => 'both'])->assertHasNoFormErrors();
    $export = Export::latest('id')->firstOrFail();
    $url = URL::signedRoute('filament.exports.download', ['authGuard' => 'web', 'export' => $export, 'format' => 'xlsx']);
    $expected = Storage::disk('local')->get($export->getFileDirectory().'/'.$export->file_name.'.xlsx');

    $response = $this->get($url);

    $response->assertDownload($export->file_name.'.xlsx')->assertHeader('X-Vapor-Base64-Encode', 'True');
    expect($response->streamedContent())->toBe($expected);
});
