<?php

use App\Filament\Resources\ConfigurationParts\Pages\CreateConfigurationPart;
use App\Models\ConfigurationPart;
use App\Models\Part;
use App\Models\ProductConfiguration;
use App\Models\User;
use Filament\Forms\Components\Select;
use Livewire\Livewire;

it('searches parts by name and saves the selected relationship', function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    $configuration = ProductConfiguration::factory()->create();
    $matchingPart = Part::factory()->create(['name' => 'Stainless valve stem']);
    Part::factory()->create(['name' => 'Rubber seal']);

    $page = Livewire::test(CreateConfigurationPart::class)
        ->assertFormFieldExists('part_id', 'form', fn (Select $field): bool => $field->isSearchable());

    $field = $page->instance()->form->getFlatFields()['part_id'];
    expect($field->getSearchResults('valve'))->toBe([$matchingPart->id => 'Stainless valve stem']);
    expect($field->getSearchResults('no matching part'))->toBe([]);

    $page->fillForm([
        'product_configuration_id' => $configuration->id,
        'part_id' => $matchingPart->id,
        'part_number' => 7,
    ])
        ->call('create')
        ->assertHasNoFormErrors();

    $savedPart = ConfigurationPart::query()->sole();
    expect($savedPart->part_id)->toBe($matchingPart->id);
    expect($savedPart->product_configuration_id)->toBe($configuration->id);
    expect($savedPart->part_number)->toBe(7);
});
