<?php

use App\Actions\AssignConfiguratorGroups;
use App\Actions\DeleteConfigurator;
use App\Actions\SaveConfiguratorDefinition;
use App\Actions\SaveConfiguratorSettings;
use App\Filament\Resources\Configurators\ConfiguratorResource;
use App\Filament\Resources\Configurators\Pages\CreateConfigurator;
use App\Filament\Resources\Configurators\Pages\EditConfigurator;
use App\Filament\Resources\Configurators\RelationManagers\AttributesRelationManager;
use App\Livewire\Catalog\ConfiguratorGroups;
use App\Livewire\Catalog\ConfiguratorOverview;
use App\Livewire\Catalog\ConfiguratorPreview;
use App\Models\Configurator;
use App\Models\ConfiguratorAttribute;
use App\Models\ConfiguratorRule;
use App\Models\Group;
use App\Models\Option;
use App\Models\User;
use App\Services\ConfiguratorDefinitionLoader;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $this->actingAs($this->actor);
});

test('Configurator assignment accepts an explicit leaf set and cannot silently steal or assign a branch', function () {
    $configurator = Configurator::factory()->create();
    $other = Configurator::factory()->create();
    $leaf = Group::factory()->create();
    $foreign = Group::factory()->create(['configurator_id' => $other->id]);
    $parent = Group::factory()->create();
    Group::factory()->create(['parent_id' => $parent->id]);
    $action = app(AssignConfiguratorGroups::class);
    $action->handle($this->actor, $configurator, [$leaf->id]);
    expect($leaf->fresh()->configurator_id)->toBe($configurator->id);
    expect(fn () => $action->handle($this->actor, $configurator, [$foreign->id]))->toThrow(ValidationException::class);
    expect(fn () => $action->handle($this->actor, $configurator, [$parent->id]))->toThrow(ValidationException::class);
    expect(fn () => $action->handle($this->actor, $configurator, [$leaf->id, $leaf->id]))->toThrow(ValidationException::class);
    expect($leaf->fresh()->configurator_id)->toBe($configurator->id)->and($foreign->fresh()->configurator_id)->toBe($other->id);
    $action->handle($this->actor, $configurator, []);
    expect($leaf->fresh()->configurator_id)->toBeNull();
});

test('assignment mutation rejects an unauthorized direct caller', function () {
    expect(fn () => app(AssignConfiguratorGroups::class)->handle(User::factory()->create(), Configurator::factory()->create(), []))->toThrow(AuthorizationException::class);
});

require_once dirname(__DIR__, 2).'/ConfiguratorFixtures.php';

test('Overview save preserves unvisited local inclusions and rules and the workspace has five focused tabs', function () {
    [$configurator, $data] = canonicalDefinitionFixture();
    $data['rules'] = [fixtureMapping()];
    app(SaveConfiguratorDefinition::class)->handle($this->actor, $configurator, $data);
    $before = app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh());
    $page = Livewire\Livewire::test(EditConfigurator::class, ['record' => $configurator->id])
        ->assertSee('Overview')->assertSee('Groups')->assertSee('Attributes')->assertSee('Rules')->assertSee('Preview & Test')->assertDontSeeText('Reserved for later custom behavior.');
    Livewire\Livewire::test(ConfiguratorOverview::class, ['configuratorId' => $configurator->id])
        ->fillForm(['name' => 'Renamed', 'description' => 'Shared details', 'context_schema' => ['territory' => [], 'application' => []]])->call('saveOverview')->assertHasNoFormErrors();
    $after = app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh());
    expect($after['name'])->toBe('Renamed')->and($after['attributes'])->toBe($before['attributes'])->and($after['rules'])->toBe($before['rules']);
    $tabs = $page->instance()->form->getComponent('configurator-editor');
    expect(array_map(fn ($tab) => $tab->getLabel(), $tabs->getChildSchema()->getComponents()))->toBe(['Overview', 'Groups', 'Attributes', 'Rules', 'Preview & Test']);
    $page->assertActionDoesNotExist('settings')->assertActionDoesNotExist('assignGroups')->assertActionDoesNotExist('viewGroups');
    $this->get(ConfiguratorResource::getUrl('edit', ['record' => $configurator->id]))
        ->assertDontSeeLivewire(ConfiguratorPreview::class)
        ->assertSee('Preview and testing tools will be added here later.');
});

