<?php

use App\Actions\SaveConfiguratorDefinition;
use App\Filament\Resources\Configurators\Pages\EditConfigurator;
use App\Filament\Resources\Configurators\RelationManagers\AttributesRelationManager;
use App\Filament\Resources\Configurators\RelationManagers\RulesRelationManager;
use App\Livewire\Catalog\ConfiguratorOverview;
use App\Models\Configurator;
use App\Models\User;
use App\Services\ConfiguratorDefinitionLoader;
use App\Services\ConfiguratorEditorChanges;
use App\Services\ConfiguratorRuleDraft;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

require_once dirname(__DIR__).'/ConfiguratorFixtures.php';

beforeEach(function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
});

function editorChangesFixture(): array
{
    [$configurator, $data] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $data);

    return [$configurator, app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh())];
}

test('opening conflict Rules preserves saved Rule edits and the exact unsaved new Rule', function () {
    [$configurator, $saved] = editorChangesFixture();
    $first = $configurator->rules()->create(['label' => 'First Rule', 'kind' => 'Advanced', 'is_active' => true, 'priority' => 0]);
    $second = $configurator->rules()->create(['label' => 'Second Rule', 'kind' => 'Advanced', 'is_active' => true, 'priority' => 1]);
    $manager = Livewire::test(RulesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->call('createRule', 'Advanced')->fillForm(['label' => 'Unsaved new Rule'], 'editorForm');
    $newId = $manager->get('editorData.id');

    $manager->call('openPreviewSource', (string) $first->id)->assertReturned(true)
        ->fillForm(['label' => 'Pending first Rule'], 'editorForm')
        ->call('openPreviewSource', (string) $second->id)->assertReturned(true)
        ->fillForm(['label' => 'Pending second Rule'], 'editorForm')
        ->call('openPreviewSource', (string) $first->id)->assertReturned(true)
        ->assertSet('editorData.label', 'Pending first Rule')
        ->call('openPreviewSource', $newId)->assertReturned(true)
        ->assertSet('selectedRuleId', null)->assertSet('editorData.id', $newId)
        ->assertSet('editorData.label', 'Unsaved new Rule');

    expect($first->fresh()->label)->toBe('First Rule');
    expect($second->fresh()->label)->toBe('Second Rule');
    expect($configurator->rules()->count())->toBe(2);
});

test('opening conflict Values preserves both Attribute drafts and all other Value drafts without saving', function () {
    [$configurator, $saved] = editorChangesFixture();
    [$a, $b] = $saved['attributes'];
    $manager = Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->call('selectAttribute', (int) $a['id'])->fillForm(['help_text' => 'Pending A'], 'editorForm')
        ->call('selectOption', (int) $a['id'], (int) $a['options'][0]['id'])->fillForm(['hint' => 'Pending A0'], 'optionEditorForm')
        ->call('selectAttribute', (int) $b['id'])->fillForm(['help_text' => 'Pending B'], 'editorForm');

    $manager->call('openPreviewSource', (int) $a['id'], (int) $a['options'][1]['id'])->assertReturned(true)
        ->assertSet('editorData.help_text', 'Pending A')
        ->fillForm(['hint' => 'Pending A1'], 'optionEditorForm')
        ->call('openPreviewSource', (int) $a['id'], (int) $a['options'][0]['id'])->assertReturned(true)
        ->assertSet('optionEditorData.hint', 'Pending A0')
        ->call('openPreviewSource', (int) $b['id'], (int) $b['options'][0]['id'])->assertReturned(true)
        ->assertSet('editorData.help_text', 'Pending B')
        ->call('openPreviewSource', (int) $a['id'], (int) $a['options'][1]['id'])->assertReturned(true)
        ->assertSet('optionEditorData.hint', 'Pending A1');

    expect(app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh()))->toBe($saved);
});

