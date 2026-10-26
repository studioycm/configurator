<?php

use App\Actions\AddConfiguratorAttributes;
use App\Actions\AddConfiguratorValues;
use App\Actions\CreateMasterValues;
use App\Actions\SaveConfiguratorDefinition;
use App\Filament\Resources\Configurators\Pages\EditConfigurator;
use App\Filament\Resources\Configurators\RelationManagers\AttributesRelationManager;
use App\Filament\Resources\Configurators\RelationManagers\OptionsRelationManager;
use App\Models\Attribute;
use App\Models\Configurator;
use App\Models\Option;
use App\Models\User;
use App\Models\Value;
use App\Services\ConfiguratorDefinitionLoader;
use App\Services\ConfiguratorInclusionDrafts;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

require_once __DIR__.'/../ConfiguratorFixtures.php';

beforeEach(function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    Exceptions::fake();
});

function creationRenderedPickers(string $html): int
{
    $document = new DOMDocument;
    $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);

    return (new DOMXPath($document))->query('//*[@data-workspace-row-selection]')->length;
}

function creationRef(): string
{
    return 'new:'.Str::uuid();
}

function creationReview(int $attributeId): array
{
    $draft = app(ConfiguratorInclusionDrafts::class)->attribute($attributeId);
    $values = array_map(fn (array $value): array => ['ref' => $value['id'], 'option_id' => $value['option_id']], $draft['options']);

    return ['ref' => $draft['id'], 'attribute_id' => $attributeId, 'values' => $values, 'default_ref' => $values[0]['ref'] ?? null];
}

function creationMaster(array $overrides = []): array
{
    return [...['ref' => creationRef(), 'label' => 'Shared blue', 'semantic_id' => 'shared_blue', 'tags' => [' blue '], 'description' => 'Specification'], ...$overrides];
}

test('V14 actual Add Attributes includes existing only with empty creation branches', function () {
    $owner = Configurator::factory()->create();
    $option = Option::factory()->create(['code' => 'Q1']);
    $review = creationReview($option->attribute_id);
    $manager = Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $owner, 'pageClass' => EditConfigurator::class])
        ->mountAction(TestAction::make('includeMany')->table());
    $manager->set('mountedActions.0.data.existing_attribute_ids', [$option->attribute_id]);
    $rows = $manager->get('mountedActions.0.data.reviews');
    expect($rows)->toHaveCount(1);
    $key = array_key_first($rows);
    expect(array_values($rows[$key]['values'])[0]['_code'] ?? null)->toBe('Q1');
    $manager->set("mountedActions.0.data.reviews.$key.default_ref", array_values($rows[$key]['values'])[0]['ref'])
        ->callMountedAction()->assertHasNoFormErrors()->assertNotified();
    $inclusion = $owner->attributes()->sole();
    expect($inclusion->attribute_id)->toBe($option->attribute_id)->and($inclusion->defaultOption->option_id)->toBe($option->id);
});

test('V14 operations reject empty or pool only additions without creating Masters', function (string $operation) {
    $owner = Configurator::factory()->create();
    $payload = ['master_values' => [creationMaster()]];
    if ($operation === 'values') {
        $option = Option::factory()->create();
        app(AddConfiguratorAttributes::class)->handle(auth()->user(), $owner, ['existing_attribute_ids' => [$option->attribute_id], 'reviews' => [creationReview($option->attribute_id)]]);
        $inclusion = $owner->attributes()->sole();
        expect(fn () => app(AddConfiguratorValues::class)->handle(auth()->user(), $owner, $inclusion->id, $payload))->toThrow(ValidationException::class);
    } else {
        expect(fn () => app(AddConfiguratorAttributes::class)->handle(auth()->user(), $owner, $payload))->toThrow(ValidationException::class);
    }
    expect(Value::where('semantic_id', 'shared_blue')->exists())->toBeFalse();
})->with(['attributes', 'values']);

