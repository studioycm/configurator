<x-catalog.related-editor>
    <x-slot:list>{{ $this->table }}</x-slot:list>
    @if ($attribute = $this->selectedAttribute())
        <div class="space-y-6" wire:key="attribute-editor-{{ $attribute->id }}">
            <x-filament::section :heading="$attribute->label_override ?? $attribute->attribute->label">
                <div class="space-y-5">
                    @if (isset($editorStaleRows[$attribute->id]))
                        <div role="alert" class="text-sm text-warning-600">
                            <p>Batch changes were saved. Your draft was kept. Reload before editing again; reloading discards this record's draft.</p>
                            <x-filament::button color="warning" wire:click="reloadEditor">Reload saved record</x-filament::button>
                        </div>
                    @endif
                    {{ $this->editorForm }}
                    <div class="flex flex-wrap gap-3">
                        <x-filament::button wire:click="saveEditor" wire:loading.attr="disabled" wire:target="saveEditor">Save attribute</x-filament::button>
                        <x-filament::button color="gray" wire:click="closeEditor" data-close-editor>Close</x-filament::button>
                    </div>
                </div>
            </x-filament::section>
            @livewire(\App\Filament\Resources\Configurators\RelationManagers\OptionsRelationManager::class, ['ownerRecord' => $attribute, 'pageClass' => $this->getPageClass(), 'selectedOptionId' => $selectedOptionId], key('attribute-options-'.$attribute->id))
            @if ($option = $this->selectedOption())
                <x-filament::section :heading="'Option: '.$option->option->code.' · '.$option->option->value->label" wire:key="option-editor-{{ $option->id }}">
                    <p class="mb-2 text-sm text-gray-500">Blank local fields inherit the shared Value. Save this Option separately from the Attribute.</p>
                    @if (isset($optionEditorStaleRows[$option->id]))
                        <div role="alert" class="mb-2 text-sm text-warning-600">
                            <p>Batch changes were saved. Your Option draft was kept.</p>
                            <x-filament::button color="warning" wire:click="reloadOptionEditor">Reload saved Option</x-filament::button>
                        </div>
                    @endif
                    {{ $this->optionEditorForm }}
                    <div class="mt-3 flex flex-wrap gap-3">
                        <x-filament::button wire:click="saveOptionEditor" wire:loading.attr="disabled" wire:target="saveOptionEditor">Save option</x-filament::button>
                        <x-filament::button color="gray" wire:click="closeOptionEditor" data-close-option-editor>Close option</x-filament::button>
                    </div>
                </x-filament::section>
            @endif
        </div>
    @else
        <x-filament::section>
            <p class="text-sm text-gray-500">Select an attribute to edit its settings and included options.</p>
        </x-filament::section>
    @endif
</x-catalog.related-editor>
