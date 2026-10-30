<?php

use App\Actions\SaveAdminAppearanceSettings;
use App\Filament\Pages\Dashboard;
use App\Models\AdminAppearanceSettings;
use App\Models\User;
use App\Services\AdminAppearance;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/** @param array<string, mixed> $settings @return array<string, mixed> */
function appearanceSettingsByKey(array $settings): array
{
    ksort($settings, SORT_STRING);

    return $settings;
}

beforeEach(function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
});

test('invalid stored appearance recovers affected fields without changing valid preferences or storage', function (array $overrides, array $replacements) {
    $appearance = app(AdminAppearance::class);
    $stored = [...$appearance->defaults(), 'sidebar_width' => 288, 'control_height' => 34, ...$overrides];
    $record = AdminAppearanceSettings::findOrFail(1);
    $record->update(['settings' => $stored]);
    $record->refresh();
    $raw = $record->getRawOriginal('settings');
    $version = $record->version;

    $resolved = $appearance->current();

    expect($resolved)->toBe([...$stored, ...$replacements]);
    expect($appearance->recovery())->toBe(['defaulted_fields' => array_keys($replacements), 'ignored_unknown_count' => 0, 'invalid_payload' => false]);
    expect($record->fresh()->getRawOriginal('settings'))->toBe($raw);
    expect($record->fresh()->version)->toBe($version);
})->with([
    'sidebar range' => [['sidebar_width' => 999], ['sidebar_width' => 216]],
    'invalid integer type' => [['workspace_gap' => []], ['workspace_gap' => 8]],
    'invalid dialog choice' => [['dialog_control_style' => 'hidden'], ['dialog_control_style' => 'icons']],
    'invalid boolean' => [['show_configurator_loading_indicator' => 'yes'], ['show_configurator_loading_indicator' => false]],
    'logo range' => [['logo_height' => 99], ['logo_height' => 28]],
    'logo fit' => [['header_height' => 40, 'logo_height' => 32], ['logo_height' => 28]],
    'logo and title fit' => [['header_height' => 40, 'logo_height' => 32, 'page_title_size' => 30], ['logo_height' => 28, 'page_title_size' => 20]],
]);

test('stored appearance recovery ignores unknown keys without exposing their content and logs once', function () {
    Log::spy();
    $appearance = app(AdminAppearance::class);
    $stored = [...$appearance->defaults(), 'sidebar_width' => 288, 'untrusted-secret-name' => 'private raw content'];
    AdminAppearanceSettings::findOrFail(1)->update(['settings' => $stored]);

    expect($appearance->current())->toBe([...$appearance->defaults(), 'sidebar_width' => 288]);
    expect($appearance->current())->toBe([...$appearance->defaults(), 'sidebar_width' => 288]);
    $report = ['defaulted_fields' => [], 'ignored_unknown_count' => 1, 'invalid_payload' => false];
    expect($appearance->recovery())->toBe($report);
    Log::shouldHaveReceived('warning')->once()->with('Stored admin appearance settings required recovery.', $report);
    expect(appearanceSettingsByKey(AdminAppearanceSettings::findOrFail(1)->settings))->toBe(appearanceSettingsByKey($stored));
});

test('unusable stored appearance payload falls back without silently repairing it', function (mixed $stored) {
    DB::table('admin_appearance_settings')->where('id', 1)->update(['settings' => json_encode($stored, JSON_THROW_ON_ERROR)]);
    $record = AdminAppearanceSettings::findOrFail(1);
    $raw = $record->getRawOriginal('settings');
    $appearance = app(AdminAppearance::class);

    expect($appearance->current())->toBe($appearance->defaults());
    expect($appearance->recovery())->toBe(['defaulted_fields' => [], 'ignored_unknown_count' => 0, 'invalid_payload' => true]);
    expect($record->fresh()->getRawOriginal('settings'))->toBe($raw);
})->with(['JSON null' => [null], 'JSON string' => ['not a settings map'], 'JSON integer' => [3]]);

test('legacy omissions and valid coercible appearance values do not trigger recovery', function () {
    $appearance = app(AdminAppearance::class);
    $stored = ['sidebar_width' => '288', 'show_configurator_loading_indicator' => '0'];
    AdminAppearanceSettings::findOrFail(1)->update(['settings' => $stored]);

    expect($appearance->current())->toBe([...$appearance->defaults(), 'sidebar_width' => 288]);
    expect($appearance->recovery())->toBeNull();
    expect(appearanceSettingsByKey(AdminAppearanceSettings::findOrFail(1)->settings))->toBe(appearanceSettingsByKey($stored));
});

