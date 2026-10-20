<?php

use App\Filament\Resources\ItemCountColumn;
use App\Livewire\Catalog\ConfiguratorGroups;
use App\Livewire\Catalog\ItemListDrawer;
use App\Models\Attribute;
use App\Models\Configurator;
use App\Models\ConfiguratorAttribute;
use App\Models\ConfiguratorRule;
use App\Models\Group;
use App\Models\Option;
use App\Models\Product;
use App\Models\User;
use App\Models\Value;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;

test('count slide overs identify their source without repeating the table heading', function (Closure $source, string $listKey, string $heading, string $title) {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    $record = $source();
    $action = ItemCountColumn::make('items_count', 'Count', $listKey)->getAction()->record($record);

    expect($action->getModalHeading())->toBe($heading);
    $drawer = Livewire::test(ItemListDrawer::class, $action->getModalContent()->getData());
    expect($drawer->instance()->getTable()->getHeading())->toBe('')
        ->and($drawer->instance()->getTable()->getPluralModelLabel())->toBe($title);
})->with([
    'Master Value attribute values' => [fn (): Model => Value::factory()->create(['label' => 'Buna-N']), 'value-options', 'Attribute Values using Buna-N', 'Attribute Values'],
    'Attribute values' => [fn (): Model => Attribute::factory()->create(['label' => 'Material']), 'attribute-options', 'Attribute Values for Material', 'Attribute Values'],
    'Attribute uses' => [fn (): Model => Attribute::factory()->create(['label' => 'Material']), 'attribute-inclusions', 'Configurator Uses of Material', 'Configurator Uses'],
    'Attribute Value uses' => [fn (): Model => Option::factory()->for(Attribute::factory()->state(['label' => 'Material']))->for(Value::factory()->state(['label' => 'Buna-N']))->create(['code' => 'BN']), 'option-inclusions', 'Configurator Uses of BN - Buna-N | Material', 'Configurator Uses'],
    'Assigned groups' => [fn (): Model => Configurator::factory()->create(['name' => 'Seal Builder']), 'configurator-groups', 'Groups assigned to Seal Builder', 'Groups'],
    'Included attributes' => [fn (): Model => Configurator::factory()->create(['name' => 'Seal Builder']), 'configurator-attributes', 'Attributes included in Seal Builder', 'Attributes'],
    'Configurator rules' => [fn (): Model => Configurator::factory()->create(['name' => 'Seal Builder']), 'configurator-rules', 'Rules for Seal Builder', 'Rules'],
    'Local inclusion values' => [fn (): Model => ConfiguratorAttribute::factory()->for(Attribute::factory()->state(['label' => 'Material']))->for(Configurator::factory()->state(['name' => 'Seal Builder']))->create(['label_override' => 'Seal Material']), 'inclusion-options', 'Attribute Values for Seal Material in Seal Builder', 'Attribute Values'],
    'Group card properties' => [fn (): Model => Group::factory()->create(['name' => 'Seals']), 'group-card-properties', 'Card Properties for Seals', 'Card Properties'],
    'Rule mapping sets' => [fn (): Model => ConfiguratorRule::factory()->for(Configurator::factory()->state(['name' => 'Seal Builder']))->create(['label' => 'Material mapping', 'kind' => 'Mapping']), 'rule-mappings', 'Mapping Sets for Material mapping in Seal Builder', 'Mapping Sets'],
    'Rule effects' => [fn (): Model => ConfiguratorRule::factory()->for(Configurator::factory()->state(['name' => 'Seal Builder']))->create(['label' => 'Material restriction']), 'rule-effects', 'Effects for Material restriction in Seal Builder', 'Effects'],
]);

test('local value headings fall back to the attribute name without losing configurator context', function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    $inclusion = ConfiguratorAttribute::factory()->for(Attribute::factory()->state(['label' => 'Material']))->for(Configurator::factory()->state(['name' => 'Seal Builder']))->create(['label_override' => null]);

    $action = ItemCountColumn::make('options_count', 'Values', 'inclusion-options')->getAction()->record($inclusion);

    expect($action->getModalHeading())->toBe('Attribute Values for Material in Seal Builder');
});

test('the group product count names the group and preserves the product scope', function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    $configurator = Configurator::factory()->create();
    $group = Group::factory()->for($configurator)->create(['name' => 'Seals']);
    $own = Product::factory()->for($group)->create();
    $foreign = Product::factory()->create();

    $component = Livewire::test(ConfiguratorGroups::class, ['configuratorId' => $configurator->id])
        ->mountAction('products', ['group' => $group->id]);
    expect($component->instance()->getMountedAction()->getModalHeading())->toBe('Products in Seals');
    $drawer = Livewire::test(ItemListDrawer::class, $component->instance()->getMountedAction()->getModalContent()->getData())
        ->assertCanSeeTableRecords([$own])->assertCanNotSeeTableRecords([$foreign]);
    expect($drawer->instance()->getTable()->getHeading())->toBe('')
        ->and($drawer->instance()->getTable()->getPluralModelLabel())->toBe('Products');
});
