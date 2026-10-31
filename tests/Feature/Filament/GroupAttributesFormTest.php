<?php

use App\Actions\SaveGroupAttributes;
use App\Filament\Resources\Groups\Pages\CreateGroup;
use App\Filament\Resources\Groups\Pages\EditGroup;
use App\Models\Configurator;
use App\Models\Group;
use App\Models\GroupTagValue;
use App\Models\Product;
use App\Models\User;
use App\Services\GroupAttributes;
use Filament\Forms\Components\TagsInput;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
});

test('root branch and leaf editors expose native own Group attributes without changing their other fields', function (string $level) {
    $parent = $level === 'root' ? null : Group::factory()->create();
    $group = Group::factory()->create(['parent_id' => $parent?->id]);
    if ($level !== 'leaf') {
        Group::factory()->for($group, 'parent')->create();
    }
    $editor = Livewire::test(EditGroup::class, ['record' => $group->id]);
    foreach (array_keys(app(GroupAttributes::class)->definitions()) as $key) {
        $field = collect($editor->instance()->form->getFlatFields(withHidden: true))
            ->first(fn ($field): bool => $field->getStatePath() === 'data.catalog_attributes.'.$key);
        expect($field)->toBeInstanceOf(TagsInput::class);
        $editor->assertSet('data.catalog_attributes.'.$key, []);
    }
    foreach (['name', 'parent_id'] as $key) {
        expect(collect($editor->instance()->form->getFlatFields(withHidden: true))
            ->contains(fn ($field): bool => $field->getStatePath() === 'data.'.$key))->toBeTrue();
    }
    $editor->set('data.catalog_attributes.application', ['Own label'])->call('save')->assertHasNoFormErrors();
    expect($group->fresh()->catalog_attributes)->toBe(['application' => ['Own label']]);
})->with(['root', 'branch', 'leaf']);

test('embedded Group creation persists native chip values with client and writer normalization parity', function () {
    $inputs = [' 0 ', "\u{00A0}001\u{00A0}", "\u{FEFF}é Multi Word\u{FEFF}", "\u{200B}0\u{200B}", "\u{200C}0\u{200C}", "\u{200D}0\u{200D}", "\u{00AD}0\u{00AD}"];
    $expected = ['0', '001', 'é Multi Word', "\u{200B}0\u{200B}", "\u{200C}0\u{200C}", "\u{200D}0\u{200D}", "\u{00AD}0\u{00AD}"];
    Livewire::test(CreateGroup::class, ['embedded' => true])
        ->fillForm(['name' => 'Explicit authored Group', 'catalog_attributes' => ['application' => $inputs]])
        ->call('create')->assertHasNoFormErrors()->assertDispatched('catalog-record-created')->assertNoRedirect();
    $group = Group::query()->sole();
    expect(app(GroupAttributes::class)->document($group))->toBe(['application' => $expected])
        ->and(json_decode($group->getRawOriginal('catalog_attributes')))->toBeInstanceOf(stdClass::class)
        ->and((int) $group->catalog_revision)->toBe(2);
});