test('unavailable or foreign conflict sources leave the current unsaved editor untouched', function () {
    [$configurator, $saved] = editorChangesFixture();
    $foreign = Configurator::factory()->create()->rules()->create(['label' => 'Foreign Rule', 'kind' => 'Advanced', 'is_active' => true, 'priority' => 0]);
    $rules = Livewire::test(RulesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->call('createRule', 'Advanced')->fillForm(['label' => 'Keep new Rule'], 'editorForm');
    $rules->call('openPreviewSource', (string) $foreign->id)->assertReturned(false)
        ->call('openPreviewSource', 'new:missing')->assertReturned(false)->assertSet('editorData.label', 'Keep new Rule');
    [$a, $b] = $saved['attributes'];
    Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->call('selectAttribute', (int) $a['id'])->fillForm(['help_text' => 'Keep Attribute draft'], 'editorForm')
        ->call('openPreviewSource', (int) $a['id'], (int) $b['options'][0]['id'])->assertReturned(false)
        ->assertSet('selectedAttributeId', (int) $a['id'])->assertSet('editorData.help_text', 'Keep Attribute draft');
});

test('conflict source navigation requires catalog management access', function () {
    [$configurator, $saved] = editorChangesFixture();
    $rules = Livewire::test(RulesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class]);
    $values = Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class]);
    $this->actingAs(User::factory()->create(['email' => 'unauthorized@example.test']));

    $rules->call('openPreviewSource', '1')->assertForbidden();
    $values->call('openPreviewSource', (int) $saved['attributes'][0]['id'], (int) $saved['attributes'][0]['options'][0]['id'])->assertForbidden();
});

test('editor changes overlay all edited fields while preserving saved structure without writes', function () {
    [$configurator, $saved] = editorChangesFixture();
    $attribute = $saved['attributes'][0];
    $value = $attribute['options'][0];
    $changes = app(ConfiguratorEditorChanges::class);
    $capture = $changes->capture($configurator->id, [
        ['type' => 'attribute', 'id' => $attribute['id'], 'before' => $attribute, 'after' => [...$attribute, 'label_override' => 'Temporary body']],
        ['type' => 'value', 'id' => $value['id'], 'before' => $value, 'after' => [...$value, 'hint' => 'Temporary guidance']],
        ['type' => 'overview', 'id' => 'overview', 'before' => $saved, 'after' => [...$saved, 'description' => 'Temporary description']],
    ]);

    $prepared = $changes->prepare($configurator, [$capture]);

    expect($prepared['data']['attributes'][0]['label_override'])->toBe('Temporary body');
    expect($prepared['data']['attributes'][0]['options'][0]['hint'])->toBe('Temporary guidance');
    expect($prepared['data']['description'])->toBe('Temporary description');
    expect($prepared['changes'])->toHaveCount(3);
    expect(app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh()))->toBe($saved);
});

test('editor capture signatures reject tampering and another configurator', function () {
    [$configurator, $saved] = editorChangesFixture();
    $changes = app(ConfiguratorEditorChanges::class);
    $capture = $changes->capture($configurator->id, [['type' => 'overview', 'id' => 'overview', 'before' => $saved, 'after' => [...$saved, 'name' => 'Temporary']]]);
    $capture['rows'][0]['after']['name'] = 'Forged';

    expect(fn () => $changes->prepare($configurator, [$capture]))->toThrow(ValidationException::class);
});

test('changed saved fields reject stale edits but immediate ordering remains intact', function () {
    [$configurator, $saved] = editorChangesFixture();
    $attribute = $saved['attributes'][0];
    $changes = app(ConfiguratorEditorChanges::class);
    $capture = $changes->capture($configurator->id, [['type' => 'attribute', 'id' => $attribute['id'], 'before' => $attribute, 'after' => [...$attribute, 'label_override' => 'Temporary']]]);
    $configurator->attributes()->findOrFail($attribute['id'])->update(['display_order' => 9]);

    $prepared = $changes->prepare($configurator->fresh(), [$capture]);
    expect(collect($prepared['data']['attributes'])->firstWhere('id', $attribute['id'])['display_order'])->toBe(9);
    $configurator->attributes()->findOrFail($attribute['id'])->update(['label_override' => 'Other saved edit']);

    expect(fn () => $changes->prepare($configurator->fresh(), [$capture]))->toThrow(ValidationException::class);
});

