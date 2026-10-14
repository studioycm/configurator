<?php

use App\Filament\Resources\Attributes\AttributeResource;
use App\Filament\Resources\Options\OptionResource;
use App\Filament\Resources\Options\Pages\CreateOption;
use App\Filament\Resources\Options\Pages\EditOption;
use App\Models\Attribute;
use App\Models\Option;
use App\Models\User;
use App\Models\Value;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Lang;
use Livewire\Livewire;

test('Attribute Values are omitted from navigation while authorized workflows remain available', function (string $email) {
    $this->actingAs(User::factory()->create(['email' => $email]));
    Lang::addLines([
        'catalog.resources.attribute_values.plural' => 'Shared library values',
        'catalog.attribute_values.headings.shared' => 'Shared library value details',
    ], 'en');
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $attribute = Attribute::factory()->create();
    $value = Value::factory()->create();

    $navigationUrls = collect(Filament::getNavigation())->flatMap(fn ($group) => $group->getItems())->map(fn ($item) => $item->getUrl());

    expect($navigationUrls)->toContain(AttributeResource::getUrl())->not->toContain(OptionResource::getUrl());
    expect(OptionResource::getSlug())->toBe('options');
    $this->get(OptionResource::getUrl())->assertSee(OptionResource::getTitleCasePluralModelLabel());
    $create = Livewire::test(CreateOption::class);
    $this->get(OptionResource::getUrl('create'))->assertSee($create->instance()->getTitle());
    $create
        ->fillForm(['attribute_id' => $attribute->id, 'value_id' => $value->id, 'code' => 'A1'])
        ->call('create')->assertHasNoFormErrors();
    $option = Option::where('code', 'A1')->sole();
    $editor = Livewire::test(EditOption::class, ['record' => $option->id]);
    $this->get(OptionResource::getUrl('edit', ['record' => $option]))->assertSee($editor->instance()->form->getComponents()[0]->getHeading());
    Livewire::test(EditOption::class, ['record' => $option->id, 'listKey' => 'attribute-options', 'parentId' => $attribute->id])
        ->fillForm(['attribute_id' => $attribute->id, 'value_id' => $value->id, 'code' => 'A2'])
        ->call('save')->assertHasNoFormErrors()->assertDispatched('item-drawer-record-saved');
    $this->assertDatabaseHas('options', ['id' => $option->id, 'code' => 'A2']);
    expect(OptionResource::getGlobalSearchResults('A2')->pluck('url')->first())->toBe(OptionResource::getUrl('edit', ['record' => $option]));
    expect(OptionResource::canDelete($option))->toBeTrue();
})->with(['ycm@data4.work', 'beny.cr@gmail.com']);

test('navigation hiding does not grant Attribute Value access to catalog users', function () {
    $this->actingAs(User::factory()->create());
    $option = Option::factory()->create();

    $this->get(OptionResource::getUrl('create'))->assertForbidden();
    $this->get(OptionResource::getUrl('edit', ['record' => $option]))->assertForbidden();
    Livewire::test(EditOption::class, ['record' => $option->id, 'listKey' => 'attribute-options', 'parentId' => $option->attribute_id])->assertForbidden();
    expect(OptionResource::canGloballySearch())->toBeFalse();
});