test('new Configurator creation is unassigned and does not fabricate canonical content', function () {
    Livewire\Livewire::test(CreateConfigurator::class)
        ->fillForm(['name' => 'Unassigned definition', 'description' => null])->call('create')->assertHasNoFormErrors();
    $record = Configurator::where('name', 'Unassigned definition')->sole();
    expect($record->groups()->count())->toBe(0)->and($record->attributes()->count())->toBe(0)->and(Option::count())->toBe(0);
});

test('local inclusion dialogs preserve shared identity and stored default through option reordering', function () {
    [$configurator, $data] = canonicalDefinitionFixture();
    $draft = $data['attributes'][0];
    unset($draft['display_order'], $draft['code_order']);
    $draft['default_configurator_option_id'] = $draft['options'][0]['id'];
    foreach ($draft['options'] as &$option) {
        unset($option['display_order']);
    }
    unset($option);
    $manager = Livewire\Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class]);
    $manager->callTableAction('include', data: $draft)->assertHasNoTableActionErrors();
    $inclusion = $configurator->attributes()->sole();
    $default = $inclusion->default_configurator_option_id;
    $saved = app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh())['attributes'][0];
    unset($saved['display_order'], $saved['code_order']);
    $saved['options'] = array_reverse($saved['options']);
    foreach ($saved['options'] as &$option) {
        unset($option['display_order']);
    }
    unset($option);
    $saved['label_override'] = 'Local only';
    $manager->callTableAction('edit', $inclusion, data: $saved)->assertHasNoTableActionErrors();
    expect($inclusion->fresh()->default_configurator_option_id)->toBe($default)->and($inclusion->fresh()->label_override)->toBe('Local only')->and($inclusion->attribute->label)->toBe('A');
    expect($inclusion->options()->orderBy('display_order')->pluck('id')->all())->toBe(array_map('intval', array_column($saved['options'], 'id')));
});

test('local ordering uses complete permutations and direct mutation methods reauthorize the owner', function () {
    [$configurator, $data] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle($this->actor, $configurator, $data);
    $ids = $configurator->attributes()->orderBy('display_order')->pluck('id')->all();
    $manager = Livewire\Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class]);
    $manager->call('saveCodeOrder', array_reverse($ids))->assertHasNoErrors();
    expect($configurator->attributes()->orderBy('display_order')->pluck('id')->all())->toBe($ids)->and($configurator->attributes()->orderBy('code_order')->pluck('id')->all())->toBe(array_reverse($ids));
    $manager->call('reorderTable', [$ids[0]])->assertHasErrors(['order']);
    $this->actingAs(User::factory()->create());
    $manager->call('moveAttribute', $ids[0], 1)->assertForbidden();
});

test('duplicate action remaps local rows and copies no Group assignments', function () {
    [$configurator, $data] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle($this->actor, $configurator, $data);
    Group::factory()->create(['configurator_id' => $configurator->id]);
    Livewire\Livewire::test(EditConfigurator::class, ['record' => $configurator->id])
        ->callAction('duplicate', data: ['name' => 'Independent copy'])->assertHasNoActionErrors();
    $copy = Configurator::where('name', 'Independent copy')->sole();
    expect($copy->groups()->count())->toBe(0)->and($copy->attributes()->count())->toBe(3)->and(Option::count())->toBe(6);
});

test('deleting a shared Configurator requires unassignment and keeps canonical library records', function () {
    [$configurator, $data] = canonicalDefinitionFixture();
    $data['rules'] = [fixtureMapping()];
    app(SaveConfiguratorDefinition::class)->handle($this->actor, $configurator, $data);
    $group = Group::factory()->create(['configurator_id' => $configurator->id]);
    $action = app(DeleteConfigurator::class);
    expect(fn () => $action->handle($this->actor, $configurator))->toThrow(ValidationException::class);
    $group->update(['configurator_id' => null]);
    $action->handle($this->actor, $configurator);
    expect($configurator->fresh())->toBeNull()->and(ConfiguratorAttribute::count())->toBe(0)->and(ConfiguratorRule::count())->toBe(0)->and(Option::count())->toBe(6);
});

