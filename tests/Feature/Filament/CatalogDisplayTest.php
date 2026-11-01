<?php

use App\Actions\SaveCatalogPresentationSettings;
use App\Actions\SaveGroupSettings;
use App\Actions\SaveGroupTags;
use App\Filament\Pages\CatalogDisplay;
use App\Filament\Resources\CatalogDisplayForm;
use App\Models\CatalogPresentationSettings;
use App\Models\Group;
use App\Models\GroupTagCategory;
use App\Models\GroupTagValue;
use App\Models\User;
use App\Services\CatalogPresentation;
use Livewire\Livewire;

/** @return array<string,bool> */
function catalogDisplayDirtyMarkers(string $html): array
{
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $markers = [];
    foreach ((new DOMXPath($document))->query('//*[@data-draft-status]') as $node) {
        $markers[$node->getAttribute('data-draft-status')] = $node->getAttribute('data-dirty') === 'true';
    }

    return $markers;
}

test('native vocabulary actions preserve exact labels identity and guarded removal', function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    $page = Livewire::test(CatalogDisplay::class)->mountAction('category')->fillForm(['label' => '001', 'sort_order' => 0])->callMountedAction()->assertHasNoFormErrors();
    $category = GroupTagCategory::query()->sole();
    $page->mountAction('value')->fillForm(['group_tag_category_id' => $category->id, 'label' => '0', 'sort_order' => 0])->callMountedAction()->assertHasNoFormErrors();
    $value = GroupTagValue::query()->sole();
    expect($category->label)->toBe('001');
    expect($value->label)->toBe('0');
    $page->mountAction('removeCategory')->fillForm(['record_id' => $category->id])->callMountedAction()->assertHasFormErrors(['record_id']);
    $this->assertModelExists($category);
    $page->unmountAction()->mountAction('removeValue')->fillForm(['record_id' => $value->id])->callMountedAction()->assertHasNoFormErrors();
    $this->assertModelMissing($value);
    $page->mountAction('category')->fillForm(['record_id' => $category->id, 'label' => 'Exact renamed label', 'sort_order' => 3])->callMountedAction()->assertHasNoFormErrors();
    expect($category->fresh()->label)->toBe('Exact renamed label');
});

test('Catalog Display is explicitly registered and access is limited to catalog managers', function () {
    $this->get('/admin/catalog-display')->assertRedirect();
    $this->actingAs(User::factory()->create());
    $this->get('/admin/catalog-display')->assertForbidden();
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    $this->get('/admin/catalog-display')->assertOk()->assertSee('Catalog Display');
});

test('native attribute controls distinguish empty Group overrides from inheritance and keep facts untouched', function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    $field = CatalogDisplayForm::field('group_card_attribute_keys');
    expect($field->isSearchable())->toBeFalse()
        ->and(array_keys($field->getOptions()))->toBe(array_keys(config('group_attributes.definitions')));
    $group = Group::factory()->create(['catalog_attributes' => ['application' => [' 001 ']]]);
    $page = Livewire::test(CatalogDisplay::class)->call('requestGroup', $group->id)
        ->set('groupData.use_group_card_attribute_keys', true)->set('groupData.group_card_attribute_keys', [])
        ->set('groupData.use_group_card_attribute_labels', true)->set('groupData.group_card_attribute_labels', false)
        ->call('saveGroupPresentation')->assertHasNoErrors();
    expect($group->fresh()->result_settings['catalog_presentation'])->toBe(['group_card_attribute_keys' => [], 'group_card_attribute_labels' => false])
        ->and($group->fresh()->catalog_attributes)->toBe(['application' => [' 001 ']]);
    $page->set('groupData.use_group_card_attribute_keys', false)->call('saveGroupPresentation')->assertHasNoErrors();
    expect($group->fresh()->result_settings['catalog_presentation'])->not->toHaveKey('group_card_attribute_keys');
    $page->set('data.branch_attribute_retention', 'clear')->set('data.group_card_attribute_keys', ['fluid_type', 'application'])
        ->call('save')->assertHasNoErrors();
    expect(app(CatalogPresentation::class)->current()['settings']['group_card_attribute_keys'])->toBe(['fluid_type', 'application']);
});

test('native shared form persists only its state and reset remains an unsaved draft', function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    $group = Group::factory()->create();
    $before = $group->fresh()->getAttributes();
    $page = Livewire::test(CatalogDisplay::class)->assertSchemaExists('form')->assertSchemaExists('groupForm')->assertSchemaExists('tagForm');
    expect(catalogDisplayDirtyMarkers($page->html()))->toBe(['shared' => false]);
    $page->set('data.sidebar_mode', 'tags')->set('data.tag_counts', false);
    expect(catalogDisplayDirtyMarkers($page->html()))->toBe(['shared' => true]);
    $page->call('save')->assertHasNoErrors();
    expect(catalogDisplayDirtyMarkers($page->html()))->toBe(['shared' => false]);
    $record = CatalogPresentationSettings::query()->findOrFail(1);
    expect($record->settings['sidebar_mode'])->toBe('tags');
    expect($record->settings['tag_counts'])->toBeFalse();
    expect($group->fresh()->getAttributes())->toBe($before);
    $page->call('resetDefaults')->assertSet('data.sidebar_mode', 'tree');
    expect(catalogDisplayDirtyMarkers($page->html()))->toBe(['shared' => true]);
    expect($record->fresh()->settings['sidebar_mode'])->toBe('tags');
    app(SaveCatalogPresentationSettings::class)->handle(auth()->user(), array_replace($record->settings, ['group_card_density' => 'comfortable']), $record->version);
    $page->call('save')->assertHasErrors(['data']);
    expect(catalogDisplayDirtyMarkers($page->html()))->toBe(['shared' => true]);
    expect($record->fresh()->settings['sidebar_mode'])->toBe('tags');
});

