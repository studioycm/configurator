<x-catalog.related-editor>
    <x-slot:list>{{ $this->table }}</x-slot:list>
    @if ($editorKind !== null)
        <x-filament::section :heading="$selectedRuleId === null ? 'New '.strtolower($editorKind).' rule' : 'Edit rule'" wire:key="rule-editor-{{ $selectedRuleId ?? $editorKind }}">
            <div class="space-y-5">
                {{ $this->editorForm }}
                <div class="flex flex-wrap gap-3">
                    <x-filament::button wire:click="saveEditor" wire:loading.attr="disabled" wire:target="saveEditor">Save rule</x-filament::button>
                    <x-filament::button color="gray" wire:click="closeEditor" data-close-editor>Close</x-filament::button>
                </div>
            </div>
        </x-filament::section>
    @else
        <x-filament::section>
            <p class="text-sm text-gray-500">Select a rule to edit it, or add a new mapping or advanced rule.</p>
        </x-filament::section>
    @endif
</x-catalog.related-editor>