test('cold ordinary admin page renders recovery notice and manager repair link', function () {
    $appearance = new AdminAppearance;
    app()->instance(AdminAppearance::class, $appearance);
    $stored = [...$appearance->defaults(), 'sidebar_width' => 999];
    AdminAppearanceSettings::findOrFail(1)->update(['settings' => $stored]);

    $response = $this->get(Dashboard::getUrl());

    $response->assertSee(__('admin.appearance.recovery.notice'));
    $response->assertSee(__('admin.appearance.recovery.repair'));
    expect(appearanceSettingsByKey(AdminAppearanceSettings::findOrFail(1)->settings))->toBe(appearanceSettingsByKey($stored));
});

test('appearance recovery notice does not offer repair permission to ordinary readers', function () {
    $this->actingAs(User::factory()->create());

    $html = view('filament.resources.appearance-recovery-notice', ['recovery' => ['defaulted_fields' => ['sidebar_width'], 'ignored_unknown_count' => 0, 'invalid_payload' => false]])->render();

    expect($html)->toContain(__('admin.appearance.recovery.notice'))->not->toContain(__('admin.appearance.recovery.repair'));
});

test('recovery metadata is query free and repair page can explicitly save validated settings', function () {
    $appearance = app(AdminAppearance::class);
    AdminAppearanceSettings::findOrFail(1)->update(['settings' => [...$appearance->defaults(), 'sidebar_width' => 999]]);
    $appearance->current();
    DB::enableQueryLog();
    DB::flushQueryLog();
    $appearance->recovery();
    expect(DB::getQueryLog())->toBe([]);
    DB::disableQueryLog();

    Livewire::test(App\Filament\Pages\AdminAppearance::class)->fillForm(['sidebar_width' => 288])->call('save')->assertHasNoFormErrors();

    expect($appearance->current()['sidebar_width'])->toBe(288);
    expect($appearance->recovery())->toBeNull();
    expect(AdminAppearanceSettings::findOrFail(1)->version)->toBe(2);
    expect(fn () => $appearance->validate([...$appearance->defaults(), 'unknown' => true]))->toThrow(ValidationException::class);
    $this->get(App\Filament\Pages\AdminAppearance::getUrl())->assertDontSee(__('admin.appearance.recovery.notice'));
});

test('appearance recovery does not hide missing singleton or cache failures', function (string $failure) {
    withConsolidationAppearanceConnection(function (AdminAppearance $appearance) use ($failure): void {
        if ($failure === 'missing row') {
            AdminAppearanceSettings::whereKey(1)->delete();

            expect(fn () => $appearance->current())->toThrow(ModelNotFoundException::class);
        } else {
            $cache = Mockery::mock(Cache::getFacadeRoot());
            $cache->shouldReceive('remember')->once()->andThrow(new RuntimeException('Cache unavailable'));
            Cache::swap($cache);

            expect(fn () => $appearance->current())->toThrow(RuntimeException::class, 'Cache unavailable');
        }
        expect($appearance->recovery())->toBeNull();
    });
})->with(['missing row', 'cache failure']);

test('appearance recovery follows repair rollback and committed repair without publishing fallback to cache', function (bool $warm) {
    withConsolidationAppearanceConnection(function (AdminAppearance $appearance) use ($warm): void {
        $invalid = [...$appearance->defaults(), 'sidebar_width' => 999, 'control_height' => 34];
        AdminAppearanceSettings::findOrFail(1)->update(['settings' => $invalid]);
        Cache::put(AdminAppearance::CACHE_KEY, $invalid);
        if ($warm) {
            $appearance->current();
        }
        DB::beginTransaction();
        $appearance->current();
        expect($appearance->recovery()['defaulted_fields'])->toBe(['sidebar_width']);
        app(SaveAdminAppearanceSettings::class)->handle(auth()->user(), [...$appearance->defaults(), 'sidebar_width' => 288]);
        expect($appearance->current()['sidebar_width'])->toBe(288);
        expect($appearance->recovery())->toBeNull();
        expect(Cache::get(AdminAppearance::CACHE_KEY))->toBe($invalid);
        DB::rollBack();
        expect($appearance->current()['control_height'])->toBe(34);
        expect($appearance->recovery()['defaulted_fields'])->toBe(['sidebar_width']);
        expect(appearanceSettingsByKey(AdminAppearanceSettings::findOrFail(1)->settings))->toBe(appearanceSettingsByKey($invalid));
        app(SaveAdminAppearanceSettings::class)->handle(auth()->user(), [...$appearance->defaults(), 'sidebar_width' => 288]);
        expect($appearance->current()['sidebar_width'])->toBe(288);
        expect($appearance->recovery())->toBeNull();
        expect(AdminAppearanceSettings::findOrFail(1)->version)->toBe(2);
    });
})->with([true, false]);

