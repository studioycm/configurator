<?php

use App\CatalogWriteReplan;
use App\Filament\CatalogDataActions;
use App\Filament\Imports\ValueImporter;
use App\Models\User;
use App\Models\Value;
use Filament\Actions\Imports\Events\ImportChunkProcessed;
use Filament\Actions\Imports\ImportDispatcher;
use Filament\Actions\Imports\Jobs\ImportCsv;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    DB::commit();
    $this->nativeTransactions = app('db.transactions');
    $transactions = new DatabaseTransactionsManager;
    app()->instance('db.transactions', $transactions);
    DB::connection()->setTransactionManager($transactions);
    $this->nativeBatchIds = DB::table('job_batches')->pluck('id')->all();
    $this->actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $this->nativePrefix = 'native_tx_'.bin2hex(random_bytes(4));
    $this->import = Import::create(['file_name' => 'native.csv', 'file_path' => 'native.csv', 'importer' => ValueImporter::class, 'total_rows' => 3, 'user_id' => $this->actor->id]);
});

afterEach(function () {
    Value::where('semantic_id', 'like', $this->nativePrefix.'%')->delete();
    $this->import->delete();
    $this->actor->delete();
    DB::table('job_batches')->whereNotIn('id', $this->nativeBatchIds)->delete();
    app()->instance('db.transactions', $this->nativeTransactions);
    DB::connection()->setTransactionManager($this->nativeTransactions);
    DB::beginTransaction();
});

function nativeValueJob(Import $import, array $rows): ImportCsv
{
    $class = CatalogDataActions::import('native', ValueImporter::class)->getJob();

    return new $class($import, base64_encode(serialize($rows)), ['semantic_id' => 'identity', 'label' => 'name', 'import' => 'include']);
}

test('the configured native chunk preserves mapped rows and legitimate rejected and skipped outcomes', function () {
    $job = nativeValueJob($this->import, [
        ['identity' => $this->nativePrefix.'_one', 'name' => '001', 'include' => '1'],
        ['identity' => $this->nativePrefix.'_invalid', 'name' => '', 'include' => '1'],
        ['identity' => '', 'name' => 'Skipped', 'include' => '0'],
    ]);
    $job->handle();
    expect($this->import->fresh()->only(['processed_rows', 'successful_rows']))->toBe(['processed_rows' => 3, 'successful_rows' => 2])
        ->and($this->import->failedRows()->count())->toBe(1)
        ->and(Value::where('semantic_id', $this->nativePrefix.'_one')->sole()->label)->toBe('001');
    expect($this->import->failedRows()->sole()->data)->toMatchArray(['identity' => $this->nativePrefix.'_invalid', 'name' => '']);
    $download = $this->actingAs($this->actor)->get(route('filament.imports.failed-rows.download', $this->import))->assertOk();
    expect($download->streamedContent())->toContain($this->nativePrefix.'_invalid');
});

test('a native chunk infrastructure failure rolls back prior rows and all accounting', function () {
    $pending = true;
    Event::listen('eloquent.saving: '.Value::class, function (Value $value) use (&$pending): void {
        if ($pending && $value->label === 'Crash') {
            throw new RuntimeException('Injected chunk infrastructure failure');
        }
    });
    try {
        $job = nativeValueJob($this->import, [
            ['identity' => $this->nativePrefix.'_one', 'name' => 'First'],
            ['identity' => $this->nativePrefix.'_two', 'name' => 'Crash'],
        ])->withFakeQueueInteractions();
        $job->handle();
        $job->assertFailedWith(RuntimeException::class);
        expect(Value::where('semantic_id', 'like', $this->nativePrefix.'%')->count())->toBe(0)
            ->and($this->import->fresh()->only(['processed_rows', 'successful_rows']))->toBe(['processed_rows' => 0, 'successful_rows' => 0])
            ->and($this->import->failedRows()->count())->toBe(0);
    } finally {
        $pending = false;
    }
});

