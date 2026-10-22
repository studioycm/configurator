<?php

namespace App\Livewire\Catalog;

use App\Actions\AddAttributeValues;
use App\Actions\SaveCanonicalOption;
use App\Filament\Resources\Attributes\Schemas\AttributeForm;
use App\Filament\Resources\Options\Schemas\OptionForm;
use App\Models\Attribute;
use App\Models\Option;
use App\Services\ItemLists;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class AttributeValueAssignment extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    #[Locked]
    public string $listKey;

    #[Locked]
    public int $parentId;

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(string $listKey, int $parentId): void
    {
        $this->listKey = $listKey;
        $this->parentId = $parentId;
        $this->authorizeAssignment();
        $this->form->fill($listKey === 'value-options' ? ['value_id' => $parentId] : []);
    }

    private function authorizeAssignment(): void
    {
        abort_unless(in_array($this->listKey, ['attribute-options', 'value-options'], true), 404);
        app(ItemLists::class)->definition(auth()->user(), $this->listKey, $this->parentId);
    }

    public function form(Schema $schema): Schema
    {
        $schema->statePath('data')->operation('create')->rootHeadingLevel(3);
        if ($this->listKey === 'attribute-options') {
            return $schema->model(Attribute::findOrFail($this->parentId))->components([AttributeForm::valuesSection(standalone: true)]);
        }

        return OptionForm::configure($schema->model(Option::class), fixedValueId: $this->parentId);
    }

    public function save(): void
    {
        $this->authorizeAssignment();
        $data = $this->form->getState();
        try {
            if ($this->listKey === 'attribute-options') {
                app(AddAttributeValues::class)->handle(auth()->user(), Attribute::findOrFail($this->parentId), $data['pending_options'] ?? []);
            } else {
                app(SaveCanonicalOption::class)->handle(auth()->user(), null, (int) $data['attribute_id'], $this->parentId, $data['code']);
            }
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(function (array $messages, string $field): array {
                if ($this->listKey === 'value-options' && $field === 'value_id') {
                    return ['data.attribute_id' => [__('admin-ux.value_already_used')]];
                }

                return ['data.'.$field => $messages];
            })->all());
        }
        $this->form->fill($this->listKey === 'value-options' ? ['value_id' => $this->parentId] : []);
        $this->dispatch('item-drawer-assignment-saved', listKey: $this->listKey, parentId: $this->parentId)->to(ItemListDrawer::class);
        $this->dispatch('catalog-record-saved');
        $this->dispatch('configurator-updated');
        $this->dispatch('catalog-editor-saved');
        Notification::make()->title(__('admin-ux.relation_saved'))->success()->send();
    }

    public function cancel(): void
    {
        $this->authorizeAssignment();
        $this->dispatch('item-drawer-editor-closed', listKey: $this->listKey, parentId: $this->parentId)->to(ItemListDrawer::class);
    }

    public function render(): View
    {
        $this->authorizeAssignment();

        return view('livewire.catalog.attribute-value-assignment');
    }
}