test('new Mapping and Advanced rule edits get unique priorities and remain unsaved', function () {
    [$configurator, $saved] = editorChangesFixture();
    $a = $saved['attributes'][0];
    $b = $saved['attributes'][1];
    $mapping = fixtureMapping('pending-map');
    $mapping['driver_configurator_attribute_id'] = $a['id'];
    $mapping['target_configurator_attribute_id'] = $b['id'];
    $mapping['sets'][0]['source_option_ids'] = [$a['options'][0]['id']];
    $mapping['sets'][0]['target_option_ids'] = [$b['options'][1]['id']];
    $advanced = fixtureAdvanced('pending-advanced', [], 'B', 'SetHint', [], 'Pending explanation');
    $advanced['effects'][0]['target_configurator_attribute_id'] = $b['id'];
    $changes = app(ConfiguratorEditorChanges::class);
    $capture = $changes->capture($configurator->id, array_map(fn (array $rule): array => ['type' => 'rule', 'id' => $rule['id'], 'before' => [], 'after' => app(ConfiguratorRuleDraft::class)->fromRule($rule)], [$mapping, $advanced]));

    $prepared = $changes->prepare($configurator, [$capture]);

    expect(array_column($prepared['data']['rules'], 'priority'))->toBe([0, 1]);
    expect(array_column($prepared['data']['rules'], 'label'))->toBe(['pending-map', 'pending-advanced']);
    expect($configurator->rules()->count())->toBe(0);
});

test('editor exports combine active and retained Attribute and Value edits without saving', function () {
    [$configurator, $saved] = editorChangesFixture();
    $a = $saved['attributes'][0];
    $b = $saved['attributes'][1];
    $manager = Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->call('selectAttribute', (int) $a['id'])->fillForm(['label_override' => 'Pending A'], 'editorForm')
        ->call('selectOption', (int) $a['id'], (int) $a['options'][0]['id'])->fillForm(['hint' => 'Pending Value'], 'optionEditorForm')
        ->call('selectAttribute', (int) $b['id'])->fillForm(['help_text' => 'Pending B'], 'editorForm');
    $capture = null;

    $manager->call('exportPreviewChanges')->assertReturned(function (array $result) use (&$capture): bool {
        $capture = $result;

        return true;
    });
    $prepared = app(ConfiguratorEditorChanges::class)->prepare($configurator->fresh(), [$capture]);

    expect($prepared['changes'])->toHaveCount(3);
    expect($prepared['data']['attributes'][0]['label_override'])->toBe('Pending A');
    expect($prepared['data']['attributes'][0]['options'][0]['hint'])->toBe('Pending Value');
    expect($prepared['data']['attributes'][1]['help_text'])->toBe('Pending B');
    expect(app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh()))->toBe($saved);
});

test('Overview and new Rule exports preserve current unsaved form data without validation side effects', function () {
    [$configurator, $saved] = editorChangesFixture();
    $overview = Livewire::test(ConfiguratorOverview::class, ['configuratorId' => $configurator->id])->fillForm(['description' => 'Pending Overview']);
    $rule = Livewire::test(RulesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])->call('createRule', 'Advanced')->fillForm(['label' => 'Incomplete new rule'], 'editorForm');

    $overview->call('exportPreviewChanges')->assertReturned(fn (array $result): bool => $result['rows'][0]['after']['description'] === 'Pending Overview');
    $rule->call('exportPreviewChanges')->assertReturned(fn (array $result): bool => $result['rows'][0]['after']['label'] === 'Incomplete new rule');

    expect($configurator->fresh()->description)->toBeNull();
    expect($configurator->rules()->count())->toBe(0);
});

