<?php

use App\Actions\SaveConfiguratorDefinition;
use App\Livewire\Catalog\ConfiguratorPreview;
use App\Livewire\Catalog\ProductConfigurator;
use App\Models\CatalogContextSettings;
use App\Models\Group;
use App\Models\Option;
use App\Models\Product;
use App\Models\User;
use App\Services\ConfiguratorDefinitionLoader;
use Livewire\Livewire;

require_once dirname(__DIR__).'/ConfiguratorFixtures.php';

function previewPresentationFixture(?Closure $alter = null): array
{
    test()->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    [$configurator, $data] = canonicalDefinitionFixture();
    if ($alter !== null) {
        $alter($data);
    }
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $data);
    $product = Product::factory()->for(Group::factory()->create(['configurator_id' => $configurator->id]))->create();

    return [$configurator, $product, app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh())];
}

function previewPresentationDocument(string $html): DOMDocument
{
    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return $document;
}

function previewPresentationSection(DOMDocument $document, string $heading): DOMElement
{
    foreach ($document->getElementsByTagName('section') as $section) {
        $title = $section->getElementsByTagName('h3')->item(0);
        if ($title !== null && trim($title->textContent) === $heading) {
            return $section;
        }
    }

    throw new RuntimeException('Expected presentation section is missing: '.$heading);
}

test('local Display theme hints and diagnostics controls have no automatic request disabling in rendered DOM', function () {
    [$configurator] = previewPresentationFixture(function (array &$data): void {
        $data['attributes'][1]['options'][0]['hint'] = 'Local hint remains interactive';
    });

    $preview = Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id]);
    $document = previewPresentationDocument($preview->html());
    $local = [];
    foreach ($document->getElementsByTagName('button') as $button) {
        if (in_array($button->getAttribute('x-on:click'), ["setTheme('light')", "setTheme('dark')", 'closeDiagnostics()'], true)
            || trim($button->textContent) === __('configurator.preview.display')
            || $button->getAttribute('aria-label') === 'Local hint remains interactive') {
            $local[] = $button;
        }
    }

    expect($local)->toHaveCount(5);
    foreach ($local as $button) {
        expect($button->hasAttribute('wire:loading.attr'))->toBeFalse();
        expect($button->hasAttribute('disabled'))->toBeFalse();
    }
});

test('both Preview presentations bind native selection to the same snapshot after a hidden Value reorder', function (string $inputType) {
    [$configurator, $product, $saved] = previewPresentationFixture(function (array &$data) use ($inputType): void {
        $data['attributes'][1]['input_type'] = $inputType;
        $data['attributes'][1]['options'][0]['label_override'] = 'EPDM';
        $data['attributes'][1]['options'][1]['label_override'] = 'Buna N';
        $data['rules'] = [fixtureAdvanced('hide-epdm', [fixtureCondition('a1', 'A', 'A1')], 'B', 'HideOptions', ['new:B0'])];
    });
    $attribute = $saved['attributes'][1];
    $preview = Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id]);

    $preview->call('selectOption', $saved['attributes'][0]['id'], $saved['attributes'][0]['options'][1]['id']);
    $snapshot = $preview->get('previewView');
    $document = previewPresentationDocument($preview->html());
    $boundRadios = [];
    foreach ($document->getElementsByTagName('input') as $input) {
        if ($input->getAttribute('type') !== 'radio' || ! str_ends_with($input->getAttribute('name'), '-'.$attribute['id'])) {
            continue;
        }
        $boundRadios[] = $input;
        expect($input->getAttribute('x-bind:checked'))->toContain('$wire.previewView?.inspection?.attributes?.', "?.options?.['".$input->getAttribute('value')."']?.selected ?? false");
        expect($input->hasAttribute('checked'))->toBe($input->getAttribute('value') === $attribute['options'][1]['id']);
    }
    expect($boundRadios)->toHaveCount($inputType === 'select' ? 2 : 3);
    expect(array_map('strval', array_keys($snapshot['inspection']['attributes'][$attribute['id']]['options'])))->toBe([$attribute['options'][1]['id'], $attribute['options'][0]['id']]);
    if ($inputType === 'select') {
        $select = $document->getElementsByTagName('select')->item(0);
        foreach ($document->getElementsByTagName('select') as $candidate) {
            if ($candidate->getAttribute('aria-label') === ($attribute['label_override'] ?? 'B')) {
                $select = $candidate;
            }
        }
        expect($select->getAttribute('x-bind:value'))->toContain('$wire.previewView?.inspection?.attributes?.', "?.selected_id ?? ''");
        expect($select->getElementsByTagName('option')->item(0)->getAttribute('x-bind:selected'))->toContain('?.selected ?? false');
    }

    $public = Livewire::test(ProductConfigurator::class, ['productId' => $product->id]);
    $publicDocument = previewPresentationDocument($public->html());
    foreach ($publicDocument->getElementsByTagName('input') as $input) {
        if ($input->getAttribute('type') === 'radio') {
            expect($input->hasAttribute('x-bind:checked'))->toBeFalse();
        }
    }
    foreach ($publicDocument->getElementsByTagName('select') as $select) {
        expect($select->hasAttribute('x-bind:value'))->toBeFalse();
    }
})->with(['radio' => 'toggle', 'native select' => 'select']);