test('V15 mixed shared creation reuses a Master once and persists exact local defaults atomically', function () {
    $owner = Configurator::factory()->create();
    $existing = Option::factory()->create(['code' => 'E1']);
    $master = creationMaster();
    $attributes = [];
    $reviews = [creationReview($existing->attribute_id)];
    foreach (['First' => 'Aa', 'Second' => 'aA'] as $name => $code) {
        $association = ['ref' => creationRef(), 'value_ref' => $master['ref'], 'code' => $code];
        $attribute = ['ref' => creationRef(), 'label' => $name, 'key' => strtolower($name), 'values' => [$association]];
        $localRef = creationRef();
        $attributes[] = $attribute;
        $reviews[] = ['ref' => creationRef(), 'attribute_ref' => $attribute['ref'], 'values' => [['ref' => $localRef, 'option_ref' => $association['ref']]], 'default_ref' => $localRef];
    }
    app(AddConfiguratorAttributes::class)->handle(auth()->user(), $owner, ['master_values' => [$master], 'attributes' => $attributes, 'existing_attribute_ids' => [$existing->attribute_id], 'reviews' => $reviews]);

    expect($owner->attributes()->count())->toBe(3)->and(Value::where('semantic_id', 'shared_blue')->count())->toBe(1);
    foreach ($owner->attributes()->with('defaultOption.option')->get() as $inclusion) {
        expect($inclusion->defaultOption->configurator_attribute_id)->toBe($inclusion->id);
    }
    expect(Option::whereIn('code', ['Aa', 'aA'])->pluck('value_id')->unique()->count())->toBe(1);
    expect(Value::where('semantic_id', 'shared_blue')->sole()->tags)->toBe(['blue']);
});

test('V15 missing foreign and duplicate review references fail with zero local or canonical writes', function (string $failure) {
    $owner = Configurator::factory()->create();
    $option = Option::factory()->create(['code' => 'E1']);
    $review = creationReview($option->attribute_id);
    if ($failure === 'missing default') {
        unset($review['default_ref']);
    }
    if ($failure === 'foreign default') {
        $review['default_ref'] = creationRef();
    }
    if ($failure === 'foreign association') {
        unset($review['values'][0]['option_id']);
        $review['values'][0]['option_ref'] = creationRef();
    }
    if ($failure === 'duplicate local ref') {
        $review['values'][] = $review['values'][0];
    }
    $reviews = $failure === 'duplicate review' ? [$review, $review] : [$review];
    expect(fn () => app(AddConfiguratorAttributes::class)->handle(auth()->user(), $owner, ['existing_attribute_ids' => [$option->attribute_id], 'reviews' => $reviews]))->toThrow(ValidationException::class);
    expect($owner->attributes()->count())->toBe(0);
})->with(['missing default', 'foreign default', 'foreign association', 'duplicate local ref', 'duplicate review']);

test('V16 operations authorize before validation and scope the inclusion to its separate owner', function () {
    [$owner, $draft] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $owner, $draft);
    $inclusion = $owner->attributes()->first();
    $foreign = Configurator::factory()->create();
    expect(fn () => app(AddConfiguratorValues::class)->handle(auth()->user(), $foreign, $inclusion->id, []))->toThrow(ModelNotFoundException::class);
    Gate::define('manage-catalog', fn () => false);
    expect(fn () => app(AddConfiguratorAttributes::class)->handle(auth()->user(), $owner, ['forged' => true]))->toThrow(AuthorizationException::class);
    expect(fn () => app(AddConfiguratorValues::class)->handle(auth()->user(), $owner, $inclusion->id, ['forged' => true]))->toThrow(AuthorizationException::class);
});

test('V17 Add Values mixes existing and new shared associations while preserving the full saved graph', function () {
    [$owner, $draft] = canonicalDefinitionFixture();
    $draft['rules'] = [fixtureMapping()];
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $owner, $draft);
    $inclusion = $owner->attributes()->first();
    $before = app(ConfiguratorDefinitionLoader::class)->draft($owner);
    $existing = Option::factory()->create(['attribute_id' => $inclusion->attribute_id, 'code' => 'Z1']);
    $master = creationMaster();
    app(AddConfiguratorValues::class)->handle(auth()->user(), $owner, $inclusion->id, ['existing_option_ids' => [$existing->id], 'new_values' => [['ref' => creationRef(), 'value_ref' => $master['ref'], 'code' => 'Z2']], 'master_values' => [$master]]);
    $after = app(ConfiguratorDefinitionLoader::class)->draft($owner->fresh());
    expect(array_slice($after['attributes'][0]['options'], 0, 2))->toBe($before['attributes'][0]['options'])
        ->and($after['attributes'][0]['default_configurator_option_id'])->toBe($before['attributes'][0]['default_configurator_option_id'])
        ->and($after['rules'])->toBe($before['rules'])->and(array_slice($after['attributes'], 1))->toBe(array_slice($before['attributes'], 1));
    expect($inclusion->options()->count())->toBe(4);
});

test('V17 actual Add Values remains useful when all current shared Values are included', function () {
    [$owner, $draft] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $owner, $draft);
    $inclusion = $owner->attributes()->first();
    $master = creationMaster();
    Livewire::test(OptionsRelationManager::class, ['ownerRecord' => $inclusion, 'pageClass' => EditConfigurator::class])
        ->mountAction(TestAction::make('includeMany')->table())
        ->set('mountedActions.0.data.master_values', [(string) Str::uuid() => $master])
        ->set('mountedActions.0.data.new_values', [(string) Str::uuid() => ['ref' => creationRef(), 'value_ref' => $master['ref'], 'code' => 'Z3', 'name' => 'Shared blue']])
        ->callMountedAction()->assertHasNoFormErrors()->assertNotified();
    expect($inclusion->options()->count())->toBe(3)->and($inclusion->fresh()->default_configurator_option_id)->toBe($inclusion->default_configurator_option_id);
});