test('a fresh Rule editor has no pending changes after discarding another Rule', function () {
    [$configurator] = editorChangesFixture();
    Livewire::test(RulesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->call('createRule', 'Mapping')->fillForm(['label' => 'Discarded rule'], 'editorForm')
        ->call('closeEditor')->call('createRule', 'Mapping')->call('exportPreviewChanges')
        ->assertReturned(fn (array $capture): bool => $capture['rows'] === []);
});

test('a fresh Rule editor has no pending changes after saving another Rule', function () {
    [$configurator, $saved] = editorChangesFixture();
    $rule = fixtureAdvanced('saved-rule', [], 'C', 'SetHint', [], 'Saved hint');
    $rule['effects'][0]['target_configurator_attribute_id'] = $saved['attributes'][2]['id'];
    Livewire::test(RulesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->call('createRule', 'Advanced')->fillForm(app(ConfiguratorRuleDraft::class)->fromRule($rule), 'editorForm')
        ->call('saveEditor')->assertHasNoErrors()->call('createRule', 'Advanced')->call('exportPreviewChanges')
        ->assertReturned(fn (array $capture): bool => $capture['rows'] === []);
});

test('saved preview changes clear matching editor buffers and preserve edits made after collection', function () {
    [$configurator, $saved] = editorChangesFixture();
    $a = $saved['attributes'][0];
    $manager = Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->call('selectAttribute', (int) $a['id'])->fillForm(['label_override' => 'Tested label'], 'editorForm');
    $capture = null;
    $manager->call('exportPreviewChanges')->assertReturned(function (array $result) use (&$capture): bool {
        $capture = $result;

        return true;
    });
    $prepared = app(ConfiguratorEditorChanges::class)->prepare($configurator->fresh(), [$capture]);
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $prepared['data']);
    $manager->fillForm(['help_text' => 'Typed after collection'], 'editorForm');

    $manager->dispatch('configurator-preview-saved', configuratorId: $configurator->id, captures: [$capture], newRules: [])
        ->call('exportPreviewChanges')->assertReturned(function (array $result) use (&$capture): bool {
            $capture = $result;

            return true;
        });
    $remaining = app(ConfiguratorEditorChanges::class)->prepare($configurator->fresh(), [$capture]);

    expect($remaining['changes'])->toHaveCount(1);
    expect(array_keys($remaining['changes'][0]['fields']))->toBe(['help_text']);
    expect($manager->get('editorData.label_override'))->toBe('Tested label');
});

test('confirmation describes nested Rule changes with Attribute names and Value codes', function () {
    [$configurator, $saved] = editorChangesFixture();
    $a = $saved['attributes'][0];
    $b = $saved['attributes'][1];
    $mapping = fixtureMapping('review-map');
    $mapping['driver_configurator_attribute_id'] = $a['id'];
    $mapping['target_configurator_attribute_id'] = $b['id'];
    $mapping['sets'][0]['source_option_ids'] = [$a['options'][0]['id']];
    $mapping['sets'][0]['target_option_ids'] = [$b['options'][1]['id']];
    $changes = app(ConfiguratorEditorChanges::class);
    $capture = $changes->capture($configurator->id, [['type' => 'rule', 'id' => $mapping['id'], 'before' => [], 'after' => app(ConfiguratorRuleDraft::class)->fromRule($mapping)]]);

    $prepared = $changes->prepare($configurator->fresh(), [$capture]);

    expect($prepared['changes'][0]['fields']['driver_configurator_attribute_id']['after_text'])->toBe('A');
    expect($prepared['changes'][0]['fields']['sets']['after_text'])->toContain('A0', 'B1', 'Driver values', 'Allowed values');
});

test('Overview can test a second edit after accepting a preview save', function () {
    [$configurator, $saved] = editorChangesFixture();
    $manager = Livewire::test(ConfiguratorOverview::class, ['configuratorId' => $configurator->id])->fillForm(['description' => 'First save']);
    $capture = null;
    $manager->call('exportPreviewChanges')->assertReturned(function (array $result) use (&$capture): bool {
        $capture = $result;

        return true;
    });
    $prepared = app(ConfiguratorEditorChanges::class)->prepare($configurator->fresh(), [$capture]);
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $prepared['data']);
    $manager->dispatch('configurator-preview-saved', configuratorId: $configurator->id, captures: [$capture], newRules: []);
    $manager->fillForm(['description' => 'Second save']);

    $manager->call('exportPreviewChanges')->assertReturned(function (array $result) use (&$capture): bool {
        $capture = $result;

        return true;
    });
    $prepared = app(ConfiguratorEditorChanges::class)->prepare($configurator->fresh(), [$capture]);

    expect($prepared['data']['description'])->toBe('Second save');
    expect($prepared['changes'])->toHaveCount(1);
});

