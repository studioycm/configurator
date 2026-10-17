<x-catalog.related-editor data-preview-editor="attributes">
    <x-slot:list>{{ $this->table }}</x-slot:list>
    @if ($attribute = $this->selectedAttribute())
        <div class="space-y-6" wire:key="attribute-editor-{{ $attribute->id }}">
            <x-filament::section :heading="$attribute->label_override ?? $attribute->attribute->label">
                <div class="space-y-5">
                    @if (isset($editorStaleRows[$attribute->id]))
                        <div role="alert" class="text-sm text-warning-600">
                            <p>{{ __('admin.workspace.drafts.batch_changed') }}</p>
                            <x-filament::button color="warning" wire:click="reloadEditor">{{ __('admin.workspace.copy.reload_saved_record') }}</x-filament::button>
                        </div>
                    @endif
                    {{ $this->editorForm }}
                    <div class="flex flex-wrap gap-3">
                        <x-filament::button wire:click="saveEditor" wire:loading.attr="disabled" wire:target="saveEditor">{{ __('admin.workspace.copy.save_attribute') }}</x-filament::button>
                        <x-filament::button color="gray" wire:click="closeEditor" data-close-editor>{{ __('ui.actions.close') }}</x-filament::button>
                    </div>
                </div>
            </x-filament::section>
            @livewire(\App\Filament\Resources\Configurators\RelationManagers\OptionsRelationManager::class, ['ownerRecord' => $attribute, 'pageClass' => $this->getPageClass(), 'selectedOptionId' => $selectedOptionId], key('attribute-options-'.$attribute->id))
            @if ($option = $this->selectedOption())
                <x-filament::section :heading="__('configurator.inclusions.editor_heading', ['code' => $option->option->code, 'label' => $option->option->value->labelWithSemanticId()])" wire:key="option-editor-{{ $option->id }}">
                    <p class="mb-2 text-sm text-gray-500">{{ __('configurator.inclusions.inheritance_help') }}</p>
                    @if (isset($optionEditorStaleRows[$option->id]))
                        <div role="alert" class="mb-2 text-sm text-warning-600">
                            <p>{{ __('admin.workspace.copy.batch_changes_were_saved_your_value_draft_was_kept') }}</p>
                            <x-filament::button color="warning" wire:click="reloadOptionEditor">{{ __('admin.workspace.copy.reload_saved_value') }}</x-filament::button>
                        </div>
                    @endif
                    {{ $this->optionEditorForm }}
                    <div class="mt-3 flex flex-wrap gap-3">
                        <x-filament::button wire:click="saveOptionEditor" wire:loading.attr="disabled" wire:target="saveOptionEditor">{{ __('admin.workspace.copy.save_value') }}</x-filament::button>
                        <x-filament::button color="gray" wire:click="closeOptionEditor" data-close-option-editor>{{ __('admin.workspace.copy.close_value') }}</x-filament::button>
                    </div>
                </x-filament::section>
            @endif
        </div>
    @else
        <x-filament::section>
            <p class="text-sm text-gray-500">{{ __('admin.workspace.copy.select_an_attribute_to_edit_its_settings_and_included_values') }}</p>
        </x-filament::section>
    @endif
</x-catalog.related-editor>