test('V12 V15 mounted creation stages one Master for two Attributes and saves their chosen table defaults', function () {
    $owner = Configurator::factory()->create();
    $manager = Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $owner, 'pageClass' => EditConfigurator::class])->mountAction(TestAction::make('includeMany')->table());
    $manager->set('catalogCreationTab', 'create');
    $a = array_key_first($manager->get('mountedActions.0.data.attributes'));
    $b = (string) Str::uuid();
    $manager->set("mountedActions.0.data.attributes.$a.label", 'First')->set("mountedActions.0.data.attributes.$a.key", 'first')
        ->set("mountedActions.0.data.attributes.$b", ['ref' => creationRef(), 'label' => 'Second', 'key' => '', 'values' => []])
        ->callAction(TestAction::make('openValues')->schemaComponent("create.attributes.$a.attribute-values", 'mountedActionSchema0'));
    $composerKey = (string) Str::uuid();
    $master = creationMaster();
    $manager->set("mountedActions.0.data.attributes.$a._master_composer", [$composerKey => $master])
        ->callAction(TestAction::make('createAndUse')->schemaComponent("create.attributes.$a.attribute-values", 'mountedActionSchema0'))->assertHasNoErrors();
    expect(Value::count())->toBe(0)->and(Attribute::count())->toBe(0);
    $manager->callAction(TestAction::make('openValues')->schemaComponent("create.attributes.$b.attribute-values", 'mountedActionSchema0'))
        ->set("mountedActions.0.data.attributes.$b._selected_pending", [$master['ref']])
        ->callAction(TestAction::make('useSelected')->schemaComponent("create.attributes.$b.attribute-values", 'mountedActionSchema0'))->assertHasNoErrors();
    $first = array_key_first($manager->get("mountedActions.0.data.attributes.$a.values"));
    $second = array_key_first($manager->get("mountedActions.0.data.attributes.$b.values"));
    $manager->set("mountedActions.0.data.attributes.$a.values.$first.code", 'Aa')->set("mountedActions.0.data.attributes.$b.values.$second.code", 'aA')
        ->callAction(TestAction::make('setDefault')->schemaComponent("create.attributes.$a.attribute-values.values.$first.staged-default", 'mountedActionSchema0'))
        ->callAction(TestAction::make('setDefault')->schemaComponent("create.attributes.$b.attribute-values.values.$second.staged-default", 'mountedActionSchema0'))
        ->set("mountedActions.0.data.attributes.$b.key", 'second')->callMountedAction()->assertHasNoFormErrors()->assertNotified();
    expect($owner->attributes()->count())->toBe(2)->and(Value::where('semantic_id', 'shared_blue')->count())->toBe(1)
        ->and($owner->attributes()->with('defaultOption.option')->get()->map(fn ($row) => $row->defaultOption->option->code)->all())->toBe(['Aa', 'aA']);
});

test('V19 empty cancel closes and dirty retained review confirms while Keep editing preserves it', function () {
    $owner = Configurator::factory()->create();
    $option = Option::factory()->create();
    $manager = Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $owner, 'pageClass' => EditConfigurator::class])->mountAction(TestAction::make('includeMany')->table());
    $manager->callAction(TestAction::make('discardDraft')->table());
    expect($manager->get('mountedActions'))->toBe([]);
    $manager->mountAction(TestAction::make('includeMany')->table())->set('mountedActions.0.data.existing_attribute_ids', [$option->attribute_id]);
    $reviews = $manager->get('mountedActions.0.data.reviews');
    $key = array_key_first($reviews);
    $valueKey = array_key_first($reviews[$key]['values']);
    $manager->set("mountedActions.0.data.reviews.$key.values.$valueKey.local_fields.hint", 'Keep this hint')->set('mountedActions.0.data.existing_attribute_ids', [])
        ->mountAction(TestAction::make('discardDraft')->table());
    expect($manager->get('mountedActions'))->toHaveCount(2);
    $manager->call('unmountAction', false)->set('mountedActions.0.data.existing_attribute_ids', [$option->attribute_id])
        ->assertSet("mountedActions.0.data.reviews.$key.values.$valueKey.local_fields.hint", 'Keep this hint');
    $manager->mountAction(TestAction::make('discardDraft')->table())->callMountedAction();
    expect($manager->get('mountedActions'))->toBe([]);
    $manager->mountAction(TestAction::make('includeMany')->table())->assertSet('mountedActions.0.data.reviews', [])->assertSet('mountedActions.0.data._review_stash', []);
});