test('new Rule confirmation uses UI labels and omits irrelevant empty fields', function () {
    [$configurator, $saved] = editorChangesFixture();
    $rule = fixtureAdvanced('readable-hint', [], 'B', 'SetHint', [], 'Helpful note');
    $rule['effects'][0]['target_configurator_attribute_id'] = $saved['attributes'][1]['id'];
    $capture = app(ConfiguratorEditorChanges::class)->capture($configurator->id, [['type' => 'rule', 'id' => $rule['id'], 'before' => [], 'after' => $rule]]);

    $fields = app(ConfiguratorEditorChanges::class)->prepare($configurator, [$capture])['changes'][0]['fields'];

    expect($fields)->not->toHaveKeys(['driver_configurator_attribute_id', 'target_configurator_attribute_id', 'sets']);
    expect($fields['effects']['after_text'])->toContain('Set hint', 'Whole Attribute')->not->toContain('SetHint', 'Values: —');
});

test('Rule reconciliation preserves a separately saved disable while retaining later label edits', function (bool $buffered) {
    [$configurator, $data] = canonicalDefinitionFixture();
    $data['rules'][] = fixtureAdvanced('original-rule', [], 'C', 'SetHint', [], 'Original hint');
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $data);
    $rule = $configurator->rules()->sole();
    $manager = Livewire::test(RulesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->call('selectRule', $rule->id)->fillForm(['label' => 'Tested label'], 'editorForm');
    $capture = null;
    $manager->call('exportPreviewChanges')->assertReturned(function (array $result) use (&$capture): bool {
        $capture = $result;

        return true;
    });
    $rule->update(['is_active' => false]);
    $changes = app(ConfiguratorEditorChanges::class);
    $prepared = $changes->prepare($configurator->fresh(), [$capture]);
    expect($prepared['data']['rules'][0]['is_active'])->toBeFalse();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $prepared['data']);
    $manager->fillForm(['label' => 'Typed after collection'], 'editorForm');
    if ($buffered) {
        $manager->call('createRule', 'Mapping');
    }

    $manager->dispatch('configurator-preview-saved', configuratorId: $configurator->id, captures: [$capture], newRules: [])
        ->call('exportPreviewChanges')->assertReturned(function (array $result) use (&$capture): bool {
            $capture = $result;

            return true;
        });
    $remaining = $changes->prepare($configurator->fresh(), [$capture]);

    expect(array_keys($remaining['changes'][0]['fields']))->toBe(['label']);
    expect($remaining['data']['rules'][0]['is_active'])->toBeFalse();
    $manager->call('selectRule', $rule->id)
        ->assertSchemaStateSet(['label' => 'Typed after collection', 'is_active' => false], 'editorForm');
})->with(['open Rule' => false, 'buffered Rule' => true]);

test('Overview keeps a separately saved name while retaining a later description edit', function () {
    [$configurator, $data] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $data);
    $manager = Livewire::test(ConfiguratorOverview::class, ['configuratorId' => $configurator->id])->fillForm(['description' => 'Tested description']);
    $capture = null;
    $manager->call('exportPreviewChanges')->assertReturned(function (array $result) use (&$capture): bool {
        $capture = $result;

        return true;
    });
    $configurator->update(['name' => 'Renamed elsewhere']);
    $changes = app(ConfiguratorEditorChanges::class);
    $prepared = $changes->prepare($configurator->fresh(), [$capture]);
    expect($prepared['data']['name'])->toBe('Renamed elsewhere');
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $prepared['data']);
    $manager->fillForm(['description' => 'Typed after collection']);

    $manager->dispatch('configurator-preview-saved', configuratorId: $configurator->id, captures: [$capture], newRules: [])
        ->call('exportPreviewChanges')->assertReturned(function (array $result) use (&$capture): bool {
            $capture = $result;

            return true;
        });
    $remaining = $changes->prepare($configurator->fresh(), [$capture]);

    expect(array_keys($remaining['changes'][0]['fields']))->toBe(['description']);
    expect($remaining['data']['name'])->toBe('Renamed elsewhere');
});

