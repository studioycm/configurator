<?php

use App\Actions\ChangeCatalogStatus;
use App\Actions\SaveConfiguratorDefinition;
use App\Filament\Resources\Configurators\Pages\EditConfigurator;
use App\Filament\Resources\Configurators\RelationManagers\AttributesRelationManager;
use App\Filament\Resources\Options\Pages\ListOptions;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Attribute;
use App\Models\Configurator;
use App\Models\Group;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogSnapshots;
use App\Services\ConfiguratorDefinitionLoader;
use App\Services\ConfiguratorEngine;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

require_once dirname(__DIR__, 2).'/ConfiguratorFixtures.php';

beforeEach(function () {
    $this->actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $this->actingAs($this->actor);
});

function statusConfigurationFixture(): array
{
    [$configurator, $draft] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $draft);
    $group = Group::factory()->create(['configurator_id' => $configurator->id]);
    $product = Product::factory()->for($group)->create();

    return [$configurator, $group, $product];
}

test('disabling a branch hides its descendants and direct routes without changing descendant status', function () {
    $parent = Group::factory()->create();
    $leaf = Group::factory()->create(['parent_id' => $parent->id]);
    $product = Product::factory()->for($leaf)->create();
    app(ChangeCatalogStatus::class)->handle($this->actor, $parent, false);
    $this->get(route('catalog.groups.show', $leaf))->assertNotFound();
    $this->get(route('catalog.products.show', $product))->assertNotFound();
    $this->get(route('catalog.index'))->assertDontSee($parent->name);
    expect($leaf->fresh()->is_active)->toBeTrue()->and($product->fresh()->is_active)->toBeTrue();
    app(ChangeCatalogStatus::class)->handle($this->actor, $parent, true);
    $this->get(route('catalog.groups.show', $leaf))->assertOk();
});

test('disabling a Product removes cached rows and direct access and re enabling restores it', function () {
    [$configurator, $group, $product] = statusConfigurationFixture();
    $snapshots = app(CatalogSnapshots::class);
    $before = $snapshots->get($group->id);
    app(ChangeCatalogStatus::class)->handle($this->actor, $product, false);
    expect($snapshots->get($group->id)->data['rows'])->toBe([])
        ->and($snapshots->revision($group->id))->not->toBe($before->revision());
    $this->get(route('catalog.products.show', $product))->assertNotFound();
    app(ChangeCatalogStatus::class)->handle($this->actor, $product, true);
    expect($snapshots->get($group->id)->data['rows'])->toHaveCount(1);
});

test('a branch with no visible children stays a branch without mounting leaf filters', function () {
    $parent = Group::factory()->create();
    $child = Group::factory()->create(['parent_id' => $parent->id]);
    app(ChangeCatalogStatus::class)->handle($this->actor, $child, false);
    $this->get(route('catalog.groups.show', $parent))->assertOk()
        ->assertSee('There are no available groups in this branch.')
        ->assertDontSee('data-catalog-filters', escape: false);
    app(ChangeCatalogStatus::class)->handle($this->actor, $child, true);
    $this->get(route('catalog.groups.show', $parent))->assertOk()->assertSee($child->name);
});

test('Configurator disabling requires an explicit Group choice and implements each reversible visibility outcome', function (string $behavior) {
    [$configurator, $group, $product] = statusConfigurationFixture();
    $action = app(ChangeCatalogStatus::class);
    expect(fn () => $action->handle($this->actor, $configurator, false))->toThrow(ValidationException::class);
    expect($configurator->fresh()->is_active)->toBeTrue();
    $action->handle($this->actor, $configurator, false, $behavior);
    expect($group->fresh()->is_active)->toBeTrue()->and($product->fresh()->is_active)->toBeTrue();
    if ($behavior === 'hide') {
        $this->get(route('catalog.products.show', $product))->assertNotFound();
        $action->handle($this->actor, $configurator, true);
        $this->get(route('catalog.products.show', $product))->assertOk();
    } else {
        $this->get(route('catalog.products.show', $product))->assertOk()->assertSee('Configuration is not available');
        expect($group->fresh()->configurator_id)->toBe($behavior === 'unassign' ? null : $configurator->id);
    }
})->with(['visible', 'hide', 'unassign']);