test('V21 native staged default removal clears intent and requires another choice', function () {
    $owner = Configurator::factory()->create();
    $first = Option::factory()->create(['code' => 'Q1']);
    Option::factory()->create(['attribute_id' => $first->attribute_id, 'code' => 'Q2']);
    $manager = Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $owner, 'pageClass' => EditConfigurator::class])->mountAction(TestAction::make('includeMany')->table())
        ->set('mountedActions.0.data.existing_attribute_ids', [$first->attribute_id]);
    $reviews = $manager->get('mountedActions.0.data.reviews');
    $key = array_key_first($reviews);
    $valueKey = array_key_first($reviews[$key]['values']);
    $manager->callAction(TestAction::make('setDefault')->schemaComponent("reviews.$key.values.$valueKey.review-default", 'mountedActionSchema0'))
        ->assertSet("mountedActions.0.data.reviews.$key.default_ref", $reviews[$key]['values'][$valueKey]['ref'])
        ->callAction(TestAction::make('delete')->schemaComponent("reviews.$key.values", 'mountedActionSchema0')->arguments(['item' => $valueKey]))
        ->assertSet("mountedActions.0.data.reviews.$key.default_ref", null)
        ->callMountedAction()->assertHasErrors(["mountedActions.0.data.reviews.$key.default_ref"]);
    expect($owner->attributes()->count())->toBe(0);
});

test('V15 V06 a late Code conflict preserves original blank pool and Attribute error positions with total rollback', function () {
    $owner = Configurator::factory()->create();
    Option::factory()->create(['code' => 'X2']);
    $master = creationMaster();
    $attributes = [['ref' => creationRef(), 'label' => '', 'key' => '', 'values' => []]];
    $reviews = [];
    foreach (['X1', 'X2'] as $index => $code) {
        $association = ['ref' => creationRef(), 'value_ref' => $master['ref'], 'code' => $code];
        $attribute = ['ref' => creationRef(), 'label' => 'New '.$index, 'key' => 'new_'.$index, 'values' => [$association]];
        $local = creationRef();
        $attributes[] = $attribute;
        $reviews[] = ['ref' => creationRef(), 'attribute_ref' => $attribute['ref'], 'values' => [['ref' => $local, 'option_ref' => $association['ref']]], 'default_ref' => $local];
    }
    try {
        app(AddConfiguratorAttributes::class)->handle(auth()->user(), $owner, ['attributes' => $attributes, 'master_values' => [['ref' => creationRef(), 'label' => '', 'semantic_id' => ''], $master], 'reviews' => $reviews]);
        test()->fail('Expected late global Code conflict.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('attributes.2.values.0.code');
    }
    expect(Value::where('semantic_id', 'shared_blue')->exists())->toBeFalse()->and(Attribute::whereIn('key', ['new_0', 'new_1'])->count())->toBe(0)->and($owner->attributes()->count())->toBe(0);
});

test('V16 stale records are rechecked and an inactive included Attribute can receive eligible new Values', function (string $status) {
    [$owner, $draft] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $owner, $draft);
    $inclusion = $owner->attributes()->first();
    $option = Option::factory()->create(['attribute_id' => $inclusion->attribute_id, 'code' => 'X1']);
    $option->update([$status => $status === 'is_hidden']);
    expect(fn () => app(AddConfiguratorValues::class)->handle(auth()->user(), $owner, $inclusion->id, ['existing_option_ids' => [$option->id]]))->toThrow(ValidationException::class);
    $option->update(['is_active' => true, 'is_hidden' => false]);
    $inclusion->attribute->update(['is_active' => false]);
    app(AddConfiguratorValues::class)->handle(auth()->user(), $owner, $inclusion->id, ['existing_option_ids' => [$option->id]]);
    expect($inclusion->options()->where('option_id', $option->id)->exists())->toBeTrue();
})->with(['is_active', 'is_hidden']);

test('C08 context is acquired before a patch callback can make nested canonical writes', function () {
    $owner = Configurator::factory()->create();
    $events = [];
    DB::listen(function ($query) use (&$events): void {
        if (str_contains($query->sql, 'catalog_context_settings')) {
            $events[] = 'context';
        }
        if (str_starts_with($query->sql, 'insert into "values"')) {
            $events[] = 'master';
        }
    });
    app(SaveConfiguratorDefinition::class)->change(auth()->user(), $owner, function (array $draft) use (&$events): array {
        $events[] = 'callback';
        app(CreateMasterValues::class)->handle(auth()->user(), [creationMaster()]);

        return $draft;
    });
    expect(array_search('context', $events, true))->toBeLessThan(array_search('callback', $events, true));
    expect(array_search('callback', $events, true))->toBeLessThan(array_search('master', $events, true));
});

