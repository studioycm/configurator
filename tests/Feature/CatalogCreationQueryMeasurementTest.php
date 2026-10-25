<?php

use App\Actions\SaveConfiguratorDefinition;
use App\Filament\Forms\Components\WorkspaceTableSelect;
use App\Filament\Resources\Attributes\Pages\ListAttributes;
use App\Filament\Resources\Configurators\Pages\EditConfigurator;
use App\Filament\Resources\Configurators\RelationManagers\AttributesRelationManager;
use App\Filament\Resources\Configurators\RelationManagers\OptionsRelationManager;
use App\Filament\Resources\Values\Pages\ListValues;
use App\Livewire\Catalog\WorkspaceTablePicker;
use App\Models\Attribute;
use App\Models\Configurator;
use App\Models\Option;
use App\Models\User;
use App\Models\Value;
use App\Services\ConfiguratorDefinitionLoader;
use Filament\Actions\Testing\TestAction;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Livewire;

require_once __DIR__.'/../ConfiguratorFixtures.php';

/**
 * Counts only the supplied native component operation, after its fixtures exist.
 * Label-map reads are canonical ID-list reads in the UI call stack; picker reads are
 * attributed to the actual mounted picker on the query's PHP call stack.
 *
 * @param  array<string, array{total:int, tag_vocabulary:int, label_map:int, picker_list:int, tag_callers:array<string,int>, http_requests:list<array<string,mixed>>}>  $measurements
 */
function measureCreationQueries(string $name, Closure $operation, array &$measurements): mixed
{
    $counts = ['total' => 0, 'tag_vocabulary' => 0, 'label_map' => 0, 'picker_list' => 0, 'tag_callers' => []];
    $recording = true;
    $requestCounts = [];
    $currentRequest = ['total' => 0, 'tag_vocabulary' => 0, 'label_map' => 0, 'picker_list' => 0, 'tag_callers' => []];
    Event::listen(RequestHandled::class, function () use (&$recording, &$requestCounts, &$currentRequest): void {
        if ($recording) {
            $requestCounts[] = $currentRequest;
            $currentRequest = ['total' => 0, 'tag_vocabulary' => 0, 'label_map' => 0, 'picker_list' => 0, 'tag_callers' => []];
        }
    });
    DB::listen(function (QueryExecuted $query) use (&$recording, &$counts, &$currentRequest): void {
        if (! $recording) {
            return;
        }
        $counts['total']++;
        $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS);
        $inRequest = array_any($trace, fn (array $frame): bool => ($frame['object'] ?? null) instanceof Kernel);
        if ($inRequest) {
            $currentRequest['total']++;
        }
        $sql = str_replace('`', '"', $query->sql);
        if (preg_match('/^select "tags" from "values" where "tags" is not null/i', $sql) === 1) {
            $counts['tag_vocabulary']++;
            $caller = array_find($trace, fn (array $frame): bool => ($frame['class'] ?? null) === Value::class && ($frame['function'] ?? null) === 'tagVocabulary');
            $site = isset($caller['file'], $caller['line']) ? str_replace(base_path().'/', '', $caller['file']).':'.$caller['line'] : 'unknown';
            $counts['tag_callers'][$site] = ($counts['tag_callers'][$site] ?? 0) + 1;
            if ($inRequest) {
                $currentRequest['tag_vocabulary']++;
                $currentRequest['tag_callers'][$site] = ($currentRequest['tag_callers'][$site] ?? 0) + 1;
            }
        }
        $origin = array_find($trace, fn (array $frame): bool => str_starts_with($frame['file'] ?? '', app_path().'/'));
        $picker = array_any($trace, fn (array $frame): bool => ($frame['object'] ?? null) instanceof WorkspaceTablePicker);
        if ($picker && ! preg_match('/^select "tags" from "values" where "tags" is not null/i', $sql) && str_starts_with(strtolower($sql), 'select ') && preg_match('/from "(values|attributes|options)"/', $sql) === 1) {
            $counts['picker_list']++;
            if ($inRequest) {
                $currentRequest['picker_list']++;
            }
        } elseif (str_starts_with($origin['file'] ?? '', app_path('Filament').'/') && preg_match('/^select (?!.*(?:count\(|exists\()).* from "(values|attributes|options)".* where .*"id" in \(/i', $sql) === 1) {
            $counts['label_map']++;
            if ($inRequest) {
                $currentRequest['label_map']++;
            }
        }
    });
    try {
        return $operation();
    } finally {
        $recording = false;
        $measurements[$name] = [...$counts, 'http_requests' => $requestCounts];
        if (getenv('CATALOG_QUERY_TRACE')) {
            fwrite(STDERR, 'CREATION_QUERIES '.json_encode(['operation' => $name, ...$measurements[$name]], JSON_THROW_ON_ERROR)."\n");
        }
    }
}

