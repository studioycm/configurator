<?php

use AnourValar\EloquentSerialize\Facades\EloquentSerializeFacade;
use App\Filament\Exports\AttributeExporter;
use App\Filament\Exports\AttributeValueExporter;
use App\Filament\Exports\CatalogExportFiles;
use App\Filament\Exports\Jobs\CompleteCatalogExport;
use App\Filament\Exports\Jobs\CreateCatalogXlsxFile;
use App\Filament\Exports\Jobs\ExportCatalogCsv;
use App\Filament\Exports\Jobs\PrepareCatalogCsvExport;
use App\Filament\Exports\ValueExporter;
use App\Filament\Pages\DataExchange;
use App\Filament\Resources\Values\Pages\ListValues;
use App\Models\Attribute;
use App\Models\Configurator;
use App\Models\ConfiguratorAttribute;
use App\Models\ConfiguratorOption;
use App\Models\Option;
use App\Models\User;
use App\Models\Value;
use App\Services\CatalogCsv;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\ExportDispatcher;
use Filament\Actions\Exports\Models\Export;
use Filament\Actions\Imports\Models\Import;
use Filament\Actions\Testing\TestAction;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

beforeEach(function () {
    DB::commit();
    $this->savedTransactions = app('db.transactions');
    $manager = new DatabaseTransactionsManager;
    app()->instance('db.transactions', $manager);
    DB::connection()->setTransactionManager($manager);
    $this->baselineIds = [];
    foreach (['exports', 'imports', 'failed_import_rows', 'job_batches', 'configurator_options', 'configurator_attributes', 'configurators', 'values', 'attributes', 'options', 'users'] as $table) {
        $this->baselineIds[$table] = DB::table($table)->pluck('id')->all();
    }
    Storage::fake('local');
    config(['queue.default' => 'sync']);
    $this->actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $this->actingAs($this->actor);
    auth()->guard('web')->login($this->actor);
    $this->first = Value::factory()->create(['semantic_id' => 'roundtrip_first', 'label' => 'Pressure, path\\', 'description' => "Line one\r\nLine two", 'tags' => ['0', '001']]);
    $this->second = Value::factory()->create(['semantic_id' => 'roundtrip_second', 'label' => 'He said "Open"', 'description' => '0']);
});

afterEach(function () {
    while (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
    foreach (['failed_import_rows', 'imports', 'exports', 'job_batches', 'configurator_options', 'configurator_attributes', 'configurators', 'options', 'attributes', 'values', 'users'] as $table) {
        DB::table($table)->whereNotIn('id', $this->baselineIds[$table])->delete();
    }
    app()->instance('db.transactions', $this->savedTransactions);
    DB::connection()->setTransactionManager($this->savedTransactions);
    DB::beginTransaction();
});

function dispatchCanonicalArtifact(object $test, array $formats = [ExportFormat::Csv, ExportFormat::Xlsx], string $exporter = ValueExporter::class, ?array $columns = null, int $chunkSize = 1): Export
{
    $columns ??= ['semantic_id' => 'semantic_id', 'label' => 'label', 'description' => 'description', 'tags' => 'tags'];
    $query = $exporter::getModel()::query();
    $export = Export::create(['user_id' => $test->actor->id, 'exporter' => $exporter, 'total_rows' => $query->count(), 'file_disk' => 'local', 'file_name' => 'roundtrip']);
    app(ExportDispatcher::class)->dispatch($export, EloquentSerializeFacade::serialize($query), $columns, [], $formats, null, PrepareCatalogCsvExport::class, $chunkSize, null, null, null, 'web');

    return $export;
}

function canonicalDownloadedRows(string $bytes): array
{
    $reader = app(CatalogCsv::class)->reader($bytes);
    app(CatalogCsv::class)->validate($reader);
    $reader->setHeaderOffset(0);

    return iterator_to_array($reader->getRecords(), false);
}

test('actual multi-page native chain publishes CSV and XLSX with identical captured exact text', function (array $formats) {
    $export = dispatchCanonicalArtifact($this, $formats);
    $files = new CatalogExportFiles($export);
    expect($export->fresh()->only(['processed_rows', 'successful_rows', 'catalog_artifact_version']))->toBe(['processed_rows' => 2, 'successful_rows' => 2, 'catalog_artifact_version' => 1]);
    expect($files->assessment()['csv']['download'])->toBeTrue();
    $this->actingAs($this->actor);
    $bytes = $this->get(route('filament.exports.download', ['export' => $export, 'format' => 'csv']))->assertOk()->streamedContent();
    $rows = canonicalDownloadedRows($bytes);
    expect(array_column($rows, 'label'))->toBe([$this->first->label, $this->second->label]);
    expect($rows[0]['description'])->toBe($this->first->description);
    if (in_array(ExportFormat::Xlsx, $formats, true)) {
        expect($files->assessment()['xlsx']['download'])->toBeTrue();
        $this->actingAs($this->actor);
        $download = $this->get(route('filament.exports.download', ['export' => $export, 'format' => 'xlsx']))
            ->assertOk()->assertHeader('X-Catalog-Artifact-State', 'verified')->streamedContent();
        expect(hash('sha256', $download))->toBe(hash('sha256', Storage::disk('local')->get($files->workbookPath())));
        $reader = new XlsxReader;
        $reader->open(Storage::disk('local')->path($files->workbookPath()));
        $xlsx = [];
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $xlsx[] = $row->toArray();
                }
            }
        } finally {
            $reader->close();
        }
        expect($xlsx[1][1])->toBe($this->first->label)->and($xlsx[2][1])->toBe($this->second->label);
        Storage::disk('local')->delete($export->getFileDirectory().'/0000000000000001.csv');
        expect($files->assessment()['csv']['download'])->toBeFalse()->and($files->assessment()['xlsx']['download'])->toBeTrue();
    }
})->with([[[ExportFormat::Csv]], [[ExportFormat::Xlsx]], [[ExportFormat::Csv, ExportFormat::Xlsx]]]);