test('V12 native source tabs mount only the active picker and retain scalar selections', function () {
    $owner = Configurator::factory()->create();
    $value = Value::factory()->create();
    $manager = Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $owner, 'pageClass' => EditConfigurator::class])->mountAction(TestAction::make('includeMany')->table())
        ->assertSet('catalogCreationTab', 'existing');
    expect(creationRenderedPickers($manager->instance()->getSchema('mountedActionSchema0')->toHtml()))->toBe(1);
    $key = array_key_first($manager->get('mountedActions.0.data.attributes'));
    $manager->set('catalogCreationTab', 'create')->callAction(TestAction::make('openValues')->schemaComponent("create.attributes.$key.attribute-values", 'mountedActionSchema0'))
        ->set("mountedActions.0.data.attributes.$key._selected_value_ids", [$value->id]);
    expect(creationRenderedPickers($manager->instance()->getSchema('mountedActionSchema0')->toHtml()))->toBe(1);
    $manager->set('catalogCreationTab', 'existing')->set('catalogCreationTab', 'create')
        ->assertSet("mountedActions.0.data.attributes.$key._selected_value_ids", [$value->id]);
    expect(creationRenderedPickers($manager->instance()->getSchema('mountedActionSchema0')->toHtml()))->toBe(1);
});

test('C06 direct creation boundaries reject unknown incomplete and non-scalar supplied shapes', function (Closure $mutate) {
    $owner = Configurator::factory()->create();
    $option = Option::factory()->create(['code' => 'T1']);
    $payload = ['existing_attribute_ids' => [$option->attribute_id], 'reviews' => [creationReview($option->attribute_id)]];
    $mutate($payload);
    expect(fn () => app(AddConfiguratorAttributes::class)->handle(auth()->user(), $owner, $payload))->toThrow(ValidationException::class);
    expect($owner->attributes()->count())->toBe(0);
})->with([
    'unknown root' => [function (array &$payload): void {
        $payload['draft'] = [];
    }],
    'unknown review' => [function (array &$payload): void {
        $payload['reviews'][0]['order'] = 99;
    }],
    'incomplete settings' => [function (array &$payload): void {
        $payload['reviews'][0]['settings'] = ['input_type' => 'toggle'];
    }],
    'unknown settings' => [function (array &$payload): void {
        $payload['reviews'][0]['settings'] = ['input_type' => 'toggle', 'label_override' => null, 'help_text' => null, 'is_active' => true];
    }],
    'incomplete local fields' => [function (array &$payload): void {
        $payload['reviews'][0]['values'][0]['local_fields'] = ['hint' => 'Only hint'];
    }],
    'nonboolean local flags' => [function (array &$payload): void {
        $payload['reviews'][0]['values'][0]['local_fields'] = ['label_override' => null, 'subtitle_mode' => 'none', 'display_value_override' => null, 'hint' => null, 'hidden_by_default' => 1, 'disabled_by_default' => false];
    }],
    'explicit Custom without text' => [function (array &$payload): void {
        $payload['reviews'][0]['values'][0]['local_fields'] = ['label_override' => null, 'subtitle_mode' => 'custom', 'display_value_override' => null, 'hint' => null, 'hidden_by_default' => false, 'disabled_by_default' => false];
    }],
    'boolean ID' => [function (array &$payload): void {
        $payload['existing_attribute_ids'] = [true];
    }],
    'invalid unused Master shape' => [function (array &$payload): void {
        $payload['master_values'] = [creationMaster(['_suggested_semantic_id' => true])];
    }],
    'raw row maximum' => [function (array &$payload): void {
        $payload['master_values'] = array_fill(0, 501, ['ref' => creationRef()]);
    }],
]);

test('V16 real mounted addition submission refuses a revoked manager before validation', function (string $kind) {
    [$owner, $draft] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $owner, $draft);
    $record = $kind === 'values' ? $owner->attributes()->first() : $owner;
    $manager = Livewire::test($kind === 'values' ? OptionsRelationManager::class : AttributesRelationManager::class, ['ownerRecord' => $record, 'pageClass' => EditConfigurator::class])->mountAction(TestAction::make('includeMany')->table());
    Gate::define('manage-catalog', fn () => false);
    $manager->callMountedAction()->assertForbidden();
    expect($owner->attributes()->count())->toBe(3)->and(Value::count())->toBe(6);
})->with(['attributes', 'values']);

