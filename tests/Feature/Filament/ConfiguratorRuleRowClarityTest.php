<?php

use App\Actions\SaveConfiguratorDefinition;
use App\Filament\Resources\Configurators\Pages\EditConfigurator;
use App\Filament\Resources\Configurators\RelationManagers\RulesRelationManager;
use App\Models\User;
use App\Services\ConfiguratorDefinitionLoader;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Repeater;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

require_once dirname(__DIR__, 2).'/ConfiguratorFixtures.php';

function clarityRows(Testable $editor, string $path): Repeater
{
    return collect($editor->instance()->editorForm->getFlatFields())->first(fn ($field): bool => $field instanceof Repeater && $field->getStatePath() === $path);
}

function clarityRuleEditor(string $kind = 'Advanced'): array
{
    test()->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    [$owner, $data] = canonicalDefinitionFixture();
    $conditions = [['id' => 'new:group', 'operator' => 'All', 'conditions' => [fixtureCondition('first'), fixtureCondition('second', 'A', 'A1')]]];
    $rule = $kind === 'Mapping' ? [...fixtureMapping(), 'conditions' => $conditions] : fixtureAdvanced('Clarity', $conditions);
    if ($kind === 'Advanced') {
        $rule['effects'][] = [...$rule['effects'][0], 'id' => 'new:other-effect', 'kind' => 'SetHint', 'display_value' => 'Original hint'];
    }
    $data['rules'] = [$rule];
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $owner, $data);
    $editor = Livewire::test(RulesRelationManager::class, ['ownerRecord' => $owner, 'pageClass' => EditConfigurator::class])->call('selectRule', $owner->rules()->sole()->id);
    $block = array_key_first($editor->get('editorData.condition_blocks'));

    return [$owner, $editor, 'editorData.condition_blocks.'.$block.'.data.conditions'];
}

test('nested Rule row headings and existing actions identify each condition in both Rule kinds', function (string $kind) {
    [$owner, $editor, $path] = clarityRuleEditor($kind);
    $before = app(ConfiguratorDefinitionLoader::class)->draft($owner->fresh());
    $rows = clarityRows($editor, $path);
    $keys = array_keys($editor->get($path));

    $label = $rows->getItemLabel($keys[1], 1);

    expect($label)->toBe('Nested condition 2 · Selected value · A · Equals');
    foreach (['getDeleteAction' => 'Remove'] as $method => $operation) {
        $action = $rows->{$method}()->arguments(['item' => $keys[1]]);
        expect($action->getLabel())->toBe($operation.' '.$label);
        expect($action->getTooltip())->toBe($operation.' '.$label);
    }
    expect($rows->isReorderableWithButtons())->toBeFalse();
    expect($rows->isReorderableWithDragAndDrop())->toBeTrue();
    expect(app(ConfiguratorDefinitionLoader::class)->draft($owner->fresh()))->toBe($before);
})->with(['Advanced', 'Mapping']);

test('Advanced effect headings show type target and scope and renumber without changing saved order', function () {
    [$owner, $editor] = clarityRuleEditor();
    $before = app(ConfiguratorDefinitionLoader::class)->draft($owner->fresh());
    $path = 'editorData.effects';
    $keys = array_keys($editor->get($path));
    $rows = clarityRows($editor, $path);

    expect($rows->getItemLabel($keys[1], 1))->toBe('Effect 2 · Set hint · B · Whole Attribute');
    $reversed = array_reverse($editor->get($path), preserve_keys: true);
    $editor->set($path, $reversed);
    $rows = clarityRows($editor, $path);
    expect($rows->getItemLabel($keys[1], 0))->toBe('Effect 1 · Set hint · B · Whole Attribute');
    $action = $rows->getDeleteAction()->arguments(['item' => $keys[1]]);
    expect($action->getLabel())->toBe('Remove Effect 1 · Set hint · B · Whole Attribute');
    expect(app(ConfiguratorDefinitionLoader::class)->draft($owner->fresh()))->toBe($before);

    $editor->call('saveEditor')->assertHasNoErrors();
    $after = app(ConfiguratorDefinitionLoader::class)->draft($owner->fresh());
    expect($after['rules'][0]['effects'])->toBe($before['rules'][0]['effects']);
    expect($after['rules'][0]['conditions'])->toBe($before['rules'][0]['conditions']);
});

test('blank and unavailable row references remain readable and retain raw draft values', function () {
    [$owner, $editor, $path] = clarityRuleEditor();
    $key = array_key_first($editor->get($path));
    $editor->set($path.'.'.$key.'.source_configurator_attribute_id', '999999');
    expect(clarityRows($editor, $path)->getItemLabel($key, 0))->toContain('Nested condition 1', 'Unavailable Attribute #999999');
    expect($editor->get($path.'.'.$key.'.source_configurator_attribute_id'))->toBe('999999');

    $editor->set($path.'.'.$key.'.source_kind', null);
    expect(clarityRows($editor, $path)->getItemLabel($key, 0))->toBe('Nested condition 1 · Incomplete condition');
    $effect = array_key_first($editor->get('editorData.effects'));
    $editor->set('editorData.effects.'.$effect.'.kind', null)->set('editorData.effects.'.$effect.'.target_configurator_attribute_id', null);
    expect(clarityRows($editor, 'editorData.effects')->getItemLabel($effect, 0))->toContain('Effect 1', 'Choose effect', 'Choose Attribute');
});

test('removing a nested row stays in the draft and renumbers its remaining heading and action', function () {
    [$owner, $editor, $path] = clarityRuleEditor();
    $before = app(ConfiguratorDefinitionLoader::class)->draft($owner->fresh());
    $rows = clarityRows($editor, $path);
    $keys = array_keys($editor->get($path));

    $editor->callAction(TestAction::make('delete')->schemaComponent(substr($rows->getKey(), strlen('editorForm.')), schema: 'editorForm')->arguments(['item' => $keys[0]]));

    $rows = clarityRows($editor, $path);
    expect(array_keys($editor->get($path)))->toBe([$keys[1]]);
    expect($rows->getItemLabel($keys[1], 0))->toBe('Nested condition 1 · Selected value · A · Equals');
    expect(app(ConfiguratorDefinitionLoader::class)->draft($owner->fresh()))->toBe($before);
});

test('nested property and operator changes update headings without clearing old input', function () {
    [$owner, $editor, $path] = clarityRuleEditor();
    $key = array_key_first($editor->get($path));
    $references = $editor->get($path.'.'.$key.'.option_ids');

    $editor->set($path.'.'.$key.'.source_kind', 'ProductProperty')->set($path.'.'.$key.'.property_key', 'Future_Property')->set($path.'.'.$key.'.operator', 'Contains');

    expect(clarityRows($editor, $path)->getItemLabel($key, 0))->toBe('Nested condition 1 · Product property · Future_Property · Contains');
    expect($editor->get($path.'.'.$key.'.option_ids'))->toBe($references);
    $effect = array_key_first($editor->get('editorData.effects'));
    $editor->set('editorData.effects.'.$effect.'.target_configurator_attribute_id', '999999')->set('editorData.effects.'.$effect.'.target_scope', 'Options');
    expect(clarityRows($editor, 'editorData.effects')->getItemLabel($effect, 0))->toContain('Unavailable Attribute #999999', 'Selected values');
});

test('native drag handles mount the row label component without per-item action arguments', function () {
    [$owner, $editor, $path] = clarityRuleEditor();

    expect(substr_count($editor->html(), 'ruleRowDragLabel('))->toBe(4);
    expect($editor->html())->toContain('Nested condition 1', 'Nested condition 2', 'Effect 1', 'Effect 2');
});