/** @return array{ref:string, label:string, semantic_id:string, tags:array, description:string} */
function measuredMasterRow(int $index): array
{
    return ['ref' => 'new:'.Str::uuid(), 'label' => 'Measured Master '.$index, 'semantic_id' => 'measured_master_'.$index, 'tags' => ['measured', 'batch'], 'description' => 'Measured specification '.$index];
}

function measuredPickerCount(Component $component): int
{
    return collect($component->getSchema('mountedActionSchema0')->getFlatComponents())
        ->filter(fn ($field): bool => $field instanceof WorkspaceTableSelect)->count();
}

test('V28 measures empty Master action ten native rows and one actual submit separately', function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    Value::factory()->create(['tags' => ['existing vocabulary']]);
    $measurements = [];
    $list = Livewire::test(ListValues::class);
    measureCreationQueries('masters.empty-open', fn () => $list->mountAction(TestAction::make('createMany')->table()), $measurements);
    measureCreationQueries('masters.append-five', fn () => $list->callAction(TestAction::make('add5')->schemaComponent('master_values', 'mountedActionSchema0')), $measurements);
    for ($index = 0; $index < 4; $index++) {
        measureCreationQueries('masters.append-one-'.$index, fn () => $list->callAction(TestAction::make('add')->schemaComponent('master_values', 'mountedActionSchema0')), $measurements);
    }
    $rows = [];
    foreach (array_keys($list->get('mountedActions.0.data.master_values')) as $index => $key) {
        $rows[$key] = measuredMasterRow($index);
    }
    measureCreationQueries('masters.fill-ten', fn () => $list->set('mountedActions.0.data.master_values', $rows), $measurements);
    measureCreationQueries('masters.submit-ten', fn () => $list->callMountedAction()->assertHasNoFormErrors()->assertNotified(), $measurements);

    expect(Value::whereIn('semantic_id', array_column($rows, 'semantic_id'))->count())->toBe(10);
    foreach ($measurements['masters.empty-open']['http_requests'] as $request) {
        expect($request['tag_vocabulary'])->toBe(1);
    }
    expect($measurements['masters.fill-ten']['picker_list'])->toBe(0);
});

test('V28 measures three native Attribute drafts ten staged Masters picker switching and one final submit', function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    Value::factory()->count(26)->create(['tags' => ['existing vocabulary']]);
    $measurements = [];
    $list = Livewire::test(ListAttributes::class);
    measureCreationQueries('shared.empty-open', fn () => $list->mountAction(TestAction::make('createMany')->table()), $measurements);
    for ($index = 0; $index < 2; $index++) {
        measureCreationQueries('shared.add-attribute-'.$index, fn () => $list->callAction(TestAction::make('add')->schemaComponent('attributes', 'mountedActionSchema0')), $measurements);
    }
    $attributes = $list->get('mountedActions.0.data.attributes');
    $updates = [];
    foreach (array_keys($attributes) as $index => $key) {
        $updates['mountedActions.0.data.attributes.'.$key.'.label'] = 'Measured Attribute '.$index;
        $updates['mountedActions.0.data.attributes.'.$key.'.key'] = 'measured_attribute_'.$index;
    }
    measureCreationQueries('shared.name-three', fn () => $list->set($updates), $measurements);
    [$a, $b] = array_keys($attributes);
    $open = fn (string $key): TestAction => TestAction::make('openValues')->schemaComponent('attributes.'.$key.'.attribute-values', 'mountedActionSchema0');
    measureCreationQueries('shared.open-picker-a', fn () => $list->callAction($open($a)), $measurements);
    expect(measuredPickerCount($list->instance()))->toBe(1);
    $composer = [];
    for ($index = 0; $index < 10; $index++) {
        $composer[(string) Str::uuid()] = measuredMasterRow($index);
    }
    measureCreationQueries('shared.fill-composer-ten', fn () => $list->set('mountedActions.0.data.attributes.'.$a.'._master_composer', $composer), $measurements);
    measureCreationQueries('shared.stage-ten', fn () => $list->callAction(TestAction::make('createAndUse')->schemaComponent('attributes.'.$a.'.attribute-values', 'mountedActionSchema0'))->assertHasNoFormErrors(), $measurements);
    expect(Value::whereIn('semantic_id', array_column($composer, 'semantic_id'))->exists())->toBeFalse();
    measureCreationQueries('shared.switch-a-b', fn () => $list->callAction($open($b)), $measurements);
    expect(measuredPickerCount($list->instance()))->toBe(1);
    $pool = $list->get('mountedActions.0.data.master_values');
    measureCreationQueries('shared.select-pending-b', fn () => $list->set('mountedActions.0.data.attributes.'.$b.'._selected_pending', [array_values($pool)[0]['ref']]), $measurements);
    measureCreationQueries('shared.stage-pending-b', fn () => $list->callAction(TestAction::make('useSelected')->schemaComponent('attributes.'.$b.'.attribute-values', 'mountedActionSchema0')), $measurements);
    measureCreationQueries('shared.switch-b-a', fn () => $list->callAction($open($a)), $measurements);
    expect(measuredPickerCount($list->instance()))->toBe(1);
    $updates = [];
    foreach ([$a, $b] as $parentIndex => $key) {
        foreach (array_keys($list->get('mountedActions.0.data.attributes.'.$key.'.values')) as $index => $valueKey) {
            $updates['mountedActions.0.data.attributes.'.$key.'.values.'.$valueKey.'.code'] = ($parentIndex === 0 ? 'M' : 'N').$index;
        }
    }
    measureCreationQueries('shared.enter-eleven-codes', fn () => $list->set($updates), $measurements);
    measureCreationQueries('shared.submit-three', fn () => $list->callMountedAction()->assertHasNoFormErrors()->assertNotified(), $measurements);

    expect(Attribute::whereIn('key', ['measured_attribute_0', 'measured_attribute_1', 'measured_attribute_2'])->count())->toBe(3);
    expect(Value::whereIn('semantic_id', array_column($composer, 'semantic_id'))->count())->toBe(10);
    expect(Option::whereIn('code', ['M0', 'M1', 'M2', 'M3', 'M4', 'M5', 'M6', 'M7', 'M8', 'M9', 'N0'])->count())->toBe(11);
    foreach ($measurements['shared.empty-open']['http_requests'] as $request) {
        expect($request['tag_vocabulary'])->toBe(1);
    }
    expect($measurements['shared.open-picker-a']['picker_list'])->toBeGreaterThan(0);
});

