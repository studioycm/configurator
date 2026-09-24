<x-catalog.related-editor>
    <x-slot:list>{{ $this->table }}</x-slot:list>
    @if ($attribute = $this->selectedAttribute())
        <div class="space-y-6" wire:key="attribute-editor-{{ $attribute->id }}">
            <x-filament::section :heading="$attribute->label_override ?? $attribute->attribute->label">
                <div class="space-y-5">
                    {{ $this->editorForm }}
                    <div class="flex flex-wrap gap-3">
                        <x-filament::button wire:click="saveEditor" wire:loading.attr="disabled" wire:target="saveEditor">Save attribute</x-filament::button>
                        <x-filament::button color="gray" wire:click="closeEditor" data-close-editor>Close</x-filament::button>
                    </div>
                </div>
            </x-filament::section>
            @livewire(\App\Filament\Resources\Configurators\RelationManagers\OptionsRelationManager::class, ['ownerRecord' => $attribute, 'pageClass' => $this->getPageClass()], key('attribute-options-'.$attribute->id))
        </div>
    @else
        <x-filament::section>
            <p class="text-sm text-gray-500">Select an attribute to edit its settings and included options.</p>
        </x-filament::section>
    @endif
</x-catalog.related-editor>