test('actual downloaded CSV uploads maps dispatches and persists exact values through the native import action', function () {
    $export = dispatchCanonicalArtifact($this, [ExportFormat::Csv]);
    $this->actingAs($this->actor);
    $bytes = $this->get(route('filament.exports.download', ['export' => $export, 'format' => 'csv']))->assertOk()->streamedContent();
    $original = $this->first->only(['label', 'description', 'tags']);
    $this->first->update(['label' => 'Changed since capture', 'description' => 'Changed', 'tags' => []]);
    Livewire::test(DataExchange::class)->callAction(TestAction::make('importValues')->schemaComponent(true, 'content'), [
        'file' => UploadedFile::fake()->createWithContent('roundtrip.csv', $bytes),
        'columnMap' => ['semantic_id' => 'semantic_id', 'label' => 'label', 'description' => 'description', 'tags' => 'tags'],
        'update_existing' => true,
    ])->assertHasNoFormErrors();
    expect($this->first->fresh()->only(array_keys($original)))->toBe($original);
    expect(Import::query()->latest('id')->first()->successful_rows)->toBe(2);
});

test('late malformed quoting is rejected before creating an Import or dispatching any row', function () {
    $this->actingAs($this->actor);
    $before = Import::count();
    $bytes = "semantic_id,label\nnew_valid,Valid\nnew_invalid,\"He said \\\"Open\\\"\"\n";
    Livewire::test(DataExchange::class)->callAction(TestAction::make('importValues')->schemaComponent(true, 'content'), [
        'file' => UploadedFile::fake()->createWithContent('invalid.csv', $bytes), 'columnMap' => ['semantic_id' => 'semantic_id', 'label' => 'label'],
    ])->assertHasFormErrors();
    expect(Import::count())->toBe($before);
    expect(Value::whereIn('semantic_id', ['new_valid', 'new_invalid'])->exists())->toBeFalse();
});

test('header/page evidence staged inside an enclosing transaction is unavailable and rollback never publishes', function () {
    $export = Export::create(['user_id' => $this->actor->id, 'exporter' => ValueExporter::class, 'total_rows' => 1, 'file_disk' => 'local', 'file_name' => 'enclosing']);
    $columns = ['label' => 'label'];
    $query = EloquentSerializeFacade::serialize(Value::whereKey($this->first->id));
    $files = new CatalogExportFiles($export);
    DB::beginTransaction();
    $files->seal($columns, [], ['csv'], $query, [[$this->first->id]]);
    (new ExportCatalogCsv($export, $query, [$this->first->id], 1, $columns))->handle();
    expect($files->assessment()['csv']['download'])->toBeFalse();
    Storage::disk('local')->assertMissing($export->getFileDirectory().'/0000000000000001.csv');
    DB::rollBack();
    expect($export->fresh()->processed_rows)->toBe(0);
    expect($files->assessment()['csv']['download'])->toBeFalse();
    $this->first->update(['label' => 'Later source must not replace capture']);
    $files->seal($columns, [], ['csv'], $query, [[$this->first->id]]);
    (new ExportCatalogCsv($export, $query, [$this->first->id], 1, $columns))->handle();
    expect(implode('', iterator_to_array($files->csvChunks())))->not->toContain('Later source');
});

