<?php

use App\Actions\SaveConfiguratorDefinition;
use App\Filament\Pages\DataExchange;
use App\Filament\Resources\Configurators\Pages\EditConfigurator;
use App\Filament\Resources\Configurators\RelationManagers\RulesRelationManager;
use App\Models\Configurator;
use App\Models\RuleEffect;
use App\Models\User;
use App\Services\ConfiguratorRuleExchange;
use Filament\Actions\Testing\TestAction;
use Filament\Schemas\Components\Wizard;
use Illuminate\Http\UploadedFile;
use League\Csv\Writer;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['email' => 'beny.cr@gmail.com']));
});

test('Rules import is available on Data Exchange and the Configurator Rules workspace', function () {
    Livewire::test(DataExchange::class)
        ->assertActionExists(TestAction::make('importRules')->schemaComponent(true, 'content'));

    Livewire::test(RulesRelationManager::class, ['ownerRecord' => Configurator::factory()->create(), 'pageClass' => EditConfigurator::class])
        ->assertActionExists(TestAction::make('importRules')->table());
});

test('a forged final action cannot skip the required Rules preview', function () {
    $configurator = Configurator::factory()->create();

    Livewire::test(DataExchange::class)
        ->callAction(TestAction::make('importRules')->schemaComponent(true, 'content'), [
            'file' => UploadedFile::fake()->createWithContent('rules.csv', 'unused'),
            'configurator_ids' => [$configurator->id],
            'confirm_import' => true,
        ])
        ->assertHasFormErrors(['file']);

    $this->assertDatabaseCount('configurator_rules', 0);
});

require_once dirname(__DIR__).'/ConfiguratorFixtures.php';

function ruleImportActionFile(array $rows): UploadedFile
{
    $writer = Writer::fromString();
    $writer->setEscape('');
    $writer->insertOne(ConfiguratorRuleExchange::COLUMNS);
    $writer->insertAll(array_map(fn (array $row): array => array_values($row), $rows));

    return UploadedFile::fake()->createWithContent('rules.csv', $writer->toString());
}

function ruleImportActionFixture(): array
{
    [$configurator, $draft] = canonicalDefinitionFixture();
    $draft['rules'] = [fixtureAdvanced('existing', [], 'B', 'SetLabel', [], 'Original label')];
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $draft);
    $rule = $configurator->rules()->sole();
    $rows = app(ConfiguratorRuleExchange::class)->snapshot($rule)['rows'];

    return [$configurator, $rule, $rows];
}

function ruleImportActionNext($component, int $index)
{
    $wizard = $component->instance()->getSchema($component->instance()->getMountedActionSchemaName())->getComponent(fn ($component): bool => $component instanceof Wizard);

    return $component->call('callSchemaComponentMethod', $wizard->getKey(), 'nextStep', [$index]);
}

test('the native Rules wizard previews without writes then saves a confirmed Create with safe defaults', function () {
    [$configurator, $existing, $rows] = ruleImportActionFixture();
    $rows[0] = array_replace($rows[0], ['import' => '1', 'operation' => 'Create', 'rule_key' => 'copy', 'rule_id' => '', 'expected_revision' => '', 'priority' => '1', 'rule_label' => '<script>unsafe</script>']);
    $component = Livewire::test(DataExchange::class)
        ->mountAction(TestAction::make('importRules')->schemaComponent(true, 'content'))
        ->fillForm(['file' => ruleImportActionFile($rows), 'configurator_ids' => [$configurator->id]]);

    ruleImportActionNext($component, 0)->assertHasNoFormErrors();
    $previewHtml = $component->instance()->getSchema($component->instance()->getMountedActionSchemaName())->toHtml();
    expect($previewHtml)->toContain('&lt;script&gt;unsafe&lt;/script&gt;')->not->toContain('<script>unsafe</script>');
    $this->assertDatabaseCount('configurator_rules', 1);
    expect($component->get('ruleImportPreview.create_count'))->toBe(1);

    ruleImportActionNext($component, 1)->assertHasNoFormErrors();
    $component->fillForm(['confirm_import' => true])->callMountedAction()->assertHasNoFormErrors()->assertNotified();
    $created = $configurator->rules()->whereKeyNot($existing->id)->sole();
    expect($created->label)->toBe('<script>unsafe</script>')->and($created->is_active)->toBeFalse();
    expect($existing->fresh()->label)->toBe('existing');
});

