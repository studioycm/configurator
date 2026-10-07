<?php

require_once dirname(__DIR__, 3).'/bootstrap-mysql.php';
assertCatalogMySqlSafety();

use App\Filament\Resources\Options\Pages\ListOptions;
use App\Models\Option;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

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
        $page->callMountedAction()->assertHasActionErrors(['is_active']);
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