test('V28 measures native Configurator Add Attributes selection defaults and submit', function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    $owner = Configurator::factory()->create();
    $options = Option::factory()->count(3)->sequence(['code' => 'P1'], ['code' => 'P2'], ['code' => 'P3'])->create();
    $measurements = [];
    $manager = Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $owner, 'pageClass' => EditConfigurator::class]);
    measureCreationQueries('configurator.attributes.empty-open', fn () => $manager->mountAction(TestAction::make('includeMany')->table()), $measurements);
    measureCreationQueries('configurator.attributes.select-three', fn () => $manager->set('mountedActions.0.data.existing_attribute_ids', $options->pluck('attribute_id')->all()), $measurements);
    foreach ($manager->get('mountedActions.0.data.reviews') as $key => $review) {
        $valueKey = array_key_first($review['values']);
        measureCreationQueries('configurator.attributes.choose-default-'.count(array_filter(array_keys($measurements), fn (string $name): bool => str_starts_with($name, 'configurator.attributes.choose-default-'))), fn () => $manager->callAction(TestAction::make('setDefault')->schemaComponent('reviews.'.$key.'.values.'.$valueKey.'.review-default', 'mountedActionSchema0')), $measurements);
    }
    measureCreationQueries('configurator.attributes.submit-three', fn () => $manager->callMountedAction()->assertHasNoFormErrors()->assertNotified(), $measurements);

    $saved = app(ConfiguratorDefinitionLoader::class)->draft($owner->fresh());
    expect($saved['attributes'])->toHaveCount(3);
    foreach ($saved['attributes'] as $attribute) {
        expect($attribute['default_configurator_option_id'])->toBe($attribute['options'][0]['id']);
    }
    expect($measurements['configurator.attributes.empty-open']['picker_list'])->toBeGreaterThan(0);
});

test('V28 measures native Add Values mount selection and submit while retaining the saved default', function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    [$owner, $data] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $owner, $data);
    $inclusion = $owner->attributes()->firstOrFail();
    $defaultId = $inclusion->default_configurator_option_id;
    $option = Option::factory()->for($inclusion->attribute)->create(['code' => 'P4']);
    $measurements = [];
    $manager = Livewire::test(OptionsRelationManager::class, ['ownerRecord' => $inclusion, 'pageClass' => EditConfigurator::class]);
    measureCreationQueries('configurator.values.empty-open', fn () => $manager->mountAction(TestAction::make('includeMany')->table()), $measurements);
    measureCreationQueries('configurator.values.select-one', fn () => $manager->set('mountedActions.0.data.existing_option_ids', [$option->id]), $measurements);
    measureCreationQueries('configurator.values.submit-one', fn () => $manager->callMountedAction()->assertHasNoFormErrors()->assertNotified(), $measurements);

    expect($inclusion->fresh()->default_configurator_option_id)->toBe($defaultId);
    expect($inclusion->options()->where('option_id', $option->id)->exists())->toBeTrue();
    expect($measurements['configurator.values.empty-open']['picker_list'])->toBeGreaterThan(0);
});
