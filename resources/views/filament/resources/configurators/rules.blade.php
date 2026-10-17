<x-catalog.related-editor workspace="rules">
    <x-slot:list>{{ $this->table }}</x-slot:list>
    @if ($editorKind !== null)
        <x-filament::section :heading="__($selectedRuleId !== null ? 'configurator.headings.edit_rule' : ($editorKind === 'Mapping' ? 'configurator.headings.new_mapping_rule' : 'configurator.headings.new_advanced_rule'))" wire:key="rule-editor-{{ $selectedRuleId ?? $editorKind }}">
            <div class="space-y-5">
                @if ($selectedRuleId !== null && isset($editorStaleRows['rule:'.$selectedRuleId]))
                    <div role="alert" class="text-sm text-warning-600">
                        <p>{{ __('admin.workspace.drafts.batch_changed') }}</p>
                        <x-filament::button color="warning" wire:click="reloadEditor">{{ __('admin.workspace.copy.reload_saved_record') }}</x-filament::button>
                    </div>
                @endif
                {{ $this->editorForm }}
                <div class="flex flex-wrap gap-3">
                    <x-filament::button wire:click="saveEditor" wire:loading.attr="disabled" wire:target="saveEditor">{{ __('admin.workspace.copy.save_rule') }}</x-filament::button>
                    <x-filament::button color="gray" wire:click="closeEditor" data-close-editor>{{ __('ui.actions.close') }}</x-filament::button>
                </div>
            </div>
        </x-filament::section>
    @else
        <x-filament::section>
            <p class="text-sm text-gray-500">{{ __('admin.workspace.copy.select_a_rule_to_edit_it_or_add_a_new_mapping_or_advanced_rule') }}</p>
        </x-filament::section>
    @endif
</x-catalog.related-editor>
