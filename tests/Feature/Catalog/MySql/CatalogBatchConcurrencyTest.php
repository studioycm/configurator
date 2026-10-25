<?php

require_once dirname(__DIR__, 3).'/bootstrap-mysql.php';
assertCatalogMySqlSafety();

use App\Actions\BatchCatalogChanges;
use App\Actions\SaveConfiguratorDefinition;
use App\Models\Attribute;
use App\Models\ConfiguratorOption;
use App\Models\Option;
use App\Models\User;
use App\Services\ConfiguratorDefinitionLoader;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require_once dirname(__DIR__, 3).'/CatalogMySqlContention.php';
require_once dirname(__DIR__, 3).'/ConfiguratorFixtures.php';

test('a batch locks the complete canonical parent set before its first row writer', function () {
    DB::commit();
    $actor = null;
    $attributes = collect();
    $options = collect();
    $workers = [];
    try {
        $actor = User::factory()->create(['email' => 'ycm@data4.work']);
        $attributes = Attribute::factory()->count(2)->create();
        foreach ($attributes->reverse() as $index => $attribute) {
            $options->push(Option::factory()->create(['attribute_id' => $attribute->id, 'code' => 'X'.$index]));
        }
        $intent = ['operation' => 'replace', 'field' => 'code', 'mode' => 'literal', 'search' => 'X', 'replacement' => 'Y', 'case_sensitive' => true, 'occurrences' => 'all'];
        $ids = $options->pluck('id')->all();
        $preview = app(BatchCatalogChanges::class)->preview($actor, 'options', $ids, $intent);
        DB::purge('mysql');
        $workers[] = startCatalogContentionWorker(function (mixed $channel) use ($actor, $ids, $intent, $preview): array {
            $paused = false;
            DB::listen(function (QueryExecuted $query) use (&$paused, $channel): void {
                if (! $paused && str_contains($query->sql, 'from `attributes`') && str_contains($query->sql, 'for update')) {
                    $paused = true;
                    writeCatalogContentionMessage($channel, ['phase' => 'parents_locked']);
                    readCatalogContentionMessage($channel);
                }
            });

            return catalogContentionResult(fn () => app(BatchCatalogChanges::class)->apply($actor, 'options', $ids, $intent, $preview['token']));
        });
        writeCatalogContentionMessage($workers[0]['channel'], ['phase' => 'start']);
        expect(readCatalogContentionMessage($workers[0]['channel']))->toBe(['phase' => 'parents_locked']);
        DB::statement('SET SESSION innodb_lock_wait_timeout = 1');
        expect(fn () => DB::transaction(fn () => Attribute::whereKey($attributes->first()->id)->lockForUpdate()->firstOrFail()))->toThrow(QueryException::class, '1205');
        writeCatalogContentionMessage($workers[0]['channel'], ['phase' => 'release']);
        expect(readCatalogContentionMessage($workers[0]['channel']))->toMatchArray(['phase' => 'result', 'status' => 'committed']);
        expect(Option::whereKey($ids)->orderBy('id')->pluck('code')->all())->toBe(['Y1', 'Y0']);
    } finally {
        stopCatalogContentionWorkers($workers);
        foreach ($options as $option) {
            $option->delete();
            DB::table('values')->where('id', $option->value_id)->delete();
        }
        foreach ($attributes as $attribute) {
            $attribute->delete();
        }
        $actor?->delete();
        DB::statement('SET SESSION innodb_lock_wait_timeout = 50');
        DB::beginTransaction();
    }
});

test('a local batch rejects a stale owner draft after waiting for its Configurator', function () {
    DB::commit();
    $actor = null;
    $owner = null;
    $attributes = [];
    $options = collect();
    $pending = false;
    $writerName = 'catalog_batch_writer';
    config(['database.connections.'.$writerName => config('database.connections.mysql')]);
    $writer = DB::connection($writerName);
    try {
        $actor = User::factory()->create(['email' => 'ycm@data4.work']);
        [$owner, $draft] = canonicalDefinitionFixture();
        $attributes = array_column($draft['attributes'], 'attribute_id');
        $options = Option::whereIn('attribute_id', $attributes)->get();
        $draft['rules'][] = fixtureMapping();
        app(SaveConfiguratorDefinition::class)->handle($actor, $owner, $draft);
        $rule = $owner->rules()->sole();
        $inclusion = $owner->attributes()->orderBy('id')->firstOrFail();
        $intent = ['operation' => 'edit', 'changes' => [['field' => 'label', 'mode' => 'set', 'value' => 'Batch label']]];
        $preview = app(BatchCatalogChanges::class)->preview($actor, 'configurator-rules', [$rule->id], $intent, $owner->id);
        DB::connection()->beforeExecuting(function (string $sql) use (&$pending, $writer, $inclusion): void {
            if ($pending && str_contains($sql, 'from `configurators`') && str_contains($sql, 'for update')) {
                $pending = false;
                $writer->transaction(fn () => $writer->table('configurator_attributes')->where('id', $inclusion->id)->update(['help_text' => 'Committed by another editor']));
            }
        });
        $pending = true;
        expect(fn () => app(BatchCatalogChanges::class)->apply($actor, 'configurator-rules', [$rule->id], $intent, $preview['token'], $owner->id))->toThrow(ValidationException::class);
        expect($pending)->toBeFalse()->and($rule->fresh()->label)->toBe('map')
            ->and($inclusion->fresh()->help_text)->toBe('Committed by another editor');
        expect(app(ConfiguratorDefinitionLoader::class)->draft($owner)['attributes'][0]['help_text'])->toBe('Committed by another editor');
    } finally {
        $pending = false;
        if ($owner !== null) {
            $owner->attributes()->update(['default_configurator_option_id' => null]);
            $owner->rules()->delete();
            ConfiguratorOption::whereIn('configurator_attribute_id', $owner->attributes()->select('id'))->delete();
            $owner->attributes()->delete();
            $owner->delete();
        }
        foreach ($options as $option) {
            $option->delete();
            DB::table('values')->where('id', $option->value_id)->delete();
        }
        Attribute::whereKey($attributes)->delete();
        $actor?->delete();
        DB::purge($writerName);
        DB::beginTransaction();
    }
});
