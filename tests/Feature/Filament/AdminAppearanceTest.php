<?php

use App\Actions\SaveAdminAppearanceSettings;
use App\Models\AdminAppearanceSettings;
use App\Models\User;
use App\Services\AdminAppearance;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
});

test('shared appearance validates fit persists defaults and refreshes cached values only on change', function () {
    $service = app(AdminAppearance::class);
    expect($service->current())->toBe($service->defaults());
    $action = app(SaveAdminAppearanceSettings::class);
    $record = $action->handle(auth()->user(), $service->defaults());
    expect($record->version)->toBe(1);
    $changed = [...$service->defaults(), 'cell_padding_block' => 4, 'sidebar_width' => 240];
    $record = $action->handle(auth()->user(), $changed);
    expect($record->version)->toBe(2)->and($service->current())->toBe($changed);
    expect($action->handle(auth()->user(), $changed)->version)->toBe(2);
    expect(fn () => $action->handle(auth()->user(), [...$changed, 'cell_padding_inline' => 2]))->toThrow(ValidationException::class);
    expect(fn () => $action->handle(auth()->user(), [...$changed, 'header_height' => 40, 'logo_height' => 32]))->toThrow(ValidationException::class);
    expect(AdminAppearanceSettings::findOrFail(1)->settings)->toBe($changed);
});

test('ordinary accounts cannot edit shared appearance and Reset only stages a draft', function () {
    $page = Livewire::test(App\Filament\Pages\AdminAppearance::class)
        ->fillForm(['cell_padding_block' => 4])->call('resetDefaults')->assertSet('data.cell_padding_block', 6);
    expect(AdminAppearanceSettings::findOrFail(1)->settings)->toBe(app(AdminAppearance::class)->defaults());
    $this->actingAs(User::factory()->create());
    $page->call('save')->assertForbidden();
});

test('new requests read committed appearance changes and rollback does not invalidate shared cache', function () {
    $originalConnection = DB::getDefaultConnection();
    config(['database.connections.appearance_cache_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]);
    DB::setDefaultConnection('appearance_cache_test');
    DB::connection()->setTransactionManager(new DatabaseTransactionsManager);
    Cache::forget(AdminAppearance::CACHE_KEY);
    try {
        $defaults = (new AdminAppearance)->defaults();
        expect((new AdminAppearance)->current())->toBe($defaults);
        expect(fn () => app(SaveAdminAppearanceSettings::class)->handle(auth()->user(), $defaults))->toThrow(ValidationException::class);
        (require database_path('migrations/2026_10_07_035850_create_admin_appearance_settings_table.php'))->up();
        expect((new AdminAppearance)->current())->toBe($defaults);
        $changed = [...$defaults, 'cell_padding_block' => 4];
        app(SaveAdminAppearanceSettings::class)->handle(auth()->user(), $changed);
        expect(Cache::has(AdminAppearance::CACHE_KEY))->toBeFalse();
        expect((new AdminAppearance)->current())->toBe($changed);
        try {
            DB::transaction(function () use ($defaults): void {
                app(SaveAdminAppearanceSettings::class)->handle(auth()->user(), $defaults);
                throw new RuntimeException('Cancel enclosing transaction');
            });
        } catch (RuntimeException) {
        }
        expect(Cache::get(AdminAppearance::CACHE_KEY))->toBe($changed);
        expect((new AdminAppearance)->current())->toBe($changed);
        expect(AdminAppearanceSettings::findOrFail(1)->version)->toBe(2);
    } finally {
        DB::setDefaultConnection($originalConnection);
        DB::purge('appearance_cache_test');
        Cache::forget(AdminAppearance::CACHE_KEY);
    }
});