test('inventory retains Overview and Value edits beside an incomplete new Mapping Rule without compiling or writing', function () {
    [$configurator, $saved] = editorChangesFixture();
    $value = $saved['attributes'][0]['options'][0];
    $rule = fixtureMapping('incomplete-map');
    $rule['driver_configurator_attribute_id'] = null;
    $rule['target_configurator_attribute_id'] = null;
    $rule['sets'] = [];
    $changes = app(ConfiguratorEditorChanges::class);
    $capture = $changes->capture($configurator->id, [
        ['type' => 'overview', 'id' => 'overview', 'before' => $saved, 'after' => [...$saved, 'description' => 'Pending Overview']],
        ['type' => 'value', 'id' => $value['id'], 'before' => $value, 'after' => [...$value, 'hint' => 'Pending guidance']],
        ['type' => 'rule', 'id' => $rule['id'], 'before' => [], 'after' => app(ConfiguratorRuleDraft::class)->fromRule($rule)],
    ]);
    DB::enableQueryLog();

    $inventory = $changes->inspect($configurator, [$capture]);

    expect($inventory['changes'])->toHaveCount(3);
    expect(array_column($inventory['changes'], 'type'))->toBe(['overview', 'value', 'rule']);
    expect($inventory['changes'][2])->toMatchArray(['id' => 'new:incomplete-map', 'label' => 'incomplete-map', 'new' => true, 'invalid' => false, 'stale' => false, 'errors' => []]);
    expect($inventory['changes'][0]['fields']['description']['after_text'])->toBe('Pending Overview');
    expect($inventory['errors'])->toBe([]);
    expect(fn () => $changes->prepare($configurator, [$capture]))->toThrow(ValidationException::class, 'incomplete-map');
    expect($changes->inspect($configurator, [$capture]))->toBe($inventory);
    expect(app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh()))->toBe($saved);
    expect(collect(DB::getQueryLog())->pluck('query')->filter(fn (string $query): bool => preg_match('/^\s*(insert|update|delete|replace|alter|create|drop)\b/i', $query) === 1)->all())->toBe([]);
});

test('inventory describes signed raw Rule fields when an incomplete condition cannot normalize', function (bool $keyed) {
    [$configurator, $saved] = editorChangesFixture();
    $rule = app(ConfiguratorRuleDraft::class)->fromRule(fixtureMapping('raw-rule'));
    $rule['condition_blocks'] = [['type' => 'predicate', 'data' => ['source_kind' => 'SelectionOption', 'unsupported_display_field' => 'unexpected nested content']]];
    if ($keyed) {
        $rule['condition_blocks'] = ['editor-block-key' => $rule['condition_blocks'][0]];
    }
    $rule['unsupported_display_field'] = '<script>unexpected()</script>';
    $changes = app(ConfiguratorEditorChanges::class);
    $capture = $changes->capture($configurator->id, [['type' => 'rule', 'id' => $rule['id'], 'before' => [], 'after' => $rule]]);

    $inventory = $changes->inspect($configurator, [$capture]);

    expect($inventory['changes'])->toHaveCount(1);
    expect($inventory['changes'][0])->toMatchArray(['id' => 'new:raw-rule', 'new' => true, 'invalid' => true, 'stale' => false]);
    expect($inventory['changes'][0]['fields'])->toHaveKey('condition_blocks')->not->toHaveKey('unsupported_display_field');
    expect($inventory['changes'][0]['fields']['condition_blocks']['after_text'])->toContain('Selected value')->not->toContain('configurator.preview.fields.');
    expect($inventory['changes'][0]['fields']['condition_blocks']['after_text'])->not->toContain('unexpected nested content');
    expect($inventory['errors'])->toBe($inventory['changes'][0]['errors']);
    expect(implode(' ', $inventory['errors']))->toContain('raw-rule', 'Rule');
    expect(app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh()))->toBe($saved);
})->with(['indexed condition blocks' => false, 'keyed condition blocks' => true]);

test('valid inventory shares preparation normalization and readable descriptions', function () {
    [$configurator, $saved] = editorChangesFixture();
    $a = $saved['attributes'][0];
    $b = $saved['attributes'][1];
    $rule = fixtureMapping('readable-map');
    $rule['driver_configurator_attribute_id'] = $a['id'];
    $rule['target_configurator_attribute_id'] = $b['id'];
    $rule['sets'][0]['source_option_ids'] = [$a['options'][0]['id']];
    $rule['sets'][0]['target_option_ids'] = [$b['options'][1]['id']];
    $changes = app(ConfiguratorEditorChanges::class);
    $capture = $changes->capture($configurator->id, [['type' => 'rule', 'id' => $rule['id'], 'before' => [], 'after' => app(ConfiguratorRuleDraft::class)->fromRule($rule)]]);

    $inventory = $changes->inspect($configurator, [$capture]);
    $prepared = $changes->prepare($configurator, [$capture]);

    expect(Arr::except($inventory['changes'][0], ['invalid', 'stale', 'errors']))->toBe($prepared['changes'][0]);
    expect($inventory['changes'][0]['fields']['sets']['after_text'])->toContain('A0', 'B1', 'Driver values', 'Allowed values');
});