test('all three canonical action families are version marked while historical and unrelated exports are qualified', function (string $exporter, array $columns) {
    $attribute = Attribute::factory()->create();
    Option::factory()->for($attribute)->for($this->first)->create();
    $export = dispatchCanonicalArtifact($this, [ExportFormat::Csv], $exporter, $columns);
    expect($export->fresh()->catalog_artifact_version)->toBe(1);
    expect((new CatalogExportFiles($export))->assessment()['csv']['download'])->toBeTrue();
    $export->update(['catalog_artifact_version' => null]);
    $this->actingAs($this->actor);
    $this->get(route('filament.exports.download', ['export' => $export, 'format' => 'csv']))->assertOk()->assertHeader('X-Catalog-Artifact-State', 'historical-not-reverified');
    $this->actingAs(User::factory()->create());
    $this->get(route('filament.exports.download', ['export' => $export, 'format' => 'csv']))->assertForbidden();
})->with([[ValueExporter::class, ['label' => 'label']], [AttributeExporter::class, ['label' => 'label']], [AttributeValueExporter::class, ['code' => 'code']]]);

test('export creation refuses to erase pre-existing same-id evidence', function () {
    $next = (Export::max('id') ?? 0) + 1;
    Storage::disk('local')->put('filament_exports/'.$next.'/old-evidence.json', 'preserve');
    expect(fn () => Export::create(['id' => $next, 'user_id' => $this->actor->id, 'exporter' => ValueExporter::class, 'total_rows' => 1, 'file_disk' => 'local', 'file_name' => 'conflicting']))->toThrow(RuntimeException::class, 'preserved');
    expect(Storage::disk('local')->get('filament_exports/'.$next.'/old-evidence.json'))->toBe('preserve');
});

test('import modal lists active CSV rules and explicit legacy input uses that mode through persistence', function () {
    $bytes = <<<'CSV'
semantic_id,label
legacy_rule_value,"He said \"Open\""
CSV;
    Livewire::test(DataExchange::class)->mountAction(TestAction::make('importValues')->schemaComponent(true, 'content'))
        ->assertMountedActionModalSee(['Active CSV rules'])->fillForm(['csv_quoting' => 'legacy'])
        ->assertMountedActionModalSee(['Backslash escapes are preserved'])
        ->fillForm(['file' => UploadedFile::fake()->createWithContent('legacy.csv', $bytes), 'columnMap' => ['semantic_id' => 'semantic_id', 'label' => 'label']])
        ->callMountedAction()->assertHasNoFormErrors();
    expect(Value::where('semantic_id', 'legacy_rule_value')->sole()->label)->toBe('He said \\"Open\\"');
});

test('known formatter rejections preserve legitimate partial output with truthful counts', function (bool $allRejected) {
    $this->second->update(['label' => 'REJECT']);
    if ($allRejected) {
        $this->first->update(['label' => 'REJECT']);
    }
    if (! $allRejected) {
        Value::factory()->count(97)->create(['label' => 'Good']);
        Value::factory()->create(['label' => 'REJECT']);
    }
    app()->bind(ValueExporter::class, CanonicalCsvRejectingTestExporter::class);
    $export = dispatchCanonicalArtifact($this, [ExportFormat::Csv], columns: ['semantic_id' => 'semantic_id', 'label' => 'label'], chunkSize: 25);
    $files = new CatalogExportFiles($export);
    expect($files->assessment())->toMatchArray(['planned' => $allRejected ? 2 : 100, 'successful' => $allRejected ? 0 : 98, 'rejected' => 2, 'unconfirmed' => 0, 'partial' => ! $allRejected]);
    expect($files->assessment()['csv']['state'])->toBe($allRejected ? 'no-data' : 'ready');
    $notifications = session('filament.notifications');
    $notice = end($notifications);
    expect(count($notice['actions']))->toBe($allRejected ? 0 : 1);
})->with([false, true]);