test('failed inclusion validation keeps the staged draft and renders its repair summary', function () {
    [$configurator, $data] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle($this->actor, $configurator, $data);
    $inclusion = $configurator->attributes()->orderBy('display_order')->first();
    $draft = app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh())['attributes'][0];
    unset($draft['display_order'], $draft['code_order']);
    array_shift($draft['options']);
    foreach ($draft['options'] as &$option) {
        unset($option['display_order']);
    } unset($option);
    $draft['label_override'] = 'Unsaved local draft';
    $component = Livewire\Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->callTableAction('edit', $inclusion, data: $draft)->assertHasTableActionErrors(['default_configurator_option_id'])
        ->assertSet('mountedActions.0.data.label_override', 'Unsaved local draft');
    $schema = $component->instance()->getSchema($component->instance()->getMountedActionSchemaName());
    expect($schema->toHtml())->toContain('Repair these items before saving', 'Choose a default from this inclusion');
    expect($inclusion->fresh()->label_override)->toBeNull()->and($inclusion->options()->count())->toBe(2);
});

test('broad page save cannot bypass the scoped definition actions', function () {
    $configurator = Configurator::factory()->create(['name' => 'Original']);
    Livewire\Livewire::test(EditConfigurator::class, ['record' => $configurator->id])->set('data.name', 'Forged')->call('save')->assertStatus(405);
    expect($configurator->fresh()->name)->toBe('Original');
});

test('Groups tab lists assignments and available leaves with scoped immediate assign and unassign actions', function () {
    $configurator = Configurator::factory()->create();
    $other = Configurator::factory()->create();
    $assigned = Group::factory()->create(['name' => 'Assigned leaf', 'configurator_id' => $configurator->id]);
    $available = Group::factory()->create(['name' => 'Available leaf']);
    $foreign = Group::factory()->create(['name' => 'Other owner leaf', 'configurator_id' => $other->id]);
    $parent = Group::factory()->create(['name' => 'Branch only']);
    Group::factory()->create(['parent_id' => $parent->id]);
    $component = Livewire\Livewire::test(ConfiguratorGroups::class, ['configuratorId' => $configurator->id])
        ->assertSee('Assigned leaf')->assertSee('Available leaf')->assertDontSee('Other owner leaf')->assertDontSee('Branch only')
        ->call('assignGroup', $available->id)->assertHasNoErrors();
    expect($assigned->fresh()->configurator_id)->toBe($configurator->id)->and($available->fresh()->configurator_id)->toBe($configurator->id);
    $component->call('assignGroup', $foreign->id)->assertHasErrors(['groups'])
        ->call('unassignGroup', $assigned->id)->assertHasNoErrors();
    expect($assigned->fresh()->configurator_id)->toBeNull()->and($available->fresh()->configurator_id)->toBe($configurator->id)->and($foreign->fresh()->configurator_id)->toBe($other->id);
    $this->actingAs(User::factory()->create());
    $component->call('unassignGroup', $available->id)->assertForbidden();
});

test('an unexpected Overview save failure keeps the draft and shows a safe actionable error', function () {
    $record = Configurator::factory()->create(['name' => 'Persisted name']);
    $this->mock(SaveConfiguratorSettings::class)->shouldReceive('handle')->once()->andThrow(new RuntimeException('private database detail'));
    Livewire\Livewire::test(ConfiguratorOverview::class, ['configuratorId' => $record->id])
        ->fillForm(['name' => 'Unsaved name', 'description' => null, 'context_schema' => ['territory' => [], 'application' => []]])
        ->call('saveOverview')->assertHasFormErrors(['save'])
        ->assertSet('data.name', 'Unsaved name')->assertSee('Changes could not be saved')->assertDontSee('private database detail');
    expect($record->fresh()->name)->toBe('Persisted name');
});