test('inventory omits normalized blanks and fields already saved with the intended value', function () {
    [$configurator, $saved] = editorChangesFixture();
    $value = $saved['attributes'][0]['options'][0];
    $changes = app(ConfiguratorEditorChanges::class);
    $capture = $changes->capture($configurator->id, [
        ['type' => 'overview', 'id' => 'overview', 'before' => $saved, 'after' => [...$saved, 'description' => '']],
        ['type' => 'value', 'id' => $value['id'], 'before' => $value, 'after' => [...$value, 'hint' => 'Already saved']],
    ]);
    $configurator->attributes()->firstOrFail()->options()->findOrFail($value['id'])->update(['hint' => 'Already saved']);

    expect($changes->inspect($configurator->fresh(), [$capture]))->toBe(['changes' => [], 'errors' => []]);
});

test('inventory retains stale owned changes while strict preparation rejects them', function () {
    [$configurator, $saved] = editorChangesFixture();
    $changes = app(ConfiguratorEditorChanges::class);
    $capture = $changes->capture($configurator->id, [['type' => 'overview', 'id' => 'overview', 'before' => $saved, 'after' => [...$saved, 'description' => 'Pending description']]]);
    $configurator->update(['description' => 'Saved by another editor']);

    $inventory = $changes->inspect($configurator->fresh(), [$capture]);

    expect($inventory['changes'][0])->toMatchArray(['type' => 'overview', 'invalid' => false, 'stale' => true]);
    expect($inventory['changes'][0]['fields']['description'])->toMatchArray(['before' => null, 'after' => 'Pending description']);
    expect($inventory['errors'])->toBe($inventory['changes'][0]['errors'])->not->toBeEmpty();
    expect(fn () => $changes->prepare($configurator->fresh(), [$capture]))->toThrow(ValidationException::class);
    expect($configurator->fresh()->description)->toBe('Saved by another editor');
});

test('inventory rejects the entire collection for untrusted envelopes identities and row shapes', function (string $case) {
    [$configurator, $saved] = editorChangesFixture();
    $changes = app(ConfiguratorEditorChanges::class);
    $row = ['type' => 'overview', 'id' => 'overview', 'before' => $saved, 'after' => [...$saved, 'description' => 'Pending description']];
    $rows = [$row];
    if ($case === 'unknown type') {
        $rows[] = [...$row, 'type' => 'unsupported'];
    } elseif ($case === 'invalid id') {
        $rows[] = [...$row, 'type' => 'attribute', 'id' => ['bad']];
    } elseif ($case === 'scalar row') {
        $rows[] = 'bad';
    } elseif ($case === 'invalid before') {
        $rows[] = [...$row, 'type' => 'attribute', 'id' => '1', 'before' => 'bad'];
    } elseif ($case === 'invalid after') {
        $rows[] = [...$row, 'type' => 'attribute', 'id' => '1', 'after' => 'bad'];
    } elseif ($case === 'list before') {
        $rows[] = [...$row, 'type' => 'attribute', 'id' => '1', 'before' => ['bad']];
    } elseif ($case === 'list after') {
        $rows[] = [...$row, 'type' => 'attribute', 'id' => '1', 'after' => ['bad']];
    } elseif ($case === 'too many state fields') {
        $rows[0]['after'] = array_fill_keys(array_map(fn (int $index): string => 'field_'.$index, range(1, 501)), 'value');
    } elseif ($case === 'too deep state') {
        $nested = [];
        for ($depth = 0; $depth < 14; $depth++) {
            $nested = ['nested' => $nested];
        }
        $rows[0]['after']['nested'] = $nested;
    } elseif ($case === 'too many rows') {
        $rows = array_fill(0, 501, $row);
    }
    $capture = $changes->capture($configurator->id, $rows);
    if ($case === 'signature') {
        $capture['rows'][0]['after']['description'] = 'Forged';
    } elseif ($case === 'foreign configurator') {
        $capture = $changes->capture(Configurator::factory()->create()->id, $rows);
    }
    $captures = match ($case) {
        'duplicate identity' => [$capture, $capture],
        'too many captures' => array_fill(0, 4, $capture),
        default => [$capture],
    };

    expect(fn () => $changes->inspect($configurator, $captures))->toThrow(ValidationException::class);
    expect(fn () => $changes->prepare($configurator, $captures))->toThrow(ValidationException::class);
    expect(fn () => $changes->captureHash($configurator->id, $captures))->toThrow(ValidationException::class);
    expect(app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh()))->toBe($saved);
})->with(['signature', 'foreign configurator', 'duplicate identity', 'unknown type', 'invalid id', 'scalar row', 'invalid before', 'invalid after', 'list before', 'list after', 'too many state fields', 'too deep state', 'too many captures', 'too many rows']);

