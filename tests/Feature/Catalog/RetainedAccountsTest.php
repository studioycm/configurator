<?php

use App\Actions\TransferRetainedApplicationData;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

function retainedAccountSource(): void
{
    config(['database.connections.catalog_source' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
    ]]);
    DB::purge('catalog_source');
    Schema::connection('catalog_source')->create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->timestamp('email_verified_at')->nullable();
        $table->string('password');
        $table->text('two_factor_secret')->nullable();
        $table->text('two_factor_recovery_codes')->nullable();
        $table->timestamp('two_factor_confirmed_at')->nullable();
        $table->rememberToken();
        $table->timestamps();
    });
}

test('retained accounts preserve ids hashes encrypted fields and source rows on repeat transfer', function () {
    retainedAccountSource();
    $user = User::factory()->connection('catalog_source')->create([
        'id' => 42, 'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt('["test-recovery"]'),
    ]);
    $before = (array) DB::connection('catalog_source')->table('users')->find(42);
    $action = app(TransferRetainedApplicationData::class);

    expect($action->handle('catalog_source', config('app.key')))->toBe(['created' => 1, 'unchanged' => 0, 'total' => 1]);
    expect($action->handle('catalog_source', config('app.key')))->toBe(['created' => 0, 'unchanged' => 1, 'total' => 1]);

    expect((array) DB::table('users')->select(array_keys($before))->find(42))->toBe($before);
    expect((array) DB::connection('catalog_source')->table('users')->find(42))->toBe($before);
    expect(User::findOrFail(42)->two_factor_secret)->toBe($user->two_factor_secret);
});

test('a retained-account identity conflict prevents every pending insert', function () {
    retainedAccountSource();
    User::factory()->connection('catalog_source')->create(['id' => 41]);
    User::factory()->connection('catalog_source')->create(['id' => 42]);
    $existing = User::factory()->create(['id' => 42]);

    expect(fn () => app(TransferRetainedApplicationData::class)->handle('catalog_source', config('app.key')))
        ->toThrow(ValidationException::class);

    $this->assertDatabaseCount('users', 1);
    expect(User::findOrFail(42)->email)->toBe($existing->email);
});

test('incompatible application encryption keys refuse account transfer', function () {
    retainedAccountSource();
    User::factory()->connection('catalog_source')->create();

    expect(fn () => app(TransferRetainedApplicationData::class)->handle('catalog_source', 'different-key'))
        ->toThrow(ValidationException::class);

    $this->assertDatabaseCount('users', 0);
});