test('Group presentation and assignment use independent explicit native saves and stale saves retain drafts', function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    $group = Group::factory()->create(['result_settings' => ['future' => false]])->fresh();
    $value = GroupTagValue::factory()->create();
    $page = Livewire::test(CatalogDisplay::class)->call('requestGroup', $group->id)
        ->set('groupData.use_group_image_placement', true)->set('groupData.group_image_placement', 'right')
        ->set('tagData.value_ids', [$value->id]);
    expect(catalogDisplayDirtyMarkers($page->html()))->toBe(['shared' => false, 'group' => true, 'tags' => true]);
    $page->call('saveGroupPresentation')->assertHasNoErrors();
    expect(catalogDisplayDirtyMarkers($page->html()))->toBe(['shared' => false, 'group' => false, 'tags' => true]);
    $page->call('resetGroupOverrides');
    expect(catalogDisplayDirtyMarkers($page->html()))->toBe(['shared' => false, 'group' => true, 'tags' => true]);
    $page->set('groupData.use_group_image_placement', true);
    expect(catalogDisplayDirtyMarkers($page->html()))->toBe(['shared' => false, 'group' => false, 'tags' => true]);
    expect($group->fresh()->result_settings['catalog_presentation'])->toBe(['group_image_placement' => 'right']);
    expect($group->fresh()->tagValues()->count())->toBe(0);
    $page->call('saveGroupTags')->assertHasNoErrors();
    expect(catalogDisplayDirtyMarkers($page->html()))->toBe(['shared' => false, 'group' => false, 'tags' => false]);
    expect($group->fresh()->tagValues()->pluck('group_tag_values.id')->all())->toBe([$value->id]);
    $this->assertDatabaseCount('catalog_presentation_settings', 0);
    $latest = $group->fresh();
    app(SaveGroupSettings::class)->patchPresentation(auth()->user(), $latest, ['catalog_presentation' => ['group_image_placement' => 'below']], [], app(CatalogPresentation::class)->groupFingerprint($latest));
    $page->set('groupData.group_image_placement', 'above')->call('saveGroupPresentation')->assertHasErrors(['groupData']);
    $page->assertSet('groupData.group_image_placement', 'above');
    expect(catalogDisplayDirtyMarkers($page->html()))->toBe(['shared' => false, 'group' => true, 'tags' => false]);
    expect($group->fresh()->result_settings['future'])->toBeFalse();
    $tags = app(SaveGroupTags::class);
    $tags->assign(auth()->user(), $group, [], $tags->fingerprint($group));
    $page->set('tagData.value_ids', [])->call('saveGroupTags')->assertHasErrors(['tagData.value_ids']);
    expect(catalogDisplayDirtyMarkers($page->html()))->toBe(['shared' => false, 'group' => true, 'tags' => true]);
});

test('dirty Group context requires an explicit decision and discarding preserves the independent shared draft', function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    $first = Group::factory()->create();
    $second = Group::factory()->create();
    $page = Livewire::test(CatalogDisplay::class)->call('requestGroup', $first->id)
        ->set('data.sidebar_mode', 'tags')->set('groupData.use_group_card_content', true)->set('groupData.group_card_content', [])
        ->call('requestGroup', $second->id)->assertSet('selectedGroupId', $first->id)->assertSet('pendingGroupId', $second->id)
        ->call('resolveGroupChange', 'stay')->assertSet('pendingGroupId', null)->assertSet('selectedGroupId', $first->id)
        ->call('requestGroup', $second->id)->call('resolveGroupChange', 'discard')->assertSet('selectedGroupId', $second->id)
        ->assertSet('data.sidebar_mode', 'tags');
    expect(catalogDisplayDirtyMarkers($page->html()))->toBe(['shared' => true, 'group' => false, 'tags' => false]);
    expect($first->fresh()->result_settings)->not->toHaveKey('catalog_presentation');
});

test('lookup and action entry points reject forged and newly denied calls without writes', function () {
    $manager = User::factory()->create(['email' => 'ycm@data4.work']);
    $this->actingAs($manager);
    $group = Group::factory()->create();
    $page = Livewire::test(CatalogDisplay::class)->call('requestGroup', $group->id);
    $page->call('requestGroup', '../1')->assertHasErrors(['groupData']);
    $this->actingAs(User::factory()->create());
    $page->call('save')->assertForbidden();
    $this->assertDatabaseCount('catalog_presentation_settings', 0);
});