test('Review distinguishes newer inventory from the retained tested snapshot', function () {
    $html = view('livewire.catalog.configurator-preview-changes', [
        'changes' => [['type' => 'overview', 'id' => 'overview', 'label' => 'Configurator', 'new' => false, 'fields' => []]],
        'tested' => ['source' => 'unsaved', 'inventory_hash' => 'tested-a', 'configuration_code' => 'ORIGINAL-CODE'],
        'inventoryHash' => 'newer-b', 'definitionStale' => false, 'editorPreviewStale' => true,
    ])->render();

    expect($html)->toContain(__('configurator.preview.preview_stale'), 'ORIGINAL-CODE');
});

test('Review labels current changes unevaluated when the visible test uses saved data', function () {
    $html = view('livewire.catalog.configurator-preview-changes', [
        'changes' => [['type' => 'overview', 'id' => 'overview', 'label' => 'Configurator', 'new' => false, 'fields' => []]],
        'tested' => ['source' => 'saved', 'inventory_hash' => null, 'configuration_code' => 'SAVED-CODE'],
        'inventoryHash' => 'newer-b', 'definitionStale' => false, 'editorPreviewStale' => false,
    ])->render();

    expect($html)->toContain(__('configurator.preview.review_not_validated'), 'SAVED-CODE');
});

test('inspection includes unmatched disabled and excluded definitions targeting the inspected Attribute', function () {
    CatalogContextSettings::current()->update(['choices' => ['territory' => [['value' => 'USA', 'label' => 'USA']], 'application' => []]]);
    [$configurator, , $saved] = previewPresentationFixture(function (array &$data): void {
        $disabled = fixtureAdvanced('disabled-target', [], 'B', 'SetHint', [], 'Disabled hint');
        $disabled['is_active'] = false;
        $disabled['label'] = 'Disabled target definition';
        $context = ['id' => 'new:context-condition', 'source_kind' => 'Territory', 'source_configurator_attribute_id' => null, 'property_key' => null, 'context_dimension' => 'territory', 'operator' => 'Equals', 'operand' => 'USA', 'option_ids' => []];
        $data['rules'] = [fixtureAdvanced('unmatched-target', [fixtureCondition('unmatched-a1', 'A', 'A1')], 'B', 'SetHint', [], 'Unmatched hint'), $disabled, fixtureAdvanced('excluded-target', [$context], 'B', 'SetHint', [], 'Context hint')];
        foreach ($data['rules'] as $index => &$rule) {
            $rule['priority'] = $index;
            $rule['label'] = ['Unmatched target definition', 'Disabled target definition', 'Excluded target definition'][$index];
        }
        unset($rule);
    });

    $preview = Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id])->call('inspectState', $saved['attributes'][1]['id']);
    $document = previewPresentationDocument($preview->html());
    $section = previewPresentationSection($document, __('configurator.copy.rule_diagnostics'));

    expect($section->textContent)->toContain('Unmatched target definition', 'Disabled target definition', 'Excluded target definition', __('configurator.preview.rule_outcomes.not_recorded'), __('configurator.preview.preview_excluded'));
});

test('selected Value outgoing effects follow the source Attribute for negative predicates while unselected Values remain definition references', function (string $operator) {
    [$configurator, , $saved] = previewPresentationFixture(function (array &$data) use ($operator): void {
        $third = Option::factory()->create(['attribute_id' => $data['attributes'][0]['attribute_id'], 'code' => 'A2']);
        $data['attributes'][0]['options'][] = [...$data['attributes'][0]['options'][1], 'id' => 'new:A2', 'option_id' => $third->id, 'display_order' => 2];
        $condition = [...fixtureCondition('negative-a1', 'A', 'A1'), 'operator' => $operator];
        $data['rules'] = [fixtureAdvanced('negative-outgoing', [$condition], 'B', 'HideOptions', ['new:B0'])];
        $data['rules'][0]['label'] = 'Negative predicate outgoing effect';
    });
    $source = $saved['attributes'][0];
    $preview = Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id]);

    $preview->call('inspectState', $source['id'], $source['options'][0]['id']);
    $selectedSection = previewPresentationSection(previewPresentationDocument($preview->html()), __('configurator.preview.effects_other_choices'));
    expect($selectedSection->textContent)->toContain('Negative predicate outgoing effect', __('configurator.preview.rule_outcomes.recorded_applied'));
    $preview->call('inspectState', $source['id'], $source['options'][1]['id']);
    $unselectedSection = previewPresentationSection(previewPresentationDocument($preview->html()), __('configurator.preview.effects_other_choices'));
    expect($unselectedSection->textContent)->toContain('Negative predicate outgoing effect', __('configurator.preview.related_unevaluated'));
    expect($unselectedSection->textContent)->not->toContain(__('configurator.preview.rule_outcomes.recorded_applied'));
    $preview->call('inspectState', $source['id'], $source['options'][2]['id']);
    $thirdSection = previewPresentationSection(previewPresentationDocument($preview->html()), __('configurator.preview.effects_other_choices'));
    expect($thirdSection->textContent)->toContain('Negative predicate outgoing effect', __('configurator.preview.related_unevaluated'));
    expect($thirdSection->textContent)->not->toContain(__('configurator.preview.rule_outcomes.recorded_applied'));
})->with(['not equal' => 'NotEquals', 'not in' => 'NotIn']);