test('V05 actual Add Values composer retains creation-only uniqueness under the outer edit model', function () {
    [$owner, $draft] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $owner, $draft);
    $inclusion = $owner->attributes()->first();
    $master = $inclusion->options()->first()->option->value;
    $manager = Livewire::test(OptionsRelationManager::class, ['ownerRecord' => $inclusion, 'pageClass' => EditConfigurator::class])->mountAction(TestAction::make('includeMany')->table())
        ->set('catalogCreationTab', 'create')->callAction(TestAction::make('openValues')->schemaComponent('create.attribute-values', 'mountedActionSchema0'));
    $key = (string) Str::uuid();
    $row = creationMaster(['semantic_id' => $master->semantic_id, '_suggested_semantic_id' => false]);
    $manager->set('mountedActions.0.data._master_composer', [$key => $row])->callAction(TestAction::make('createAndUse')->schemaComponent('create.attribute-values', 'mountedActionSchema0'))
        ->assertHasErrors(["mountedActions.0.data._master_composer.$key.semantic_id"])->assertSet("mountedActions.0.data._master_composer.$key", $row);
    expect(Value::count())->toBe(6);
});

test('V06 mounted writer errors preserve original blank-pool UUIDs and manual helper state', function () {
    $owner = Configurator::factory()->create();
    $persisted = Value::factory()->create(['semantic_id' => 'taken']);
    $manager = Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $owner, 'pageClass' => EditConfigurator::class])->mountAction(TestAction::make('includeMany')->table());
    $a = array_key_first($manager->get('mountedActions.0.data.attributes'));
    $blankKey = (string) Str::uuid();
    $masterKey = (string) Str::uuid();
    $master = creationMaster(['semantic_id' => $persisted->semantic_id]);
    $associationKey = (string) Str::uuid();
    $row = ['ref' => creationRef(), 'label' => 'Material', 'key' => 'material', '_suggested_key' => true, 'values' => [$associationKey => ['ref' => creationRef(), 'value_ref' => $master['ref'], 'code' => 'T1', 'name' => 'Exact helper']]];
    $manager->set('mountedActions.0.data.master_values', [$blankKey => ['ref' => creationRef(), 'label' => '', 'semantic_id' => '', 'tags' => [], 'description' => null], $masterKey => $master])
        ->set("mountedActions.0.data.attributes.$a", $row);
    $reviews = $manager->get('mountedActions.0.data.reviews');
    $key = array_key_first($reviews);
    $local = array_values($reviews[$key]['values'])[0]['ref'];
    $manager->set("mountedActions.0.data.reviews.$key.default_ref", $local)->callMountedAction()->assertHasErrors(["mountedActions.0.data.master_values.$masterKey.semantic_id"])
        ->assertSet("mountedActions.0.data.attributes.$a", $row)->assertSet("mountedActions.0.data.reviews.$key.default_ref", $local);
    expect($owner->attributes()->count())->toBe(0)->and(Attribute::count())->toBe(0);
});

test('V17 addition refresh retains active and stashed Attribute and Value editor drafts', function () {
    [$owner, $draft] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $owner, $draft);
    $included = $owner->attributes()->orderBy('display_order')->get();
    $firstValue = $included[0]->options()->first();
    $secondValue = $included[1]->options()->first();
    $new = Option::factory()->create(['code' => 'Z9']);
    $manager = Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $owner, 'pageClass' => EditConfigurator::class])
        ->call('selectAttribute', $included[0]->id)->fillForm(['help_text' => 'Keep first parent'], 'editorForm')->call('selectOption', $included[0]->id, $firstValue->id)->fillForm(['hint' => 'Keep first Value'], 'optionEditorForm')
        ->call('selectAttribute', $included[1]->id)->fillForm(['help_text' => 'Keep second parent'], 'editorForm')->call('selectOption', $included[1]->id, $secondValue->id)->fillForm(['hint' => 'Keep second Value'], 'optionEditorForm');
    $review = app(ConfiguratorInclusionDrafts::class)->attribute($new->attribute_id);
    $review['default_configurator_option_id'] = $review['options'][0]['id'];
    $manager->call('includeAttributes', [$new->attribute_id], [$review])->assertHasNoErrors()->assertSet('editorData.help_text', 'Keep second parent')->assertSet('optionEditorData.hint', 'Keep second Value')
        ->call('selectAttribute', $included[0]->id)->assertSet('editorData.help_text', 'Keep first parent')->assertSet('optionEditorData.hint', 'Keep first Value');
    expect($included[0]->fresh()->help_text)->toBeNull()->and($firstValue->fresh()->hint)->toBeNull();
});

