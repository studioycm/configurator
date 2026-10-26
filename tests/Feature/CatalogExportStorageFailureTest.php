<?php

use AnourValar\EloquentSerialize\Facades\EloquentSerializeFacade;
use App\Filament\Exports\CatalogExportFiles;
use App\Filament\Exports\Jobs\ExportCatalogCsv;
use App\Filament\Exports\ValueExporter;
use App\Models\User;
use App\Models\Value;
use App\Services\CatalogCsv;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    DB::commit();
    $this->priorTransactions = app('db.transactions');
    $transactions = new DatabaseTransactionsManager;
    app()->instance('db.transactions', $transactions);
    DB::connection()->setTransactionManager($transactions);
    Storage::fake('local');
    $this->actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $this->actingAs($this->actor);
    $this->value = Value::factory()->create(['label' => 'Pressure, path\\', 'semantic_id' => 'artifact_value']);
    $this->export = Export::create(['user_id' => $this->actor->id, 'exporter' => ValueExporter::class, 'total_rows' => 1, 'file_disk' => 'local', 'file_name' => 'canonical']);
    $this->query = EloquentSerializeFacade::serialize(Value::whereKey($this->value->id));
    $this->columns = ['semantic_id' => 'semantic_id', 'label' => 'label'];
});

afterEach(function () {
    $this->export->delete();
    $this->value->delete();
    $this->actor->delete();
    app()->instance('db.transactions', $this->priorTransactions);
    DB::connection()->setTransactionManager($this->priorTransactions);
    DB::beginTransaction();
});

function prepareArtifactPage(object $test): CatalogExportFiles
{
    $files = new CatalogExportFiles($test->export);
    $files->seal($test->columns, [], ['csv', 'xlsx'], $test->query, [[$test->value->id]]);

    return $files;
}

test('a sealed but missing page cannot be made ready by green SQL counters', function () {
    $files = prepareArtifactPage($this);
    $this->export->update(['processed_rows' => 1, 'successful_rows' => 1]);
    expect($files->assessment()['csv']['download'])->toBeFalse();
});

test('an actual canonical page captures standard bytes and duplicate delivery never rereads newer data', function () {
    $files = prepareArtifactPage($this);
    $job = new ExportCatalogCsv($this->export, $this->query, [$this->value->id], 1, $this->columns);
    $job->handle();
    expect($files->assessment()['csv'])->toMatchArray(['state' => 'ready', 'download' => true]);
    $captured = implode('', iterator_to_array($files->csvChunks()));
    $this->value->update(['label' => 'Changed after capture']);
    $job->handle();
    expect(implode('', iterator_to_array($files->csvChunks())))->toBe($captured);
    expect($this->export->fresh()->only(['processed_rows', 'successful_rows']))->toBe(['processed_rows' => 1, 'successful_rows' => 1]);
});

test('failed publication keeps capture and counters but withholds CSV until an exact-capture retry', function () {
    $files = prepareArtifactPage($this);
    $disk = Storage::disk('local');
    $original = $disk;
    $failing = Mockery::mock($original)->makePartial();
    $failing->shouldReceive('put')->withArgs(fn ($path, ...$arguments): bool => $path === $this->export->getFileDirectory().'/0000000000000001.csv')->andReturn(false);
    Storage::set('local', $failing);
    $job = new ExportCatalogCsv($this->export, $this->query, [$this->value->id], 1, $this->columns);
    try {
        expect($job->handle(...))->toThrow(RuntimeException::class);
        expect($this->export->fresh()->successful_rows)->toBe(1);
        expect($files->assessment()['csv']['download'])->toBeFalse();
    } finally {
        Storage::set('local', $original);
    }
    $this->value->update(['label' => 'Never recapture this later value']);
    $job->handle();
    expect($files->assessment()['csv']['download'])->toBeTrue();
    expect(implode('', iterator_to_array($files->csvChunks())))->not->toContain('Never recapture');
});