test('Rules preview requires explicit allowed Configurators on Data Exchange', function () {
    [, , $rows] = ruleImportActionFixture();
    $component = Livewire::test(DataExchange::class)
        ->mountAction(TestAction::make('importRules')->schemaComponent(true, 'content'))
        ->fillForm(['file' => ruleImportActionFile($rows)]);

    ruleImportActionNext($component, 0)->assertHasFormErrors(['configurator_ids' => 'required']);
    expect($component->get('ruleImportPreviewToken'))->toBeNull();
});

test('changing an option after preview cannot apply the previously reviewed Rules', function () {
    [$configurator, , $rows] = ruleImportActionFixture();
    $rows[0] = array_replace($rows[0], ['import' => '1', 'rule_label' => 'Changed']);
    $component = Livewire::test(DataExchange::class)
        ->mountAction(TestAction::make('importRules')->schemaComponent(true, 'content'))
        ->fillForm(['file' => ruleImportActionFile($rows), 'configurator_ids' => [$configurator->id], 'allow_update' => true]);
    ruleImportActionNext($component, 0)->assertHasNoFormErrors();
    $component->set('mountedActions.0.data.keep_new_disabled', false)
        ->fillForm(['confirm_import' => true])->callMountedAction()->assertHasFormErrors(['file']);
    expect($configurator->rules()->sole()->label)->toBe('existing');
});

test('a blocked Rules preview displays its errors and cannot advance or save', function () {
    [$configurator, , $rows] = ruleImportActionFixture();
    $rows[0] = array_replace($rows[0], ['import' => '1', 'rule_label' => 'Changed']);
    $component = Livewire::test(DataExchange::class)
        ->mountAction(TestAction::make('importRules')->schemaComponent(true, 'content'))
        ->fillForm(['file' => ruleImportActionFile($rows), 'configurator_ids' => [$configurator->id]]);
    ruleImportActionNext($component, 0)->assertHasNoFormErrors();
    expect($component->get('ruleImportPreview.can_import'))->toBeFalse();
    ruleImportActionNext($component, 1)->assertHasFormErrors(['file']);
    $component->fillForm(['confirm_import' => true])->callMountedAction()->assertHasFormErrors(['file']);
    expect($configurator->rules()->sole()->label)->toBe('existing');
});

test('the scoped Rules wizard rejects a foreign Configurator even when form scope is forged', function () {
    [$foreign, , $rows] = ruleImportActionFixture();
    $owner = Configurator::factory()->create();
    $rows[0]['import'] = '1';
    $component = Livewire::test(RulesRelationManager::class, ['ownerRecord' => $owner, 'pageClass' => EditConfigurator::class])
        ->mountAction(TestAction::make('importRules')->table())
        ->fillForm(['file' => ruleImportActionFile($rows), 'allow_update' => true])
        ->set('mountedActions.0.data.configurator_ids', [$foreign->id]);
    ruleImportActionNext($component, 0)->assertHasNoFormErrors();
    expect($component->get('ruleImportPreview.can_import'))->toBeFalse();
    $component->fillForm(['confirm_import' => true])->callMountedAction()->assertHasFormErrors(['file']);
    expect($foreign->rules()->sole()->label)->toBe('existing');
});

test('Rules import reauthorizes a mounted action after catalog permission is revoked', function () {
    [$configurator, , $rows] = ruleImportActionFixture();
    $component = Livewire::test(DataExchange::class)
        ->mountAction(TestAction::make('importRules')->schemaComponent(true, 'content'))
        ->fillForm(['file' => ruleImportActionFile($rows), 'configurator_ids' => [$configurator->id]]);
    auth()->user()->update(['email' => 'unauthorized@example.test']);
    ruleImportActionNext($component, 0)->assertForbidden();
    $this->assertDatabaseCount('configurator_rules', 1);
});

