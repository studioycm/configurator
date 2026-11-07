<?php

use App\Actions\SaveConfiguratorDefinition;
use App\Filament\Resources\Configurators\Pages\EditConfigurator;
use App\Filament\Resources\Configurators\RelationManagers\RulesRelationManager;
use App\Filament\Resources\Configurators\Schemas\RuleValidationSummary;
use App\Models\User;
use App\Services\ConfiguratorDefinitionLoader;
use Filament\Forms\Components\Field;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

require_once dirname(__DIR__, 2).'/ConfiguratorFixtures.php';

function assertRuleErrorTarget(Testable $editor, string $path): void
{
    $field = collect($editor->instance()->editorForm->getFlatFields())->first(fn (Field $field): bool => $field->getStatePath() === $path);
    expect($field)->not->toBeNull();
    $editor->assertSeeHtml('data-rule-error-target="'.e($field->getId()).'"');
}

function ruleNavigationFixture(array $conditions, int $effects = 1): array
{
    test()->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    [$owner, $data] = canonicalDefinitionFixture();
    $data['context_schema']['territory'] = [['value' => 'EU', 'label' => 'Europe']];
    $rule = fixtureAdvanced('Navigation', $conditions);
    if ($effects === 2) {
        $rule['effects'][] = [...$rule['effects'][0], 'id' => 'new:other-effect'];
    }
    $data['rules'] = [$rule];
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $owner, $data);
    $saved = $owner->rules()->sole();
    $editor = Livewire::test(RulesRelationManager::class, ['ownerRecord' => $owner, 'pageClass' => EditConfigurator::class])->call('selectRule', $saved->id);

    return [$owner, $editor];
}

test('compiler errors target the actual displayed condition after row reordering', function (bool $grouped) {
    $conditions = [fixtureCondition('first'), fixtureCondition('second')];
    [$owner, $editor] = ruleNavigationFixture($grouped ? [['id' => 'new:group', 'operator' => 'All', 'conditions' => $conditions]] : $conditions);
    $before = app(ConfiguratorDefinitionLoader::class)->draft($owner->fresh());
    $blocks = $editor->get('editorData.condition_blocks');
    if ($grouped) {
        $block = array_key_first($blocks);
        $rows = array_reverse($blocks[$block]['data']['conditions'], preserve_keys: true);
        $editor->set('editorData.condition_blocks.'.$block.'.data.conditions', $rows);
        $path = 'editorData.condition_blocks.'.$block.'.data.conditions.'.array_keys($rows)[1];
    } else {
        $blocks = array_reverse($blocks, preserve_keys: true);
        $editor->set('editorData.condition_blocks', $blocks);
        $path = 'editorData.condition_blocks.'.array_keys($blocks)[1].'.data';
    }
    $draft = $editor->get('editorData');

    $editor->set($path.'.option_ids', [])->call('saveEditor')->assertHasErrors([$path.'.option_ids']);

    expect(app(ConfiguratorDefinitionLoader::class)->draft($owner->fresh()))->toBe($before);
    expect($editor->get('editorData.label'))->toBe($draft['label']);
    assertRuleErrorTarget($editor, $path.'.option_ids');
})->with(['root' => false, 'nested' => true]);

test('compiler operand errors target the visible single or list field', function (string $operator, mixed $operand, string $field, mixed $invalid, bool $grouped) {
    $context = ['id' => 'new:context', 'source_kind' => 'Territory', 'source_configurator_attribute_id' => null, 'property_key' => null, 'context_dimension' => 'territory', 'operator' => $operator, 'operand' => $operand, 'option_ids' => []];
    [$owner, $editor] = ruleNavigationFixture($grouped ? [['id' => 'new:group', 'operator' => 'Any', 'conditions' => [$context]]] : [$context]);
    $blocks = $editor->get('editorData.condition_blocks');
    $block = array_key_first($blocks);
    $path = 'editorData.condition_blocks.'.$block.'.data';
    if ($grouped) {
        $path .= '.conditions.'.array_key_first($blocks[$block]['data']['conditions']);
    }
    $before = app(ConfiguratorDefinitionLoader::class)->draft($owner->fresh());

    $editor->set($path.'.'.$field, $invalid)->call('saveEditor')->assertHasErrors([$path.'.'.$field]);

    expect(app(ConfiguratorDefinitionLoader::class)->draft($owner->fresh()))->toBe($before);
    assertRuleErrorTarget($editor, $path.'.'.$field);
})->with([
    'single root' => ['Equals', 'EU', 'operand_text', null, false],
    'single nested' => ['Equals', 'EU', 'operand_text', null, true],
    'list root' => ['In', ['EU'], 'operand_list', [], false],
    'list nested' => ['In', ['EU'], 'operand_list', [], true],
]);

