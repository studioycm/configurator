<?php

use App\Livewire\Catalog\ContextSelector;
use Livewire\Livewire;

test('the context summary uses choice labels and the modal marks one active toggle per group', function () {
    $component = Livewire::test(ContextSelector::class, [
        'context' => ['territory' => 'eu', 'application' => 'water'],
        'schema' => [
            'territory' => [['value' => 'eu', 'label' => 'European Union'], ['value' => 'us', 'label' => 'United States']],
            'application' => [['value' => 'water', 'label' => 'Drinking water']],
        ],
    ]);

    $document = new DOMDocument;
    @$document->loadHTML($component->html());
    $xpath = new DOMXPath($document);

    expect(array_map(fn (DOMNode $value): string => trim($value->textContent), iterator_to_array($xpath->query('//dl/dd'))))
        ->toBe(['European Union', 'Drinking water']);
    expect(array_map(fn (DOMNode $button): string => trim($button->textContent), iterator_to_array($xpath->query('//fieldset//button[@aria-pressed="true"]'))))
        ->toBe(['European Union', 'Drinking water']);
});

test('empty context schemas still offer All and context labels remain escaped', function () {
    Livewire::test(ContextSelector::class)->assertSee('Territory')->assertSee('Application')->assertSee('All')
        ->assertSeeHtml('aria-label="Edit territory and application"');

    Livewire::test(ContextSelector::class, [
        'context' => ['territory' => 'test', 'application' => 'All'],
        'schema' => [
            'territory' => [['value' => 'test', 'label' => '<script>unsafe</script>']],
            'application' => [],
        ],
    ])->assertSee('<script>unsafe</script>')->assertDontSee('<script>unsafe</script>', false);
});