test('native whole chunk replanning resets row models counters and failure rows', function (bool $exhaust) {
    $pending = true;
    $attempts = 0;
    Event::listen('eloquent.saving: '.Value::class, function (Value $value) use (&$pending, &$attempts, $exhaust): void {
        if ($pending && $value->label === 'Replan') {
            $attempts++;
            if ($exhaust || $attempts === 1) {
                throw CatalogWriteReplan::withMessages(['definition' => 'Participants changed']);
            }
        }
    });
    try {
        $job = nativeValueJob($this->import, [
            ['identity' => $this->nativePrefix.'_one', 'name' => 'First'],
            ['identity' => $this->nativePrefix.'_invalid', 'name' => ''],
            ['identity' => $this->nativePrefix.'_two', 'name' => 'Replan'],
        ])->withFakeQueueInteractions();
        $job->handle();
        if ($exhaust) {
            $job->assertFailedWith(CatalogWriteReplan::class);
        } else {
            $job->assertNotFailed();
        }
        expect($attempts)->toBe($exhaust ? 3 : 2)
            ->and($this->import->fresh()->processed_rows)->toBe($exhaust ? 0 : 3)
            ->and($this->import->fresh()->successful_rows)->toBe($exhaust ? 0 : 2)
            ->and($this->import->failedRows()->count())->toBe($exhaust ? 0 : 1)
            ->and(Value::where('semantic_id', 'like', $this->nativePrefix.'%')->count())->toBe($exhaust ? 0 : 2);
    } finally {
        $pending = false;
    }
})->with([false, true]);

test('a postcommit native event failure never replays the committed rows', function () {
    $pending = true;
    Event::listen(ImportChunkProcessed::class, function () use (&$pending): void {
        if ($pending) {
            throw new RuntimeException('Injected postcommit event failure');
        }
    });
    try {
        $job = nativeValueJob($this->import, [['identity' => $this->nativePrefix.'_one', 'name' => 'Committed']])->withFakeQueueInteractions();
        $job->handle();
        $job->assertFailedWith(RuntimeException::class);
        expect($this->import->fresh()->only(['processed_rows', 'successful_rows']))->toBe(['processed_rows' => 1, 'successful_rows' => 1])
            ->and(Value::where('semantic_id', $this->nativePrefix.'_one')->count())->toBe(1)
            ->and($this->import->failedRows()->count())->toBe(0);
    } finally {
        $pending = false;
    }
});

test('the native dispatcher reports unconfirmed chunk outcomes without a fabricated rejected-row download', function () {
    $pending = true;
    Event::listen('eloquent.saving: '.Value::class, function (Value $value) use (&$pending): void {
        if ($pending && $value->label === 'Crash') {
            throw new RuntimeException('Injected dispatcher chunk failure');
        }
    });
    try {
        session()->forget('filament.notifications');
        app(ImportDispatcher::class)->dispatch($this->import, [[
            ['semantic_id' => $this->nativePrefix.'_one', 'label' => 'First'],
            ['semantic_id' => $this->nativePrefix.'_two', 'label' => 'Crash'],
        ]], ['semantic_id' => 'semantic_id', 'label' => 'label'], [], CatalogDataActions::import('native', ValueImporter::class)->getJob(), 'web');
        $notification = collect(session('filament.notifications'))->last();
        expect($this->import->fresh()->completed_at)->not->toBeNull()
            ->and($this->import->failedRows()->count())->toBe(0)
            ->and($notification['title'])->toBe('Import processing finished')
            ->and($notification['status'])->toBe('warning')
            ->and($notification['body'])->toContain('0 recorded row rejections', '3 rows unconfirmed')
            ->and($notification['actions'])->toBe([]);
        $this->actingAs($this->actor)->get(route('filament.imports.failed-rows.download', $this->import))->assertNotFound();
    } finally {
        $pending = false;
    }
});

test('a failure after the database acknowledges commit retains data without replaying the native chunk', function () {
    $pending = true;
    Event::listen(TransactionCommitted::class, function (TransactionCommitted $event) use (&$pending): void {
        if ($pending && $event->connection->transactionLevel() === 0) {
            throw new RuntimeException('Injected committed callback failure');
        }
    });
    try {
        $job = nativeValueJob($this->import, [['identity' => $this->nativePrefix.'_one', 'name' => 'Committed once']])->withFakeQueueInteractions();
        $job->handle();
        $job->assertFailedWith(RuntimeException::class);
        expect(Value::where('semantic_id', $this->nativePrefix.'_one')->count())->toBe(1)
            ->and($this->import->fresh()->only(['processed_rows', 'successful_rows']))->toBe(['processed_rows' => 1, 'successful_rows' => 1]);
    } finally {
        $pending = false;
    }
});