test('a source transition error identifies the second nested condition without losing inputs', function () {
    $context = ['id' => 'new:context', 'source_kind' => 'Territory', 'source_configurator_attribute_id' => null, 'property_key' => null, 'context_dimension' => 'territory', 'operator' => 'Equals', 'operand' => 'EU', 'option_ids' => []];
    [$owner, $editor] = ruleNavigationFixture([['id' => 'new:group', 'operator' => 'All', 'conditions' => [fixtureCondition('first'), $context]]]);
    $blocks = $editor->get('editorData.condition_blocks');
    $block = array_key_first($blocks);
    $path = 'editorData.condition_blocks.'.$block.'.data.conditions.'.array_keys($blocks[$block]['data']['conditions'])[1];
    $before = app(ConfiguratorDefinitionLoader::class)->draft($owner->fresh());

    $editor->set($path.'.operator', 'In')->set($path.'.operand_list', ['EU'])->call('saveEditor')->assertHasErrors([$path]);

    expect($editor->get($path.'.operand_text'))->toBe('EU');
    expect($editor->get($path.'.operand_list'))->toBe(['EU']);
    assertRuleErrorTarget($editor, $path.'.operand_text');
    expect(app(ConfiguratorDefinitionLoader::class)->draft($owner->fresh()))->toBe($before);
});

test('identical errors on two effects retain two contextual navigation targets', function () {
    [$owner, $editor] = ruleNavigationFixture([], effects: 2);
    $keys = array_keys($editor->get('editorData.effects'));

    $editor->set('editorData.effects.'.$keys[0].'.kind', null)->set('editorData.effects.'.$keys[1].'.kind', null)->call('saveEditor');

    $editor->assertHasErrors(['editorData.effects.'.$keys[0].'.kind' => 'required', 'editorData.effects.'.$keys[1].'.kind' => 'required']);
    $editor->assertSee('Effect 1')->assertSee('Effect 2');
    expect(substr_count($editor->html(), 'data-rule-error-target='))->toBe(2);
    foreach ($keys as $key) {
        assertRuleErrorTarget($editor, 'editorData.effects.'.$key.'.kind');
    }
});

test('Rule summaries retain general failures safely without linking hidden fields or unrelated schemas', function () {
    [$owner, $editor] = ruleNavigationFixture([]);
    $entries = RuleValidationSummary::entries($editor->instance()->editorForm, [
        'editorData.save' => ['Save failed. Your draft remains available.'],
        'editorData.ruleConflict' => ['Refresh explicitly.'],
        'editorData.id' => ['Hidden identity is invalid.'],
        'mountedActions.0.data.draft.label' => ['Other schema'],
    ]);

    expect(array_column($entries, 'target'))->toBe([null, null, null]);
    expect(array_column($entries, 'path'))->toBe(['editorData.save', 'editorData.ruleConflict', 'editorData.id']);
    $entries[0]['messages'] = ['<script>alert(1)</script>'];
    $html = view('filament.forms.rule-validation-summary', ['entries' => $entries])->render();
    expect($html)->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')->not->toContain('<script>');
});

test('Rule summary targets a button group through its rendered label', function () {
    [$owner, $editor] = ruleNavigationFixture([]);
    $key = array_key_first($editor->get('editorData.effects'));
    $path = 'editorData.effects.'.$key.'.target_scope';

    $editor->set($path, null)->call('saveEditor')->assertHasErrors([$path => 'required']);

    assertRuleErrorTarget($editor, $path);
    $field = collect($editor->instance()->editorForm->getFlatFields())->first(fn (Field $field): bool => $field->getStatePath() === $path);
    $editor->assertSeeHtml('id="'.e($field->getId()).'-label"');
});

test('Rule mapping errors retain their custom field label anchor', function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    [$owner, $data] = canonicalDefinitionFixture();
    $data['rules'] = [fixtureMapping()];
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $owner, $data);
    $editor = Livewire::test(RulesRelationManager::class, ['ownerRecord' => $owner, 'pageClass' => EditConfigurator::class])->call('selectRule', $owner->rules()->sole()->id);

    $editor->set('editorData.sets', [])->call('saveEditor')->assertHasErrors(['editorData.sets' => 'required']);

    assertRuleErrorTarget($editor, 'editorData.sets');
    $field = collect($editor->instance()->editorForm->getFlatFields())->first(fn (Field $field): bool => $field->getStatePath() === 'editorData.sets');
    $editor->assertSeeHtml('for="'.e($field->getId()).'"');
});
