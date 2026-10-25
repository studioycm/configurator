<?php

use AnourValar\EloquentSerialize\Facades\EloquentSerializeFacade;
use App\Filament\Exports\ConfiguratorRuleExporter;
use App\Filament\Exports\ConfiguratorRuleExportFiles;
use App\Filament\Exports\Jobs\ExportConfiguratorRulesCsv;
use App\Filament\Exports\Jobs\PrepareConfiguratorRulesExport;
use App\Models\CatalogContextSettings;
use App\Models\Configurator;
use App\Models\ConfiguratorRule;
use App\Models\RuleCondition;
use App\Models\User;
use App\Services\ConfiguratorRuleExchange;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\ExportDispatcher;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    DB::commit();
    $this->exportTransactions = app('db.transactions');
    $transactions = new DatabaseTransactionsManager;
    app()->instance('db.transactions', $transactions);
    DB::connection()->setTransactionManager($transactions);
    $this->exportBatchIds = DB::table('job_batches')->pluck('id')->all();
    $this->exportContextIds = CatalogContextSettings::pluck('id')->all();
    $this->exportActor = User::factory()->create(['email' => 'ycm@data4.work']);
    $this->exportConfigurator = Configurator::factory()->create();
    $this->exportRule = ConfiguratorRule::factory()->for($this->exportConfigurator)->create(['label' => 'Captured 001']);
    RuleCondition::factory()->for($this->exportRule, 'rule')->create();
    $this->rulesExport = Export::create(['user_id' => $this->exportActor->id, 'exporter' => ConfiguratorRuleExporter::class, 'total_rows' => 1, 'processed_rows' => 0, 'successful_rows' => 0, 'file_disk' => 'local', 'file_name' => 'rules']);
});

afterEach(function () {
    $this->rulesExport->delete();
    $this->exportRule->conditions()->delete();
    $this->exportRule->delete();
    $this->exportConfigurator->delete();
    $this->exportActor->delete();
    CatalogContextSettings::whereNotIn('id', $this->exportContextIds)->delete();
    DB::table('job_batches')->whereNotIn('id', $this->exportBatchIds)->delete();
    app()->instance('db.transactions', $this->exportTransactions);
    DB::connection()->setTransactionManager($this->exportTransactions);
    DB::beginTransaction();
});

test('a failure after an actual Rules export commit retains captured pages and retry accounts once', function () {
    $disk = Storage::fake('local');
    $export = $this->rulesExport;
    $disk->put($export->getFileDirectory().'/headers.csv', implode(',', ConfiguratorRuleExchange::COLUMNS)."\n");
    $job = new ExportConfiguratorRulesCsv($export, EloquentSerializeFacade::serialize(ConfiguratorRule::where('configurator_id', $this->exportConfigurator->id)), [$this->exportRule->id], 1, [], ['preset' => 'import']);
    $pending = true;
    Event::listen(TransactionCommitted::class, function (TransactionCommitted $event) use (&$pending, $export, $disk): void {
        if ($pending && $event->connection->transactionLevel() === 0 && $export->fresh()->processed_rows === 1 && $disk->exists($export->getFileDirectory().'/0000000000000001.csv')) {
            throw new RuntimeException('Injected actual export committed callback failure');
        }
    });
    try {
        expect($job->handle(...))->toThrow(RuntimeException::class, 'actual export committed');
    } finally {
        $pending = false;
    }
    expect(DB::transactionLevel())->toBe(0);
    expect((new ConfiguratorRuleExportFiles($export))->ready())->toBeTrue();
    $captured = $disk->get($export->getFileDirectory().'/0000000000000001.csv');
    $this->exportRule->update(['label' => 'Must not replace acknowledged capture']);
    $job->handle();
    expect($export->fresh()->only(['processed_rows', 'successful_rows']))->toBe(['processed_rows' => 1, 'successful_rows' => 1])
        ->and($disk->get($export->getFileDirectory().'/0000000000000001.csv'))->toBe($captured);
});

test('the actual native Rules export chain runs page accounting outside the batch repository transaction', function () {
    $disk = Storage::fake('local');
    config(['queue.default' => 'sync']);
    $this->actingAs($this->exportActor);
    $export = $this->rulesExport;
    $levels = [];
    $pending = true;
    Event::listen('eloquent.saving: '.Export::class, function (Export $record) use ($export, &$levels, &$pending): void {
        if ($pending && $record->id === $export->id && $record->isDirty('processed_rows')) {
            $levels[] = DB::transactionLevel();
        }
    });
    try {
        app(ExportDispatcher::class)->dispatch($export, EloquentSerializeFacade::serialize(ConfiguratorRule::where('configurator_id', $this->exportConfigurator->id)), ConfiguratorRuleExporter::columnMap(['preset' => 'import']), ['preset' => 'import'], [ExportFormat::Csv, ExportFormat::Xlsx], null, PrepareConfiguratorRulesExport::class, 100, null, null, null, 'web');
    } finally {
        $pending = false;
    }
    expect($levels)->toBe([1]);
    expect((new ConfiguratorRuleExportFiles($export))->ready())->toBeTrue();
    expect($export->fresh()->completed_at)->not->toBeNull();
    $disk->assertExists($export->getFileDirectory().'/rules.xlsx');
});