test('V21 removing a new Attribute review Value preserves that exclusion through failed submit and saves a remaining explicit default', function () {
    $owner = Configurator::factory()->create();
    $first = Value::factory()->create();
    $second = Value::factory()->create();
    $manager = Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $owner, 'pageClass' => EditConfigurator::class])->mountAction(TestAction::make('includeMany')->table());
    $key = array_key_first($manager->get('mountedActions.0.data.attributes'));
    $manager->set("mountedActions.0.data.attributes.$key", ['ref' => creationRef(), 'label' => 'New', 'key' => 'new_attr', 'values' => [
        (string) Str::uuid() => ['ref' => creationRef(), 'value_id' => $first->id, 'code' => 'R1'],
        (string) Str::uuid() => ['ref' => creationRef(), 'value_id' => $second->id, 'code' => 'R2'],
    ]]);
    $reviews = $manager->get('mountedActions.0.data.reviews');
    $reviewKey = array_key_first($reviews);
    [$a, $b] = array_keys($reviews[$reviewKey]['values']);
    $manager->callAction(TestAction::make('setDefault')->schemaComponent("reviews.$reviewKey.values.$a.review-default", 'mountedActionSchema0'))
        ->callAction(TestAction::make('delete')->schemaComponent("reviews.$reviewKey.values", 'mountedActionSchema0')->arguments(['item' => $a]))
        ->callMountedAction()->assertHasErrors(["mountedActions.0.data.reviews.$reviewKey.default_ref"]);
    expect($manager->get("mountedActions.0.data.reviews.$reviewKey.values"))->toHaveCount(1);
    $manager->callAction(TestAction::make('setDefault')->schemaComponent("reviews.$reviewKey.values.$b.review-default", 'mountedActionSchema0'))
        ->callMountedAction()->assertHasNoFormErrors();
    $included = $owner->attributes()->sole();
    expect($included->options()->count())->toBe(1)->and($included->defaultOption->option->code)->toBe('R2')->and($included->attribute->options()->count())->toBe(2);
});

test('V05 mounted Add Attributes rejects duplicate new canonical Keys and Codes under a coincident outer record', function (string $field) {
    $coincidentId = max(Configurator::max('id') ?? 0, Attribute::max('id') ?? 0) + 1;
    $owner = Configurator::factory()->create(['id' => $coincidentId]);
    $persisted = Option::factory()->for(Attribute::factory()->create(['id' => $coincidentId]))->create(['code' => 'D1']);
    expect($owner->id)->toBe($persisted->attribute_id);
    $master = creationMaster();
    $manager = Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $owner, 'pageClass' => EditConfigurator::class])->mountAction(TestAction::make('includeMany')->table())->set('catalogCreationTab', 'create');
    $key = array_key_first($manager->get('mountedActions.0.data.attributes'));
    $association = (string) Str::uuid();
    $row = ['ref' => creationRef(), 'label' => 'New definition', 'key' => $field === 'key' ? $persisted->attribute->key : 'new_definition', '_suggested_key' => false,
        'values' => [$association => ['ref' => creationRef(), 'value_ref' => $master['ref'], 'code' => $field === 'code' ? 'D1' : 'D2']]];
    $manager->set('mountedActions.0.data.master_values', [(string) Str::uuid() => $master])->set("mountedActions.0.data.attributes.$key", $row);
    $reviews = $manager->get('mountedActions.0.data.reviews');
    $reviewKey = array_key_first($reviews);
    $expected = $field === 'key' ? "attributes.$key.key" : "attributes.$key.values.$association.code";
    $manager->set("mountedActions.0.data.reviews.$reviewKey.default_ref", array_values($reviews[$reviewKey]['values'])[0]['ref'])->callMountedAction()
        ->assertHasErrors(['mountedActions.0.data.'.$expected])->assertSet("mountedActions.0.data.attributes.$key", $row);
    expect(Attribute::count())->toBe(1)->and(Value::count())->toBe(1)->and(Option::count())->toBe(1)->and($owner->attributes()->count())->toBe(0);
})->with(['key', 'code']);

test('V05 mounted Add Attributes new Master composer never ignores the coincident Configurator record', function () {
    $persisted = Value::factory()->create(['semantic_id' => 'taken']);
    $owner = Configurator::factory()->create(['id' => $persisted->id]);
    expect($owner->id)->toBe($persisted->id);
    $manager = Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $owner, 'pageClass' => EditConfigurator::class])->mountAction(TestAction::make('includeMany')->table())->set('catalogCreationTab', 'create');
    $key = array_key_first($manager->get('mountedActions.0.data.attributes'));
    $manager->set("mountedActions.0.data.attributes.$key.label", 'New definition')->set("mountedActions.0.data.attributes.$key.key", 'new_definition')
        ->callAction(TestAction::make('openValues')->schemaComponent("create.attributes.$key.attribute-values", 'mountedActionSchema0'));
    $composerKey = (string) Str::uuid();
    $row = creationMaster(['semantic_id' => 'taken', '_suggested_semantic_id' => false]);
    $manager->set("mountedActions.0.data.attributes.$key._master_composer", [$composerKey => $row])
        ->callAction(TestAction::make('createAndUse')->schemaComponent("create.attributes.$key.attribute-values", 'mountedActionSchema0'))
        ->assertHasErrors(["mountedActions.0.data.attributes.$key._master_composer.$composerKey.semantic_id"])
        ->assertSet("mountedActions.0.data.attributes.$key._master_composer.$composerKey", $row);
    expect(Value::count())->toBe(1)->and(Attribute::count())->toBe(0)->and(Option::count())->toBe(0);
});

