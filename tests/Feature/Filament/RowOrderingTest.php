<?php

use App\Actions\ReorderCatalogGroups;
use App\Actions\SaveConfiguratorDefinition;
use App\Filament\Resources\Configurators\Pages\EditConfigurator;
use App\Filament\Resources\Configurators\RelationManagers\AttributesRelationManager;
use App\Filament\Resources\Groups\Pages\ListGroups;
use App\Models\Group;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

require_once dirname(__DIR__, 2).'/ConfiguratorFixtures.php';

beforeEach(function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
});

test('Group reordering updates only a complete sibling scope and invalidates its catalog revision', function () {
    $parent = Group::factory()->create();
    $first = Group::factory()->for($parent, 'parent')->create(['sort_order' => 0]);
    $second = Group::factory()->for($parent, 'parent')->create(['sort_order' => 1]);
    $outside = Group::factory()->create(['sort_order' => 9]);
    $revision = $parent->fresh()->catalog_revision;

    app(ReorderCatalogGroups::class)->handle(auth()->user(), $parent->id, [$second->id, $first->id]);

    expect($parent->children()->orderBy('sort_order')->pluck('id')->all())->toBe([$second->id, $first->id]);
    expect($parent->fresh()->catalog_revision)->not->toBe($revision);
    $this->assertDatabaseHas('groups', ['id' => $outside->id, 'sort_order' => 9, 'parent_id' => null]);
});

test('Group reordering rejects incomplete duplicate and foreign sibling IDs without partial writes', function (string $invalid) {
    $parent = Group::factory()->create();
    $children = Group::factory()->count(2)->for($parent, 'parent')->create(['sort_order' => 7]);
    $outside = Group::factory()->create();
    $order = match ($invalid) {
        'missing' => [$children[0]->id],
        'duplicate' => [$children[0]->id, $children[0]->id],
        'foreign' => [$children[0]->id, $outside->id],
    };

    expect(fn () => app(ReorderCatalogGroups::class)->handle(auth()->user(), $parent->id, $order))->toThrow(ValidationException::class);

    expect($parent->children()->pluck('sort_order')->all())->toBe([7, 7]);
})->with(['missing', 'duplicate', 'foreign']);

test('Group reordering requires catalog administration permission', function () {
    $outsider = User::factory()->create();

    expect(fn () => app(ReorderCatalogGroups::class)->handle($outsider, null, []))->toThrow(AuthorizationException::class);
});

test('Group drag mode shows complete siblings while retaining list filters search and editor selection', function () {
    $parent = Group::factory()->create();
    $children = Group::factory()->count(2)->for($parent, 'parent')->create();
    $outside = Group::factory()->create();
    $list = Livewire::test(ListGroups::class)->call('selectRecord', (string) $children[0]->id)
        ->set('tableSearch', 'not matching')->set('tableFilters.configurator_id.value', '999');

    $list->call('beginGroupReordering', $parent->id)->assertSet('isTableReordering', true)
        ->assertCanSeeTableRecords($children)->assertCanNotSeeTableRecords([$parent, $outside])
        ->assertSet('selectedRecord', (string) $children[0]->id)
        ->callAction(TestAction::make('dragOrder')->table())->assertSet('isTableReordering', false)
        ->assertSet('tableSearch', 'not matching')->assertSet('tableFilters.configurator_id.value', '999')
        ->assertCanNotSeeTableRecords($children);
});

test('rendered drag completion stays bound to its cached table action', function () {
    Group::factory()->create();
    $list = Livewire::test(ListGroups::class)->call('beginGroupReordering', null);

    expect(html_entity_decode($list->html()))->toContain("mountAction('dragOrder', {}, JSON.parse('{\\u0022table\\u0022:true}'))");
});

test('Configurator drag mode keeps editor drafts and resumes search deferred filters and sorting', function () {
    [$configurator, $draft] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $draft);
    $attributes = $configurator->attributes()->orderBy('display_order')->get();
    $manager = Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->call('selectAttribute', $attributes[0]->id)->fillForm(['label_override' => 'Keep unsaved draft'], 'editorForm')
        ->set('tableSearch', 'no matching attribute')->set('tableSort', 'id:desc');
    $filters = $manager->get('tableFilters');
    $pending = $manager->get('tableDeferredFilters');

    $manager->call('toggleTableReordering')->assertSet('isTableReordering', true)->assertCanSeeTableRecords($attributes)
        ->call('reorderTable', $attributes->pluck('id')->reverse()->values()->all())->assertHasNoErrors()
        ->assertSet('editorData.label_override', 'Keep unsaved draft')->assertSet('isTableReordering', true)
        ->call('toggleTableReordering')->assertSet('isTableReordering', false)
        ->assertSet('tableSearch', 'no matching attribute')->assertSet('tableSort', 'id:desc')
        ->assertSet('tableFilters', $filters)->assertSet('tableDeferredFilters', $pending)
        ->assertCanNotSeeTableRecords($attributes);
    expect($configurator->attributes()->orderBy('display_order')->pluck('id')->all())->toBe([$attributes[2]->id, $attributes[1]->id, $attributes[0]->id]);
});