test('malformed Rules uploads show a field error instead of creating preview authority', function () {
    $configurator = Configurator::factory()->create();
    $component = Livewire::test(DataExchange::class)
        ->mountAction(TestAction::make('importRules')->schemaComponent(true, 'content'))
        ->fillForm(['file' => UploadedFile::fake()->createWithContent('rules.csv', 'wrong,headers'), 'configurator_ids' => [$configurator->id]]);
    ruleImportActionNext($component, 0)->assertHasFormErrors(['file']);
    expect($component->get('ruleImportPreviewToken'))->toBeNull();
    $this->assertDatabaseCount('configurator_rules', 0);
});

test('a definition changed after preview is reported at confirmation and requires a new preview', function () {
    [$configurator, $rule, $rows] = ruleImportActionFixture();
    $rows[0] = array_replace($rows[0], ['import' => '1', 'rule_label' => 'Import label']);
    $component = Livewire::test(DataExchange::class)
        ->mountAction(TestAction::make('importRules')->schemaComponent(true, 'content'))
        ->fillForm(['file' => ruleImportActionFile($rows), 'configurator_ids' => [$configurator->id], 'allow_update' => true]);
    ruleImportActionNext($component, 0)->assertHasNoFormErrors();
    $rule->update(['label' => 'Concurrent label']);
    $component->fillForm(['confirm_import' => true])->callMountedAction()->assertHasFormErrors(['confirm_import']);
    expect($component->get('ruleImportPreviewToken'))->toBeNull();
    expect($rule->fresh()->label)->toBe('Concurrent label');
});

test('client preview tampering cannot authorize Rules changes', function () {
    [$configurator, , $rows] = ruleImportActionFixture();
    $component = Livewire::test(DataExchange::class)
        ->mountAction(TestAction::make('importRules')->schemaComponent(true, 'content'))
        ->fillForm(['file' => ruleImportActionFile($rows), 'configurator_ids' => [$configurator->id]]);

    expect(fn () => $component->set('ruleImportPreview', ['can_import' => true]))
        ->toThrow(CannotUpdateLockedPropertyException::class);
    $this->assertDatabaseCount('configurator_rules', 1);
});