test('unchanged native chips retain bytes and future keys while edits preserve tags hierarchy Configurator settings and Product facts', function () {
    $parent = Group::factory()->create();
    $configurator = Configurator::factory()->create();
    $retained = [' 0 ', '001', 'Case', 'case', 'מים Multi Word'];
    $stored = ['application' => $retained, 'fluid_type' => ['Water'], 'future_attribute' => ['Future fact']];
    $group = Group::factory()->for($parent, 'parent')->for($configurator)->create(['catalog_attributes' => $stored]);
    $tag = GroupTagValue::factory()->create();
    $group->tagValues()->attach($tag, ['position' => 0]);
    $product = Product::factory()->for($group)->create(['profile_fields' => ['pressure' => '001']]);
    $editor = Livewire::test(EditGroup::class, ['record' => $group->id]);
    $editor->assertSet('data.catalog_attributes.application', $retained);
    expect($editor->get('data.catalog_attributes'))->not->toHaveKey('future_attribute');
    $raw = $group->fresh()->getRawOriginal('catalog_attributes');
    $revision = $group->fresh()->catalog_revision;
    $this->travel(1)->minutes();
    $editor->call('save')->assertHasNoFormErrors();
    expect($group->fresh()->getRawOriginal('catalog_attributes'))->toBe($raw)
        ->and($group->fresh()->catalog_revision)->toBe($revision);
    $before = $group->fresh()->getAttributes();
    $configuratorBefore = $configurator->fresh()->getAttributes();
    $productBefore = $product->fresh()->getAttributes();
    $editor->set('data.catalog_attributes.application', ['Changed label'])->call('save')->assertHasNoFormErrors();
    $after = $group->fresh()->getAttributes();
    expect($group->fresh()->catalog_attributes)->toBe(['application' => ['Changed label'], 'fluid_type' => ['Water'], 'future_attribute' => ['Future fact']])
        ->and(array_diff_key($after, array_flip(['catalog_attributes', 'catalog_revision', 'updated_at'])))
        ->toBe(array_diff_key($before, array_flip(['catalog_attributes', 'catalog_revision', 'updated_at'])))
        ->and($configurator->fresh()->getAttributes())->toBe($configuratorBefore)
        ->and($product->fresh()->getAttributes())->toBe($productBefore)
        ->and($group->tagValues()->pluck('group_tag_values.id')->all())->toBe([$tag->id]);
});

test('omitted native attribute document or key preserves fresh facts and explicit empty key clears only itself', function () {
    $stored = ['application' => ['A'], 'fluid_type' => ['B']];
    $group = Group::factory()->create(['catalog_attributes' => $stored]);
    $editor = Livewire::test(EditGroup::class, ['record' => $group->id]);
    $data = $editor->get('data');
    unset($data['catalog_attributes']);
    $editor->set('data', $data)->set('data.name', 'Metadata only')->call('save')->assertHasNoFormErrors();
    expect($group->fresh()->catalog_attributes)->toBe($stored);
    $editor->set('data.catalog_attributes', ['application' => []])->call('save')->assertHasNoFormErrors();
    expect($group->fresh()->catalog_attributes)->toBe(['fluid_type' => ['B']]);
});

test('real writer validation rolls back native core settings and revision changes while keeping the draft usable', function () {
    $group = Group::factory()->create(['catalog_attributes' => ['application' => ['Saved']]]);
    $editor = Livewire::test(EditGroup::class, ['record' => $group->id]);
    $before = $group->fresh()->getAttributes();
    $editor->set('data.name', 'Draft metadata')->set('data.catalog_settings.result_settings.cards_per_row', 5)
        ->set('data.catalog_attributes.application', ['Duplicate', ' Duplicate '])->call('save')
        ->assertHasFormErrors(['catalog_attributes.application'])->assertSet('data.name', 'Draft metadata')
        ->assertNotDispatched('catalog-record-saved')->assertNotDispatched('catalog-editor-saved');
    expect($group->fresh()->getAttributes())->toBe($before);
    $editor->set('data.catalog_attributes.application', ['Accepted'])->call('save')->assertHasNoFormErrors()
        ->assertDispatched('catalog-record-saved');
    expect($group->fresh()->name)->toBe('Draft metadata')->and($group->fresh()->result_settings['cards_per_row'])->toBe(5)
        ->and((int) $group->fresh()->catalog_revision)->toBe((int) $before['catalog_revision'] + 1);
});

test('writer failure during native Group creation leaves no partial Group or created event', function () {
    Livewire::test(CreateGroup::class, ['embedded' => true])
        ->fillForm(['name' => 'Not partially created', 'catalog_attributes' => ['application' => ['Same', ' Same ']]])
        ->call('create')->assertHasFormErrors(['catalog_attributes.application'])
        ->assertSet('data.name', 'Not partially created')->assertNotDispatched('catalog-record-created');
    $this->assertDatabaseCount('groups', 0);
});

test('a root document error is visible and rolls back the entire native update', function () {
    $group = Group::factory()->create();
    $before = $group->fresh()->getAttributes();
    $values = array_map(fn ($i): string => str_repeat('界', 198).sprintf('%02d', $i), range(0, 24));
    Livewire::test(EditGroup::class, ['record' => $group->id])->set('data.name', 'Over limit draft')
        ->set('data.catalog_attributes.application', $values)->set('data.catalog_attributes.fluid_type', $values)
        ->call('save')->assertHasFormErrors(['catalog_attributes'])->assertSee(__('catalog.group_attributes.invalid_values'))
        ->assertNotDispatched('catalog-record-saved');
    expect($group->fresh()->getAttributes())->toBe($before);
});

