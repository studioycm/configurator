<?php

use AnourValar\EloquentSerialize\Facades\EloquentSerializeFacade;
use App\Filament\Exports\ConfiguratorRuleExporter;
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
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use League\Csv\Reader;
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
    $rule->effects()->first()->update(['display_value' => str_repeat('א', 32768)]);
    $export = ruleExportRecord($actor);
    $exporter = new ConfiguratorRuleExporter($export, [], ['preset' => 'import']);
    (new ExportConfiguratorRulesCsv($export, EloquentSerializeFacade::serialize(ConfiguratorRule::query()), [$rule->id], 1, [], ['preset' => 'import']))->handle();
    $rows = iterator_to_array(Reader::fromString(Storage::disk('local')->get($export->getFileDirectory().'/0000000000000001.csv'))->getRecords(), false);

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

test('only Rules exports resolve the RFC-compatible XLSX job', function () {
    $actor = User::factory()->create(['email' => 'beny.cr@gmail.com']);
    $rules = ruleExportRecord($actor);
    $catalog = ruleExportRecord($actor);
    $catalog->update(['exporter' => ValueExporter::class]);

    $rulesJob = app(CreateXlsxFile::class, ['export' => $rules, 'columnMap' => [], 'options' => []]);
    $catalogJob = app(CreateXlsxFile::class, ['export' => $catalog, 'columnMap' => [], 'options' => []]);

    expect($rulesJob)->toBeInstanceOf(CreateConfiguratorRulesXlsxFile::class);
    expect($catalogJob::class)->toBe(CreateXlsxFile::class);
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

test('a database save failure removes published chunk files as its transaction rolls back', function () {
    $disk = Storage::fake('local');
    $actor = User::factory()->create(['email' => 'beny.cr@gmail.com']);
    $rule = ruleExportFixture();
    $export = ruleExportRecord($actor);
    Export::saving(function (): void {
        throw new RuntimeException('Simulated export accounting failure.');
    });

    try {
        expect(fn () => (new ExportConfiguratorRulesCsv($export, EloquentSerializeFacade::serialize(ConfiguratorRule::query()), [$rule->id], 1, []))->handle())->toThrow(RuntimeException::class, 'accounting failure');
    } finally {
        Export::flushEventListeners();
    }

    expect($disk->allFiles())->toBe([]);
    expect($export->fresh()->only(['processed_rows', 'successful_rows']))->toBe(['processed_rows' => 0, 'successful_rows' => 0]);
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