test('dialog display preferences backfill old settings without rewriting them and cannot hide every control', function () {
    $record = AdminAppearanceSettings::findOrFail(1);
    $stored = $record->settings;
    unset($stored['dialog_control_style'], $stored['dialog_control_radius']);
    $record->update(['settings' => $stored]);
    Cache::forget(AdminAppearance::CACHE_KEY);
    $service = new AdminAppearance;
    expect($service->current()['dialog_control_style'])->toBe('icons');
    expect(appearanceSettingsByKey($record->fresh()->settings))->toBe(appearanceSettingsByKey($stored));
    foreach (['icons', 'icons_text', 'text'] as $style) {
        Livewire::test(App\Filament\Pages\AdminAppearance::class)->fillForm(['dialog_control_style' => $style, 'dialog_control_radius' => 8])
            ->call('savePersonal')->assertHasNoFormErrors()->assertDispatched('admin-personal-appearance-saved');
    }
    expect(appearanceSettingsByKey($record->fresh()->settings))->toBe(appearanceSettingsByKey($stored));
    expect(fn () => $service->validate([...$service->defaults(), 'dialog_control_style' => 'hidden']))->toThrow(ValidationException::class);
});

test('shared appearance validates fit persists defaults and refreshes cached values only on change', function () {
    $service = app(AdminAppearance::class);
    AdminAppearanceSettings::findOrFail(1)->update(['settings' => $service->defaults()]);
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
    expect(appearanceSettingsByKey(AdminAppearanceSettings::findOrFail(1)->settings))->toBe(appearanceSettingsByKey($changed));
});

test('ordinary accounts cannot edit shared appearance and Reset only stages a draft', function () {
    $original = AdminAppearanceSettings::findOrFail(1)->settings;
    $page = Livewire::test(App\Filament\Pages\AdminAppearance::class)
        ->fillForm(['cell_padding_block' => 4])->call('resetDefaults')->assertSet('data.cell_padding_block', 6);
    expect(appearanceSettingsByKey(AdminAppearanceSettings::findOrFail(1)->settings))->toBe(appearanceSettingsByKey($original));
    $this->actingAs(User::factory()->create());
    $page->call('save')->assertForbidden();
});

test('legacy appearance settings default loading off and the admin can persist its explicit toggle', function () {
    $original = AdminAppearanceSettings::findOrFail(1)->settings;
    Cache::put(AdminAppearance::CACHE_KEY, $original);

    expect((new AdminAppearance)->current()['show_configurator_loading_indicator'])->toBeFalse();
    expect(appearanceSettingsByKey(AdminAppearanceSettings::findOrFail(1)->settings))->toBe(appearanceSettingsByKey($original));

    Livewire::test(App\Filament\Pages\AdminAppearance::class)
        ->fillForm(['show_configurator_loading_indicator' => true])->call('save')->assertHasNoFormErrors();

    expect(AdminAppearanceSettings::findOrFail(1)->settings['show_configurator_loading_indicator'])->toBeTrue();
    expect((new AdminAppearance)->current()['show_configurator_loading_indicator'])->toBeTrue();
    expect(fn () => app(SaveAdminAppearanceSettings::class)->handle(auth()->user(), [...app(AdminAppearance::class)->defaults(), 'show_configurator_loading_indicator' => 'yes']))->toThrow(ValidationException::class);
    expect(AdminAppearanceSettings::findOrFail(1)->settings['show_configurator_loading_indicator'])->toBeTrue();
});

test('legacy appearance settings supply a page title size without changing stored settings', function () {
    $original = AdminAppearanceSettings::findOrFail(1)->settings;
    unset($original['page_title_size']);
    AdminAppearanceSettings::findOrFail(1)->update(['settings' => $original]);
    Cache::put(AdminAppearance::CACHE_KEY, $original);

    expect((new AdminAppearance)->current()['page_title_size'])->toBe(20);
    expect(appearanceSettingsByKey(AdminAppearanceSettings::findOrFail(1)->settings))->toBe(appearanceSettingsByKey($original));
});