test('malformed saved attributes stay read-only while ordinary metadata saves remain usable', function () {
    $group = Group::factory()->create();
    DB::table('groups')->where('id', $group->id)->update(['catalog_attributes' => '["legacy"]']);
    $editor = Livewire::test(EditGroup::class, ['record' => $group->id]);
    $field = collect($editor->instance()->form->getFlatFields(withHidden: true))
        ->first(fn ($field): bool => $field->getStatePath() === 'data.catalog_attributes.application');
    expect($field->isDisabled())->toBeTrue();
    $editor->assertSee(__('catalog.group_attributes.invalid_stored'))
        ->set('data.name', 'Metadata is still editable')->call('save')->assertHasNoFormErrors();
    expect($group->fresh()->name)->toBe('Metadata is still editable')
        ->and($group->fresh()->getRawOriginal('catalog_attributes'))->toBe('["legacy"]');
});

test('the existing whole-record guard rejects a saved winner and reload refreshes the attribute baseline', function () {
    $group = Group::factory()->create();
    $editor = Livewire::test(EditGroup::class, ['record' => $group->id])->set('data.catalog_attributes.application', ['Draft']);
    $attributes = app(GroupAttributes::class);
    app(SaveGroupAttributes::class)->handle(auth()->user(), $group, ['application' => ['Winner']], $attributes->fingerprint($group));
    $editor->call('save')->assertHasFormErrors(['record'])->assertSet('batchEditorStale', true)
        ->assertSet('data.catalog_attributes.application', ['Draft'])->assertNotDispatched('catalog-record-saved');
    expect($group->fresh()->catalog_attributes)->toBe(['application' => ['Winner']]);
    $editor->call('reloadBatchEditor')->assertSet('batchEditorStale', false)->assertSet('data.catalog_attributes.application', ['Winner'])
        ->set('data.catalog_attributes.application', ['After reload'])->call('save')->assertHasNoFormErrors();
    expect($group->fresh()->catalog_attributes)->toBe(['application' => ['After reload']]);
});

test('a late attribute CAS denial rolls back native edits and uses the existing reload banner', function () {
    $group = Group::factory()->create();
    $before = $group->fresh()->getAttributes();
    Livewire::test(GroupAttributesLateCasEditPage::class, ['record' => $group->id])->set('data.name', 'Late stale draft')
        ->set('data.catalog_attributes.application', ['Unsaved'])->call('save')
        ->assertHasFormErrors(['record'])->assertSet('batchEditorStale', true)->assertSee(__('admin.workspace.copy.reload_saved_record'))
        ->assertSet('data.name', 'Late stale draft')->assertNotDispatched('catalog-record-saved');
    expect($group->fresh()->getAttributes())->toBe($before);
});

test('attribute editing works in the existing Group drawer and still rechecks access before saving', function () {
    $group = Group::factory()->create();
    $editor = Livewire::test(EditGroup::class, ['record' => $group->id, 'listKey' => 'group-card-properties', 'parentId' => $group->id, 'initialTab' => 'form.group-settings.presentation::data::tab']);
    $editor->set('data.catalog_attributes.application', ['Drawer fact'])->call('save')->assertHasNoFormErrors()
        ->assertDispatched('item-drawer-record-saved')->assertNoRedirect();
    $before = $group->fresh()->getAttributes();
    $this->actingAs(User::factory()->create());
    $editor->call('save')->assertForbidden();
    expect($group->fresh()->getAttributes())->toBe($before);
});

test('the attribute fingerprint is server locked against client replacement', function () {
    $group = Group::factory()->create();
    $editor = Livewire::test(EditGroup::class, ['record' => $group->id]);
    expect(fn () => $editor->set('groupAttributeFingerprint', 'forged'))->toThrow(CannotUpdateLockedPropertyException::class);
});

class GroupAttributesLateCasEditPage extends EditGroup
{
    protected function beforeSave(): void
    {
        parent::beforeSave();
        $this->groupAttributeFingerprint = str_repeat('f', 64);
    }
}