test('inventory rejects a foreign or deleted owner row instead of returning other valid rows', function (bool $deleted) {
    [$configurator, $saved] = editorChangesFixture();
    $other = Configurator::factory()->create();
    $other->attributes()->create(Arr::except($saved['attributes'][0], ['id', 'options', 'default_configurator_option_id']));
    $attribute = $deleted ? $saved['attributes'][0] : app(ConfiguratorDefinitionLoader::class)->draft($other)['attributes'][0];
    $changes = app(ConfiguratorEditorChanges::class);
    $capture = $changes->capture($configurator->id, [
        ['type' => 'overview', 'id' => 'overview', 'before' => $saved, 'after' => [...$saved, 'description' => 'Pending description']],
        ['type' => 'attribute', 'id' => $attribute['id'], 'before' => $attribute, 'after' => [...$attribute, 'help_text' => 'Pending help']],
    ]);
    if ($deleted) {
        $owned = $configurator->attributes()->findOrFail($attribute['id']);
        $owned->update(['default_configurator_option_id' => null]);
        $owned->options()->delete();
        $owned->delete();
    }

    expect(fn () => $changes->inspect($configurator->fresh(), [$capture]))->toThrow(ValidationException::class);
    expect(fn () => $changes->prepare($configurator->fresh(), [$capture]))->toThrow(ValidationException::class);
    expect($configurator->fresh()->description)->toBeNull();
})->with(['foreign owner' => false, 'deleted owner' => true]);

test('capture hash ignores envelope grouping and row ordering while preserving nested order and new Rule identity', function () {
    [$configurator, $saved] = editorChangesFixture();
    $changes = app(ConfiguratorEditorChanges::class);
    $overview = ['type' => 'overview', 'id' => 'overview', 'before' => $saved, 'after' => [...$saved, 'description' => 'Pending description']];
    $rule = fixtureMapping('hash-rule');
    $row = ['type' => 'rule', 'id' => $rule['id'], 'before' => [], 'after' => app(ConfiguratorRuleDraft::class)->fromRule($rule)];
    $hash = $changes->captureHash($configurator->id, [$changes->capture($configurator->id, [$overview, $row])]);

    expect($changes->captureHash($configurator->id, [$changes->capture($configurator->id, [$row]), $changes->capture($configurator->id, [$overview])]))->toBe($hash);
    $row['after']['sets'][0]['source_option_ids'] = ['first', 'second'];
    $firstOrder = $changes->captureHash($configurator->id, [$changes->capture($configurator->id, [$overview, $row])]);
    $row['after']['sets'][0]['source_option_ids'] = ['second', 'first'];
    expect($changes->captureHash($configurator->id, [$changes->capture($configurator->id, [$overview, $row])]))->not->toBe($firstOrder);
    $row['after']['sets'][0]['source_option_ids'] = $rule['sets'][0]['source_option_ids'];
    $row['id'] = $row['after']['id'] = 'new:another-hash-rule';
    expect($changes->captureHash($configurator->id, [$changes->capture($configurator->id, [$overview, $row])]))->not->toBe($hash);
    expect($changes->captureHash($configurator->id, [$changes->capture($configurator->id, [])]))->toBe($changes->captureHash($configurator->id, []));
});