test('admins can preview and save a page title size that fits the header', function () {
    $original = AdminAppearanceSettings::findOrFail(1)->settings;
    $page = Livewire::test(App\Filament\Pages\AdminAppearance::class)
        ->fillForm(['page_title_size' => 28, 'logo_height' => 28, 'header_height' => 48]);

    expect(appearanceSettingsByKey(AdminAppearanceSettings::findOrFail(1)->settings))->toBe(appearanceSettingsByKey($original));
    $preview = app(AdminAppearance::class)->variables($page->get('data'));
    expect($preview['--aquestia-shell-heading-size'])->toBe('28px');
    expect($preview['--aquestia-shell-heading-line-height'])->toBe('34px');
    expect($preview['--aquestia-shell-logo-height'])->toBe('28px');

    $page->call('save')->assertHasNoFormErrors();
    expect(AdminAppearanceSettings::findOrFail(1)->settings['page_title_size'])->toBe(28);
    expect((new AdminAppearance)->current()['page_title_size'])->toBe(28);

    $page->fillForm(['page_title_size' => 33])->call('save')->assertHasFormErrors(['page_title_size']);
    $page->fillForm(['page_title_size' => 28, 'header_height' => 40])->call('save')->assertHasFormErrors(['page_title_size']);
    expect(AdminAppearanceSettings::findOrFail(1)->settings['page_title_size'])->toBe(28);
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

function withConsolidationAppearanceConnection(Closure $check): void
{
    $original = DB::getDefaultConnection();
    config(['database.connections.appearance_memo_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]);
    DB::setDefaultConnection('appearance_memo_test');
    DB::connection()->setTransactionManager(new DatabaseTransactionsManager);
    Cache::forget(AdminAppearance::CACHE_KEY);
    app()->instance(AdminAppearance::class, new AdminAppearance);
    try {
        (require database_path('migrations/2026_10_07_035850_create_admin_appearance_settings_table.php'))->up();
        $check(app(AdminAppearance::class));
    } finally {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        DB::setDefaultConnection($original);
        DB::purge('appearance_memo_test');
        Cache::forget(AdminAppearance::CACHE_KEY);
    }
}

test('one appearance instance reads transactional changes without promoting rollback values', function (bool $warm) {
    withConsolidationAppearanceConnection(function (AdminAppearance $appearance) use ($warm): void {
        $defaults = $appearance->defaults();
        if ($warm) {
            expect($appearance->current())->toBe($defaults);
        }
        Cache::put(AdminAppearance::CACHE_KEY, $defaults, 3600);
        DB::beginTransaction();
        $changed = [...$defaults, 'cell_padding_block' => 4];
        AdminAppearanceSettings::findOrFail(1)->update(['settings' => $changed]);
        expect($appearance->current())->toBe($changed)
            ->and(Cache::get(AdminAppearance::CACHE_KEY))->toBe($defaults);
        DB::rollBack();
        expect($appearance->current())->toBe($defaults);
    });
})->with([true, false]);

test('one appearance instance follows nested saves caught rollback and outer rollback', function (bool $warm) {
    withConsolidationAppearanceConnection(function (AdminAppearance $appearance) use ($warm): void {
        $defaults = $appearance->defaults();
        if ($warm) {
            $appearance->current();
        }
        Cache::put(AdminAppearance::CACHE_KEY, $defaults, 3600);
        DB::beginTransaction();
        $changed = [...$defaults, 'cell_padding_block' => 4];
        app(SaveAdminAppearanceSettings::class)->handle(auth()->user(), $changed);
        expect($appearance->current())->toBe($changed);
        try {
            DB::transaction(function () use ($changed): void {
                app(SaveAdminAppearanceSettings::class)->handle(auth()->user(), [...$changed, 'workspace_gap' => 10]);
                throw new RuntimeException('Cancel nested appearance save');
            });
        } catch (RuntimeException) {
        }
        expect($appearance->current())->toBe($changed);
        app(SaveAdminAppearanceSettings::class)->handle(auth()->user(), [...$changed, 'workspace_gap' => 12]);
        expect($appearance->current()['workspace_gap'])->toBe(12)
            ->and(Cache::get(AdminAppearance::CACHE_KEY))->toBe($defaults);
        DB::rollBack();
        expect($appearance->current())->toBe($defaults)
            ->and(Cache::get(AdminAppearance::CACHE_KEY))->toBe($defaults);
    });
})->with([true, false]);

test('appearance cache invalidation waits for physical commit and repeated saves refresh the same instance', function () {
    withConsolidationAppearanceConnection(function (AdminAppearance $appearance): void {
        $defaults = $appearance->current();
        $cached = Cache::get(AdminAppearance::CACHE_KEY);
        expect($cached)->toBeArray();
        DB::beginTransaction();
        $changed = [...$defaults, 'cell_padding_block' => 4];
        app(SaveAdminAppearanceSettings::class)->handle(auth()->user(), $changed);
        expect($appearance->current())->toBe($changed)
            ->and(Cache::get(AdminAppearance::CACHE_KEY))->toBe($cached);
        DB::commit();
        expect(Cache::has(AdminAppearance::CACHE_KEY))->toBeFalse()
            ->and($appearance->current())->toBe($changed);
        $next = [...$changed, 'workspace_gap' => 12];
        app(SaveAdminAppearanceSettings::class)->handle(auth()->user(), $next);
        expect($appearance->current())->toBe($next)
            ->and(appearanceSettingsByKey(AdminAppearanceSettings::findOrFail(1)->settings))->toBe(appearanceSettingsByKey($next));
    });
});