test('managed exports remain unconfirmed if their metadata is removed or version is unsupported', function () {
    $files = prepareArtifactPage($this);
    (new ExportCatalogCsv($this->export, $this->query, [$this->value->id], 1, $this->columns))->handle();
    expect($this->export->fresh()->catalog_artifact_version)->toBe(1);
    Storage::disk('local')->delete($this->export->getFileDirectory().'/.catalog/plan.json');
    expect($files->assessment()['csv'])->toMatchArray(['state' => 'unconfirmed', 'download' => false]);
    $this->export->update(['catalog_artifact_version' => 99]);
    expect($files->assessment()['csv']['download'])->toBeFalse();
});

test('captured CSV bytes must have exactly the declared successful rows and columns', function (array $row) {
    $files = prepareArtifactPage($this);
    $csv = app(CatalogCsv::class)->writer();
    $csv->insertOne($row);
    expect(fn () => $files->capturePage(1, $csv, [(string) $this->value->id], []))->toThrow(RuntimeException::class);
    expect($this->export->fresh()->processed_rows)->toBe(0);
})->with([[['only one field']], [['extra', 'extra', 'extra']]]);

test('a false header or page stage write cannot produce successful rows or a download', function (string $failure) {
    $original = Storage::disk('local');
    $failing = Mockery::mock($original)->makePartial();
    $failing->shouldReceive('put')->withArgs(fn (string $path, ...$arguments): bool => $failure === 'header'
        ? str_ends_with($path, '/.catalog/header.csv')
        : str_contains($path, '/.catalog/staged/') && str_ends_with($path, '.csv'))->andReturn(false);
    Storage::set('local', $failing);
    $files = new CatalogExportFiles($this->export);
    try {
        expect(function (): void {
            prepareArtifactPage($this);
            (new ExportCatalogCsv($this->export, $this->query, [$this->value->id], 1, $this->columns))->handle();
        })->toThrow(RuntimeException::class);
        expect($this->export->fresh()->only(['processed_rows', 'successful_rows']))->toBe(['processed_rows' => 0, 'successful_rows' => 0]);
        expect($files->assessment()['csv']['download'])->toBeFalse();
    } finally {
        Storage::set('local', $original);
    }
})->with(['header', 'page']);

test('an unrelated exception from a formatter is not relabeled as a rejected record', function () {
    prepareArtifactPage($this);
    app()->bind(ValueExporter::class, fn ($app, array $parameters) => new class($parameters['export'], $parameters['columnMap'], $parameters['options']) extends ValueExporter
    {
        public static function getColumns(): array
        {
            return [ExportColumn::make('semantic_id'), ExportColumn::make('label')->formatStateUsing(fn () => throw new RuntimeException('Infrastructure unavailable'))];
        }
    });
    $job = new ExportCatalogCsv($this->export, $this->query, [$this->value->id], 1, $this->columns);
    expect($job->handle(...))->toThrow(RuntimeException::class, 'Infrastructure');
    expect($this->export->fresh()->processed_rows)->toBe(0);
    expect((new CatalogExportFiles($this->export))->assessment())->toMatchArray(['successful' => 0, 'rejected' => 0, 'unconfirmed' => 1]);
});

test('corrupted published bytes and mismatched queued identities remain unavailable without rewriting evidence', function () {
    $files = prepareArtifactPage($this);
    $job = new ExportCatalogCsv($this->export, $this->query, [$this->value->id], 1, $this->columns);
    $job->handle();
    $path = $this->export->getFileDirectory().'/0000000000000001.csv';
    Storage::disk('local')->put($path, 'wrong bytes');
    expect($files->assessment()['csv']['download'])->toBeFalse();
    expect($job->handle(...))->toThrow(RuntimeException::class, 'Conflicting');
    expect(Storage::disk('local')->get($path))->toBe('wrong bytes');
    $wrong = new ExportCatalogCsv($this->export, $this->query, [$this->value->id + 999], 1, $this->columns);
    expect($wrong->handle(...))->toThrow(RuntimeException::class, 'conflicts');
});