test('T4-R1 legacy inclusion infers subtitle intent from original omitted mode without changing custom text', function (?string $text, string $mode, ?string $savedText) {
    $owner = Configurator::factory()->create();
    $option = Option::factory()->create(['code' => 'L1']);
    $row = app(ConfiguratorInclusionDrafts::class)->attribute($option->attribute_id);
    unset($row['options'][0]['subtitle_mode']);
    $row['options'][0]['display_value_override'] = $text;
    $row['default_configurator_option_id'] = $row['options'][0]['id'];
    Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $owner, 'pageClass' => EditConfigurator::class])->call('includeAttributes', [$option->attribute_id], [$row])->assertHasNoErrors();
    $saved = $owner->attributes()->sole()->options()->sole();
    expect($saved->subtitle_mode)->toBe($mode)->and($saved->display_value_override)->toBe($savedText);
})->with([
    'verbatim custom' => ['  Custom subtitle  ', 'custom', '  Custom subtitle  '],
    'zero is nonblank' => ['0', 'custom', '0'],
    'null' => [null, 'none', null],
    'empty' => ['', 'none', null],
    'whitespace' => ['   ', 'none', null],
]);

test('T4-R1 invalid original explicit subtitle intent stays at the owning mounted review field', function (mixed $mode, string $field) {
    $owner = Configurator::factory()->create();
    $option = Option::factory()->create(['code' => 'L1']);
    $manager = Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $owner, 'pageClass' => EditConfigurator::class])->mountAction(TestAction::make('includeMany')->table())
        ->set('mountedActions.0.data.existing_attribute_ids', [$option->attribute_id]);
    $reviews = $manager->get('mountedActions.0.data.reviews');
    $key = array_key_first($reviews);
    $valueKey = array_key_first($reviews[$key]['values']);
    $row = app(ConfiguratorInclusionDrafts::class)->attribute($option->attribute_id);
    $row['default_configurator_option_id'] = $row['options'][0]['id'];
    $row['options'][0]['subtitle_mode'] = $mode;
    $row['options'][0]['display_value_override'] = $mode === 'custom' ? null : 'Original text';
    $manager->call('includeAttributes', [$option->attribute_id], [$row])->assertHasErrors(["mountedActions.0.data.reviews.$key.values.$valueKey.local_fields.$field"])
        ->assertSet('mountedActions.0.data.reviews', $reviews);
    expect($owner->attributes()->count())->toBe(0);
})->with([
    'null mode' => [null, 'subtitle_mode'],
    'empty mode' => ['', 'subtitle_mode'],
    'unknown mode' => ['forged', 'subtitle_mode'],
    'Custom needs original text' => ['custom', 'display_value_override'],
]);

test('T4-R3 mounted review headings identify asymmetric existing and new Attributes from raw native item state', function () {
    $owner = Configurator::factory()->create();
    $attribute = Attribute::factory()->create(['label' => 'Existing Steel', 'key' => 'existing_steel']);
    Option::factory()->create(['attribute_id' => $attribute->id, 'code' => 'S1']);
    $manager = Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $owner, 'pageClass' => EditConfigurator::class])->mountAction(TestAction::make('includeMany')->table())
        ->set('mountedActions.0.data.existing_attribute_ids', [$attribute->id]);
    $key = array_key_first($manager->get('mountedActions.0.data.attributes'));
    $manager->set("mountedActions.0.data.attributes.$key.label", 'New Finish')->set("mountedActions.0.data.attributes.$key.key", 'new_finish');
    $field = $manager->instance()->getSchema('mountedActionSchema0')->getComponentByStatePath('reviews', withHidden: true);
    $document = new DOMDocument;
    $document->loadHTML($field->toHtml(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $headings = (new DOMXPath($document))->query('//*[contains(concat(" ", normalize-space(@class), " "), " fi-fo-repeater-item-header-label ")]');
    expect(array_map(fn (DOMNode $heading): string => trim($heading->textContent), iterator_to_array($headings)))->toBe(['Existing Steel', 'New Finish']);
    expect($owner->attributes()->count())->toBe(0);
});