test('inactive shared or included Attributes make configuration unavailable and survive metadata saves', function (bool $shared) {
    [$configurator, $group, $product] = statusConfigurationFixture();
    $inclusion = $configurator->attributes()->orderBy('display_order')->first();
    $record = $shared ? $inclusion->attribute : $inclusion;
    app(ChangeCatalogStatus::class)->handle($this->actor, $record, false);
    $loader = app(ConfiguratorDefinitionLoader::class);
    $engine = app(ConfiguratorEngine::class);
    $result = $engine->evaluate($loader->forDashboardProduct($product->id));
    expect($result->isComplete)->toBeFalse()->and($result->configurationCode)->toBeNull()
        ->and(array_column($result->diagnostics, 'code'))->toContain('inactive_attribute');
    app(SaveConfiguratorDefinition::class)->change($this->actor, $configurator, fn (array $draft): array => [...$draft, 'name' => 'Metadata update']);
    expect($record->fresh()->is_active)->toBeFalse();
    app(ChangeCatalogStatus::class)->handle($this->actor, $record, true);
    expect($engine->evaluate($loader->forDashboardProduct($product->id))->configurationCode)->toBe('A0-B0-C0');
})->with([true, false]);

test('disabled and hidden shared Options reject selection and an unavailable stored default requires repair', function (bool $hidden) {
    [$configurator, $group, $product] = statusConfigurationFixture();
    $inclusion = $configurator->attributes()->orderBy('display_order')->first();
    $options = $inclusion->options()->orderBy('display_order')->get();
    $action = app(ChangeCatalogStatus::class);
    $action->handle($this->actor, $options[1]->option, $hidden, hidden: $hidden);
    $loader = app(ConfiguratorDefinitionLoader::class);
    $engine = app(ConfiguratorEngine::class);
    $result = $engine->evaluate($loader->forDashboardProduct($product->id, intent: ['kind' => 'SelectOption', 'attribute_id' => (string) $inclusion->id, 'option_id' => (string) $options[1]->id]));
    expect($result->configurationCode)->toBe('A0-B0-C0')->and(array_column($result->diagnostics, 'code'))->toContain('selection_rejected');
    expect(in_array((string) $options[1]->id, $result->attributes[(string) $inclusion->id]['hidden'], true))->toBe($hidden);
    $action->handle($this->actor, $options[0]->option, $hidden, hidden: $hidden);
    $unavailable = $engine->evaluate($loader->forDashboardProduct($product->id));
    expect($unavailable->configurationCode)->toBeNull()->and(array_column($unavailable->diagnostics, 'code'))->toContain('inactive_default')
        ->and($inclusion->fresh()->default_configurator_option_id)->toBe($options[0]->id)
        ->and($inclusion->options()->count())->toBe(2);
})->with([true, false]);

test('status action rejects unauthorized writes and invalid Configurator choices', function () {
    $configurator = Configurator::factory()->create();
    expect(fn () => app(ChangeCatalogStatus::class)->handle(User::factory()->create(), $configurator, false, 'hide'))->toThrow(AuthorizationException::class);
    expect(fn () => app(ChangeCatalogStatus::class)->handle($this->actor, $configurator, false, 'invalid'))->toThrow(ValidationException::class);
    expect($configurator->fresh()->is_active)->toBeTrue();
});

test('the Configurator dialog lists impact and applies its chosen Group policy', function () {
    [$configurator, $group, $product] = statusConfigurationFixture();
    $dialog = Livewire::test(EditConfigurator::class, ['record' => $configurator->id])->mountAction('changeStatus');
    expect($dialog->instance()->getSchema($dialog->instance()->getMountedActionSchemaName())->toHtml())->toContain('1 assigned Groups', '1 Products');
    $dialog->fillForm(['is_active' => '0', 'group_behavior' => 'hide'])->callMountedAction()->assertHasNoActionErrors();
    expect($configurator->fresh()->is_active)->toBeFalse()->and($configurator->fresh()->disabled_group_behavior)->toBe('hide');
});

test('a changed assignment blocks a stale status dialog and keeps every flag unchanged', function () {
    [$configurator, $group, $product] = statusConfigurationFixture();
    $dialog = Livewire::test(EditConfigurator::class, ['record' => $configurator->id])->mountAction('changeStatus')
        ->fillForm(['is_active' => '0', 'group_behavior' => 'unassign']);
    $newGroup = Group::factory()->create(['configurator_id' => $configurator->id]);
    $dialog->callMountedAction()->assertHasActionErrors(['is_active']);
    expect($configurator->fresh()->is_active)->toBeTrue()->and($group->fresh()->configurator_id)->toBe($configurator->id)->and($newGroup->fresh()->configurator_id)->toBe($configurator->id);
});