test('workbook write failure leaves CSV available and controlled regeneration uses the captured input', function () {
    $export = dispatchCanonicalArtifact($this, [ExportFormat::Csv]);
    $files = new CatalogExportFiles($export);
    $original = Storage::disk('local');
    $failing = Mockery::mock($original)->makePartial();
    $failing->shouldReceive('put')->withArgs(fn (string $path, ...$arguments): bool => str_ends_with($path, '.xlsx'))->andReturn(false);
    Storage::set('local', $failing);
    try {
        (new CreateCatalogXlsxFile($export, ['semantic_id' => 'semantic_id', 'label' => 'label', 'description' => 'description', 'tags' => 'tags']))->handle();
        expect($files->assessment()['csv']['download'])->toBeTrue();
        expect($files->assessment()['xlsx'])->toMatchArray(['state' => 'failed', 'download' => false]);
    } finally {
        Storage::set('local', $original);
    }
    $this->first->update(['label' => 'Must not reach the workbook']);
    (new CreateCatalogXlsxFile($export, ['semantic_id' => 'semantic_id', 'label' => 'label', 'description' => 'description', 'tags' => 'tags']))->handle();
    expect($files->assessment()['xlsx']['download'])->toBeTrue();
});

test('completion re-entry does not repeat the same notification effect', function () {
    $export = dispatchCanonicalArtifact($this, [ExportFormat::Csv]);
    $before = session('filament.notifications');
    (new CompleteCatalogExport($export, [], [ExportFormat::Csv], [], 'web'))->handle();
    expect(session('filament.notifications'))->toBe($before);
});

test('workbook invocation waits for the enclosing transaction and rollback discards its callback', function (bool $commit) {
    $export = dispatchCanonicalArtifact($this, [ExportFormat::Csv]);
    $files = new CatalogExportFiles($export);
    DB::beginTransaction();
    (new CreateCatalogXlsxFile($export, ['semantic_id' => 'semantic_id', 'label' => 'label', 'description' => 'description', 'tags' => 'tags']))->handle();
    Storage::disk('local')->assertMissing($files->workbookPath());
    expect($files->assessment()['xlsx']['download'])->toBeFalse();
    if ($commit) {
        DB::commit();
    } else {
        DB::rollBack();
    }
    expect($files->assessment()['xlsx']['download'])->toBe($commit);
    if (! $commit) {
        Storage::disk('local')->assertMissing($files->workbookPath());
    }
})->with(['commit' => true, 'rollback' => false]);

test('unavailable captured CSV makes requested workbook fail instead of remaining preparing', function () {
    $export = dispatchCanonicalArtifact($this, [ExportFormat::Csv]);
    $files = new CatalogExportFiles($export);
    Storage::disk('local')->delete($export->getFileDirectory().'/0000000000000001.csv');
    (new CreateCatalogXlsxFile($export, ['semantic_id' => 'semantic_id', 'label' => 'label', 'description' => 'description', 'tags' => 'tags']))->handle();
    expect($files->assessment()['xlsx'])->toMatchArray(['state' => 'failed', 'download' => false]);
    (new CompleteCatalogExport($export, [], [ExportFormat::Xlsx], [], 'web'))->handle();
    $notices = session('filament.notifications');
    $notice = end($notices);
    expect($notice['status'])->toBe('danger');
    expect($notice['body'])->not->toContain('Preparing');
    expect($notice['actions'])->toBe([]);
});

class CanonicalCsvRejectingTestExporter extends ValueExporter
{
    public static function getColumns(): array
    {
        return [ExportColumn::make('semantic_id'), ExportColumn::make('label')->formatStateUsing(function (string $state): string {
            if ($state === 'REJECT') {
                throw new InvalidArgumentException('Invalid source text');
            }

            return $state;
        })];
    }
}

test('custom single-quote semicolon input works on the resource entrypoint and rules reflect selected options', function () {
    $bytes = "semantic_id;label\ncustom_resource;'He said ''Open'''\n";
    Livewire::test(ListValues::class)->mountAction('import')
        ->fillForm(['csv_quoting' => 'custom', 'csv_separator' => ';', 'csv_enclosure' => "'", 'csv_escape' => '', 'update_existing' => true, 'restore_spreadsheet_text' => true])
        ->assertMountedActionModalSee(['Active CSV rules', 'Existing matches update only mapped fields', 'Restore text from protected exports is enabled'])
        ->fillForm(['file' => UploadedFile::fake()->createWithContent('custom.csv', $bytes), 'columnMap' => ['semantic_id' => 'semantic_id', 'label' => 'label']])
        ->callMountedAction()->assertHasNoFormErrors();
    expect(Value::where('semantic_id', 'custom_resource')->sole()->label)->toBe("He said 'Open'");
});

