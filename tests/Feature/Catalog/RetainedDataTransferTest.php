<?php

use App\Actions\TransferRetainedApplicationData;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

function retainedPackageSource(): void
{
    config(['database.connections.catalog_source' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]);
    DB::purge('catalog_source');
    $default = DB::getDefaultConnection();
    DB::setDefaultConnection('catalog_source');
    try {
        foreach (glob(database_path('migrations/*.php')) as $file) {
            if (str_contains(basename($file), '0001_01_01_000000') || str_contains(basename($file), '2025_04_22') || str_contains(basename($file), '2025_09_22') || str_contains(basename($file), '2025_11_29') || str_contains(basename($file), '2025_12_14_011829')) {
                (require $file)->up();
            }
        }
        User::factory()->create(['id' => 42, 'email' => 'retained@example.test']);
        DB::table('bolt_forms')->insert(['id' => 23, 'user_id' => 42, 'name' => '{"en":"Retained form"}', 'slug' => 'retained-form', 'is_active' => true, 'options' => '{"require-login":true}']);
        DB::table('bolt_sections')->insert(['id' => 34, 'form_id' => 23, 'name' => '{"en":"Section"}']);
        DB::table('bolt_fields')->insert(['id' => 56, 'section_id' => 34, 'name' => '{"en":"Field"}', 'type' => '\\LaraZeus\\Bolt\\Fields\\Classes\\TextInput', 'options' => '{"is_required":true}']);
    } finally {
        DB::setDefaultConnection($default);
    }
}

test('reviewed retained rows transfer in dependency order with exact identities and unchanged repeats', function () {
    retainedPackageSource();
    $action = app(TransferRetainedApplicationData::class);
    $review = $action->transferAll('catalog_source', config('app.key'));
    $this->assertDatabaseCount('users', 0);
    expect($review['mode'])->toBe('dry-run')->and($review['tables']['bolt_fields']['created'])->toBe(1);
    $result = $action->transferAll('catalog_source', config('app.key'), true, $review['source_hash']);
    expect($result['mode'])->toBe('applied');
    foreach (['users' => 42, 'bolt_forms' => 23, 'bolt_sections' => 34, 'bolt_fields' => 56] as $table => $id) {
        expect((array) DB::table($table)->find($id))->toEqual((array) DB::connection('catalog_source')->table($table)->find($id));
    }
    $again = $action->transferAll('catalog_source', config('app.key'), true, $review['source_hash']);
    expect($again['tables']['bolt_fields']['unchanged'])->toBe(1)->and($again['tables']['users']['created'])->toBe(0);
    expect(User::factory()->create()->id)->toBeGreaterThan(42);
});

test('changed reviewed source or unclassified polymorphic content is rejected before target writes', function () {
    retainedPackageSource();
    $action = app(TransferRetainedApplicationData::class);
    $review = $action->transferAll('catalog_source', config('app.key'));
    DB::connection('catalog_source')->table('bolt_fields')->where('id', 56)->update(['name' => '{"en":"Updated"}']);
    expect(fn () => $action->transferAll('catalog_source', config('app.key'), true, $review['source_hash']))->toThrow(ValidationException::class);
    DB::connection('catalog_source')->table('workflows')->insert(['model_class' => 'App\\Models\\ProductProfile', 'workflow_name' => 'old', 'role' => 'staff']);
    expect(fn () => $action->transferAll('catalog_source', config('app.key')))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('bolt_forms', 0);
});

test('a late retained-data foreign-key failure rolls back earlier account and form inserts', function () {
    retainedPackageSource();
    DB::connection('catalog_source')->getSchemaBuilder()->disableForeignKeyConstraints();
    DB::connection('catalog_source')->table('bolt_fields')->where('id', 56)->update(['section_id' => 999]);
    DB::connection('catalog_source')->getSchemaBuilder()->enableForeignKeyConstraints();
    $action = app(TransferRetainedApplicationData::class);
    $review = $action->transferAll('catalog_source', config('app.key'));
    expect(fn () => $action->transferAll('catalog_source', config('app.key'), true, $review['source_hash']))->toThrow(QueryException::class);
    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('bolt_forms', 0);
    $this->assertDatabaseCount('bolt_fields', 0);
});

test('unreviewed retained package extensions files and model references fail closed', function (string $table, array $changes) {
    retainedPackageSource();
    DB::connection('catalog_source')->table($table)->update($changes);
    expect(fn () => app(TransferRetainedApplicationData::class)->transferAll('catalog_source', config('app.key')))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('users', 0);
})->with([
    'unknown field type' => ['bolt_fields', ['type' => '\\App\\Models\\ProductProfile']],
    'extension' => ['bolt_forms', ['extensions' => '{"model":"unknown"}']],
    'physical asset' => ['bolt_forms', ['options' => '{"logo":"retained/logo.png"}']],
    'legacy reference' => ['bolt_fields', ['options' => '{"owner":"App\\\\Models\\\\CatalogGroup"}']],
]);