test('Product table status supports a single record and an atomic selection', function () {
    $first = Product::factory()->create();
    $second = Product::factory()->create();
    $page = Livewire::test(ListProducts::class);
    $page->mountAction(TestAction::make('changeStatus')->table($first))->fillForm(['is_active' => '0'])->callMountedAction()->assertHasNoActionErrors();
    expect($first->fresh()->is_active)->toBeFalse();
    $page->selectTableRecords([$first, $second])->mountAction(TestAction::make('batchStatus')->table()->bulk())
        ->fillForm(['is_active' => '1'])->callMountedAction()->assertHasNoActionErrors();
    expect($first->fresh()->is_active)->toBeTrue()->and($second->fresh()->is_active)->toBeTrue();
});

test('a changed Product set blocks a stale Configurator impact review', function () {
    [$configurator, $group] = statusConfigurationFixture();
    $dialog = Livewire::test(EditConfigurator::class, ['record' => $configurator->id])->mountAction('changeStatus')
        ->fillForm(['is_active' => '0', 'group_behavior' => 'hide']);
    Product::factory()->for($group)->create();
    $dialog->callMountedAction()->assertHasActionErrors(['is_active']);
    expect($configurator->fresh()->is_active)->toBeTrue();
});

test('selected inclusion statuses update one complete owner definition and retain unselected rows', function () {
    [$configurator, $group] = statusConfigurationFixture();
    $rows = $configurator->attributes()->orderBy('display_order')->get();
    $revision = (int) $group->fresh()->catalog_revision;
    Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->selectTableRecords([$rows[0], $rows[1]])->mountAction(TestAction::make('batchStatus')->table()->bulk())
        ->fillForm(['is_active' => '0'])->callMountedAction()->assertHasNoActionErrors();
    expect($rows[0]->fresh()->is_active)->toBeFalse()->and($rows[1]->fresh()->is_active)->toBeFalse()
        ->and($rows[2]->fresh()->is_active)->toBeTrue()->and($configurator->attributes()->count())->toBe(3)
        ->and((int) $group->fresh()->catalog_revision)->toBe($revision + 1);
});

test('Option visibility is independent of Disabled and both prevent selection', function () {
    [$configurator] = statusConfigurationFixture();
    $option = $configurator->attributes()->first()->options()->first()->option;
    $page = Livewire::test(ListOptions::class);
    $page->mountAction(TestAction::make('changeVisibility')->table($option))
        ->fillForm(['visibility' => 'hidden'])->callMountedAction()->assertHasNoActionErrors();
    expect($option->fresh()->is_active)->toBeTrue()->and($option->fresh()->is_hidden)->toBeTrue();
    $page->mountAction(TestAction::make('changeStatus')->table($option))
        ->fillForm(['is_active' => '0', 'visibility' => 'visible'])->callMountedAction()->assertHasNoActionErrors();
    expect($option->fresh()->is_active)->toBeFalse()->and($option->fresh()->is_hidden)->toBeFalse();
});

test('duplication preserves disabled local rows and retained shared references without assignments', function () {
    [$configurator, $group] = statusConfigurationFixture();
    $inclusion = $configurator->attributes()->orderBy('display_order')->first();
    app(ChangeCatalogStatus::class)->handle($this->actor, $inclusion, false);
    app(ChangeCatalogStatus::class)->handle($this->actor, $inclusion->attribute, false);
    app(ChangeCatalogStatus::class)->handle($this->actor, $configurator, false, 'hide');
    $copy = app(SaveConfiguratorDefinition::class)->duplicate($this->actor, $configurator, 'Disabled copy');
    expect($copy->is_active)->toBeFalse()->and($copy->groups()->count())->toBe(0)
        ->and($copy->attributes()->orderBy('display_order')->first()->is_active)->toBeFalse()
        ->and($copy->attributes()->orderBy('display_order')->first()->attribute_id)->toBe($inclusion->attribute_id);
});

test('forged new inclusions cannot use a disabled shared Attribute', function () {
    [$configurator, $draft] = canonicalDefinitionFixture();
    $attribute = Attribute::findOrFail($draft['attributes'][0]['attribute_id']);
    app(ChangeCatalogStatus::class)->handle($this->actor, $attribute, false);
    expect(fn () => app(SaveConfiguratorDefinition::class)->handle($this->actor, $configurator, $draft))->toThrow(ValidationException::class, 'Disabled Attributes cannot be newly included');
    expect($configurator->attributes()->count())->toBe(0);
});