test('the native failed-row download is standard quoted and retains protected source text with authorization', function () {
    $csv = app(CatalogCsv::class)->writer();
    $csv->insertOne(['semantic_id', 'label', 'description', 'unmapped']);
    $csv->insertOne(['valid_report', 'Valid', '0', '']);
    $csv->insertOne(['invalid_report', '', 'Ends, path\\', '=1+1']);
    Livewire::test(DataExchange::class)->callAction(TestAction::make('importValues')->schemaComponent(true, 'content'), [
        'file' => UploadedFile::fake()->createWithContent('failure.csv', $csv->toString()), 'columnMap' => ['semantic_id' => 'semantic_id', 'label' => 'label', 'description' => 'description'],
    ])->assertHasNoFormErrors();
    $import = Import::latest('id')->firstOrFail();
    expect($import->only(['total_rows', 'processed_rows', 'successful_rows']))->toBe(['total_rows' => 2, 'processed_rows' => 2, 'successful_rows' => 1]);
    $this->actingAs($this->actor);
    $url = route('filament.imports.failed-rows.download', ['import' => $import]);
    $bytes = $this->get($url)->assertOk()->streamedContent();
    $rows = canonicalDownloadedRows($bytes);
    expect($rows)->toHaveCount(1);
    expect($rows[0])->toMatchArray(['semantic_id' => 'invalid_report', 'description' => 'Ends, path\\', 'unmapped' => "'=1+1"]);
    expect(implode(' ', $rows[0]))->toContain('required');
    $this->actingAs(User::factory()->create());
    $this->get($url)->assertForbidden();
});

test('real native export actions stamp every canonical family and preserve resource query filters', function (string $action) {
    $attribute = Attribute::factory()->create();
    Option::factory()->for($attribute)->for($this->first)->create();
    Livewire::test(DataExchange::class)->callAction(TestAction::make($action)->schemaComponent(true, 'content'))->assertHasNoFormErrors();
    $export = Export::latest('id')->firstOrFail();
    expect($export->catalog_artifact_version)->toBe(1);
    expect((new CatalogExportFiles($export))->assessment()['csv']['download'])->toBeTrue();
})->with(['exportValues', 'exportAttributes', 'exportAttributeValues']);

test('the resource native export still captures only its filtered records', function () {
    Livewire::test(ListValues::class)->searchTable($this->first->semantic_id)->callAction('export')->assertHasNoFormErrors();
    $export = Export::latest('id')->firstOrFail();
    expect($export->total_rows)->toBe(1);
    $rows = canonicalDownloadedRows(implode('', iterator_to_array((new CatalogExportFiles($export))->csvChunks())));
    expect(array_column($rows, 'semantic_id'))->toBe([$this->first->semantic_id]);
});

test('native import exposes both legacy choices and converts escaped quotes only when selected', function () {
    $bytes = <<<'CSV'
semantic_id,label,description
converted_legacy,"He said \"0\"",C:\Tools\Valve
CSV;
    Livewire::test(DataExchange::class)->mountAction(TestAction::make('importValues')->schemaComponent(true, 'content'))
        ->fillForm(['csv_quoting' => 'legacy_convert'])
        ->assertMountedActionModalSee(['Backslash-escaped quotes in field values become plain quotes'])
        ->fillForm(['file' => UploadedFile::fake()->createWithContent('convert.csv', $bytes), 'columnMap' => ['semantic_id' => 'semantic_id', 'label' => 'label', 'description' => 'description']])
        ->callMountedAction()->assertHasNoFormErrors();
    expect(Value::where('semantic_id', 'converted_legacy')->sole()->only(['label', 'description']))->toBe(['label' => 'He said "0"', 'description' => 'C:\\Tools\\Valve']);
});

test('historical CSV page gaps block CSV without disabling an independently readable workbook', function () {
    Value::factory()->create();
    $export = dispatchCanonicalArtifact($this);
    $export->update(['catalog_artifact_version' => null]);
    Storage::disk('local')->delete($export->getFileDirectory().'/0000000000000002.csv');
    $assessment = (new CatalogExportFiles($export))->assessment();
    expect($assessment['csv']['download'])->toBeFalse();
    expect($assessment['xlsx'])->toMatchArray(['state' => 'historical', 'download' => true]);
});