test('a scoped Rules update shows child deletions and preserves a dirty workspace draft as stale', function () {
    [$configurator, $rule] = ruleImportActionFixture();
    $extra = RuleEffect::factory()->for($rule, 'rule')->create([
        'target_configurator_attribute_id' => $rule->effects()->sole()->target_configurator_attribute_id,
        'kind' => 'SetHint', 'target_scope' => 'Attribute', 'display_value' => 'Remove this hint',
    ]);
    $rows = app(ConfiguratorRuleExchange::class)->snapshot($rule->fresh())['rows'];
    $rows = [array_replace($rows[0], ['import' => '1', 'rule_label' => 'Imported label'])];
    $component = Livewire::test(RulesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->call('selectRule', $rule->id)->set('editorData.label', 'Unsaved local label')
        ->mountAction(TestAction::make('importRules')->table())
        ->fillForm(['file' => ruleImportActionFile($rows), 'allow_update' => true]);
    ruleImportActionNext($component, 0)->assertHasNoFormErrors();
    expect($component->instance()->getSchema($component->instance()->getMountedActionSchemaName())->toHtml())->toContain('e_'.$extra->id);
    expect($component->get('ruleImportPreview.configurators.0.changes.0.deleted_children'))->toContain('e_'.$extra->id);
    ruleImportActionNext($component, 1)->assertHasNoFormErrors();
    $component->fillForm(['confirm_import' => true])->callMountedAction()->assertHasNoFormErrors()->assertDispatched('configurator-updated');
    expect($rule->fresh()->label)->toBe('Imported label');
    expect($rule->effects()->count())->toBe(1);
    expect($component->get('editorData.label'))->toBe('Unsaved local label');
    expect($component->get('editorStaleRows.rule:'.$rule->id))->toBeTrue();
});

test('regenerating the Rules preview after navigating back renews the token and clears confirmation', function () {
    [$configurator, , $rows] = ruleImportActionFixture();
    $rows[0] = array_replace($rows[0], ['import' => '1', 'rule_label' => 'Changed']);
    $component = Livewire::test(DataExchange::class)
        ->mountAction(TestAction::make('importRules')->schemaComponent(true, 'content'))
        ->fillForm(['file' => ruleImportActionFile($rows), 'configurator_ids' => [$configurator->id], 'allow_update' => true]);
    ruleImportActionNext($component, 0)->assertHasNoFormErrors();
    $token = $component->get('ruleImportPreviewToken');
    $component->set('mountedActions.0.data.confirm_import', true)->call('previewRuleImport')->assertHasNoErrors();
    expect($component->get('ruleImportPreviewToken'))->not->toBe($token);
    expect($component->get('mountedActions.0.data.confirm_import'))->toBeFalse();
    $component->callMountedAction()->assertHasFormErrors(['confirm_import']);
    expect($configurator->rules()->sole()->label)->toBe('existing');
});

test('replacing the uploaded file after preview cannot apply the previous Rules file', function (string $replacementLabel) {
    [$configurator, , $rows] = ruleImportActionFixture();
    $rows[0] = array_replace($rows[0], ['import' => '1', 'rule_label' => 'Changed']);
    $component = Livewire::test(DataExchange::class)
        ->mountAction(TestAction::make('importRules')->schemaComponent(true, 'content'))
        ->fillForm(['file' => ruleImportActionFile($rows), 'configurator_ids' => [$configurator->id], 'allow_update' => true]);
    ruleImportActionNext($component, 0)->assertHasNoFormErrors();
    $rows[0]['rule_label'] = $replacementLabel;
    $component->fillForm(['file' => ruleImportActionFile($rows), 'confirm_import' => true])
        ->callMountedAction()->assertHasFormErrors(['file']);
    expect($configurator->rules()->sole()->label)->toBe('existing');
})->with(['different content' => 'Another file', 'identical content new upload' => 'Changed']);

test('the rendered Rules wizard preserves its Alpine back handler and keeps all steps inside the native wizard', function () {
    $component = Livewire::test(DataExchange::class)
        ->mountAction(TestAction::make('importRules')->schemaComponent(true, 'content'));
    $schema = $component->instance()->getSchema($component->instance()->getMountedActionSchemaName());
    $wizard = $schema->getComponent(fn ($component): bool => $component instanceof Wizard);
    $document = new DOMDocument;
    $previousErrors = libxml_use_internal_errors(true);
    try {
        $document->loadHTML($schema->toHtml());
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);
    }
    $xpath = new DOMXPath($document);
    $wizardElements = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " fi-sc-wizard ")]');

    expect($wizardElements->length)->toBe(1);
    $wizardElement = $wizardElements->item(0);
    expect($wizardElement->getAttribute('id'))->toBe($wizard->getId());
    expect($wizardElement->getAttribute('x-data'))->toContain('wizardSchemaComponent(');
    expect($wizardElement->getAttribute('x-init'))->toBe('$watch("step", (current, previous) => { if (previous && getStepIndex(current) < getStepIndex(previous)) { $wire.previewRuleImport() } })');
    expect($wizardElement->textContent)->not->toContain('$wire.previewRuleImport()', 'getStepIndex(current)');
    expect($xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " fi-sc-wizard-step ")]', $wizardElement)->length)->toBe(3);
    $submitContainers = [];
    foreach ($xpath->query('.//div', $wizardElement) as $element) {
        if (str_contains($element->getAttribute('x-bind:class'), '! isLastStep()')) {
            $submitContainers[] = $element;
        }
    }
    expect($submitContainers)->toHaveCount(1);
    $submitButtons = $xpath->query('.//button', $submitContainers[0]);
    expect($submitButtons->length)->toBe(1);
    expect($submitButtons->item(0)->getAttribute('wire:click'))->toBe('callMountedAction');
    expect($submitButtons->item(0)->textContent)->toContain(__('data_exchange.rules.confirm_action'));
});
