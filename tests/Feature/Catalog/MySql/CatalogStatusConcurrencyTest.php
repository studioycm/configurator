<?php

require_once dirname(__DIR__, 3).'/bootstrap-mysql.php';
assertCatalogMySqlSafety();

use App\Actions\ChangeCatalogStatus;
use App\Filament\Resources\Options\Pages\ListOptions;
use App\Models\Configurator;
use App\Models\ConfiguratorAttribute;
use App\Models\ConfiguratorOption;
use App\Models\Group;
use App\Models\Option;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

require_once dirname(__DIR__, 3).'/CatalogMySqlContention.php';

test('status selection acquires every owner before Group rows', function (bool $native) {
    DB::commit();
    $workers = [];
    $actor = null;
    $owners = collect();
    $options = collect();
    $group = null;
    try {
        $actor = User::factory()->create(['email' => 'ycm@data4.work']);
        $this->actingAs($actor);
        $owners = Configurator::factory()->count(2)->create();
        foreach ($owners as $owner) {
            $option = Option::factory()->create();
            $options->push($option);
            $inclusion = ConfiguratorAttribute::factory()->create(['configurator_id' => $owner->id, 'attribute_id' => $option->attribute_id]);
            ConfiguratorOption::factory()->create(['configurator_attribute_id' => $inclusion->id, 'option_id' => $option->id]);
        }
        $group = Group::factory()->create(['configurator_id' => $owners->first()->id]);
        DB::purge('mysql');
        $workers[] = startCatalogContentionWorker(function (mixed $channel) use ($actor, $options, $native): array {
            $paused = false;
            DB::listen(function (QueryExecuted $query) use (&$paused, $channel): void {
                if (! $paused && str_contains($query->sql, 'from `configurators`') && str_contains($query->sql, 'for update')) {
                    $paused = true;
                    writeCatalogContentionMessage($channel, ['phase' => 'owners_locked']);
                    readCatalogContentionMessage($channel);
                }
            });

            return catalogContentionResult(function () use ($actor, $options, $native): void {
                if ($native) {
                    Livewire::test(ListOptions::class)->selectTableRecords($options)
                        ->mountAction(TestAction::make('batchStatus')->table()->bulk())
                        ->fillForm(['is_active' => '0', 'visibility' => 'unchanged'])
                        ->callMountedAction()->assertHasNoFormErrors();
                } else {
                    app(ChangeCatalogStatus::class)->handleSelection($actor, $options->reverse(), false);
                }
            });
        });
        writeCatalogContentionMessage($workers[0]['channel'], ['phase' => 'start']);
        expect(readCatalogContentionMessage($workers[0]['channel']))->toBe(['phase' => 'owners_locked']);
        DB::statement('SET SESSION innodb_lock_wait_timeout = 1');
        expect(fn () => DB::transaction(fn () => Configurator::whereKey($owners->last()->id)->lockForUpdate()->firstOrFail()))
            ->toThrow(QueryException::class, '1205');
        expect(DB::transaction(fn () => Group::whereKey($group->id)->lockForUpdate()->firstOrFail())->id)->toBe($group->id);
        writeCatalogContentionMessage($workers[0]['channel'], ['phase' => 'release']);
        expect(readCatalogContentionMessage($workers[0]['channel']))->toMatchArray(['phase' => 'result', 'status' => 'committed']);
        expect(Option::whereKey($options->pluck('id')->all())->where('is_active', true)->count())->toBe(0);
    } finally {
        stopCatalogContentionWorkers($workers);
        if ($group !== null) {
            $group->delete();
        }
        foreach ($owners as $owner) {
            ConfiguratorOption::whereIn('configurator_attribute_id', $owner->attributes()->select('id'))->delete();
            $owner->attributes()->delete();
            $owner->delete();
        }
        foreach ($options as $option) {
            $option->delete();
            DB::table('attributes')->where('id', $option->attribute_id)->delete();
            DB::table('values')->where('id', $option->value_id)->delete();
        }
        $actor?->delete();
        DB::statement('SET SESSION innodb_lock_wait_timeout = 50');
        DB::beginTransaction();
    }
})->with(['application selection' => false, 'native bulk action' => true]);

test('a status review detects a write committed immediately before the selected record lock', function () {
    DB::commit();
    $actor = null;
    $option = null;
    $pending = false;
    $writerName = 'catalog_status_writer';
    config(['database.connections.'.$writerName => config('database.connections.mysql')]);
    $writer = DB::connection($writerName);
    try {
        $actor = User::factory()->create(['email' => 'ycm@data4.work']);
        $this->actingAs($actor);
        $option = Option::factory()->create();
        $page = Livewire::test(ListOptions::class)->mountAction(TestAction::make('changeStatus')->table($option))
            ->fillForm(['is_active' => '0', 'visibility' => 'visible']);
        DB::connection()->beforeExecuting(function (string $sql) use (&$pending, $writer, $option): void {
            if (! $pending || ! str_contains($sql, 'from `options`') || ! str_contains($sql, 'for update')) {
                return;
            }
            $pending = false;
            $writer->transaction(fn () => $writer->table('options')->where('id', $option->id)->update(['is_hidden' => true]));
        });
        $pending = true;
        $page->callMountedAction()->assertHasFormErrors(['is_active']);
        expect($pending)->toBeFalse()->and($option->fresh()->is_active)->toBeTrue()->and($option->fresh()->is_hidden)->toBeTrue();
    } finally {
        $pending = false;
        if ($option !== null) {
            DB::table('options')->where('id', $option->id)->delete();
            DB::table('attributes')->where('id', $option->attribute_id)->delete();
            DB::table('values')->where('id', $option->value_id)->delete();
        }
        if ($actor !== null) {
            DB::table('users')->where('id', $actor->id)->delete();
        }
        DB::purge($writerName);
        DB::beginTransaction();
    }
});