test('CSV only completion does not describe an unrequested workbook as preparing', function () {
    dispatchCanonicalArtifact($this, [ExportFormat::Csv]);
    $notices = session('filament.notifications');
    $notice = end($notices);
    expect($notice['body'])->toContain('CSV:')->not->toContain('XLSX:');
});

test('the actual XLSX only chain gives preparing feedback before workbook success without premature actions', function () {
    dispatchCanonicalArtifact($this, [ExportFormat::Xlsx]);
    $notices = session('filament.notifications');
    expect($notices[0]['status'])->toBe('info');
    expect($notices[0]['title'])->toBe('Export files are being prepared');
    expect($notices[0]['actions'])->toBe([]);
    expect($notices[0]['body'])->toContain('XLSX: Preparing')->not->toContain('CSV:');
    expect(end($notices)['status'])->toBe('success');
});

test('actual Attribute and Attribute Value file roundtrips preserve codes memberships and unmapped metadata', function (bool $attributeValues) {
    $attribute = Attribute::factory()->create(['key' => 'roundtrip_attribute', 'label' => 'Thread, path\\', 'is_active' => true]);
    $option = Option::factory()->for($attribute)->for($this->first)->create(['code' => '01', 'is_active' => true, 'is_hidden' => true]);
    $owner = Configurator::factory()->create();
    $inclusion = ConfiguratorAttribute::factory()->create(['configurator_id' => $owner->id, 'attribute_id' => $attribute->id]);
    ConfiguratorOption::factory()->create(['configurator_attribute_id' => $inclusion->id, 'option_id' => $option->id]);
    $memberships = DB::table('configurator_options')->select('id', 'configurator_attribute_id', 'option_id')->orderBy('id')->get()->map(fn (stdClass $row): array => (array) $row)->all();
    $columns = $attributeValues ? ['attribute.key' => 'attribute_key', 'value.semantic_id' => 'value_semantic_id', 'code' => 'code', 'is_active' => 'is_active', 'is_hidden' => 'is_hidden'] : ['key' => 'key', 'label' => 'label', 'is_active' => 'is_active'];
    $export = dispatchCanonicalArtifact($this, [ExportFormat::Csv], $attributeValues ? AttributeValueExporter::class : AttributeExporter::class, $columns);
    $this->actingAs($this->actor);
    $bytes = $this->get(route('filament.exports.download', ['export' => $export, 'format' => 'csv']))->assertOk()->streamedContent();
    $map = $attributeValues ? ['attribute_key' => 'attribute_key', 'value_semantic_id' => 'value_semantic_id', 'code' => 'code', 'is_active' => 'is_active', 'is_hidden' => null] : ['key' => 'key', 'label' => 'label', 'is_active' => null];
    if ($attributeValues) {
        $option->update(['is_active' => false, 'is_hidden' => false]);
    } else {
        $attribute->update(['label' => 'Modified since export', 'is_active' => false]);
    }
    Livewire::test(DataExchange::class)->callAction(TestAction::make($attributeValues ? 'importAttributeValues' : 'importAttributes')->schemaComponent(true, 'content'), [
        'file' => UploadedFile::fake()->createWithContent('family.csv', $bytes), 'columnMap' => $map, 'update_existing' => true,
    ])->assertHasNoFormErrors();
    $import = Import::latest('id')->firstOrFail();
    expect($import->successful_rows)->toBe(1);
    expect($option->fresh()->only(['code', 'is_hidden']))->toBe(['code' => '01', 'is_hidden' => ! $attributeValues]);
    expect(DB::table('configurator_options')->select('id', 'configurator_attribute_id', 'option_id')->orderBy('id')->get()->map(fn (stdClass $row): array => (array) $row)->all())->toBe($memberships);
    expect(ConfiguratorAttribute::whereKey($inclusion->id)->value('configurator_id'))->toBe($owner->id);
    expect($this->first->fresh()->tags)->toBe(['0', '001']);
    if ($attributeValues) {
        expect($option->fresh()->is_active)->toBeTrue();
    } else {
        expect($attribute->fresh()->label)->toBe('Thread, path\\');
        expect($attribute->fresh()->is_active)->toBeFalse();
    }
})->with(['Attribute' => false, 'Attribute Value' => true]);
