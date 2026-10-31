<x-filament-panels::page x-data="{
    draftChanged(draft, saved, excluded = null) {
        const fields = state => Object.fromEntries(Object.entries(state).filter(([key]) => key !== excluded));
        return JSON.stringify(fields(draft)) !== JSON.stringify(fields(saved));
    }
}">
    <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('catalog.display.scope') }}</p>
    @if ($this->recovery)
        <p role="status" class="text-sm text-amber-700 dark:text-amber-400">{{ __('catalog.display.recovery') }}</p>
    @endif
    <form wire:submit="save" class="space-y-4">
        {{ $this->form }}
        @error('data') <p role="alert" class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p> @enderror
        <div class="flex flex-wrap gap-2">
            <x-filament::button type="submit" wire:loading.attr="disabled">{{ __('catalog.display.save_shared') }}</x-filament::button>
            <x-filament::button color="gray" wire:click="resetDefaults">{{ __('catalog.display.reset_shared') }}</x-filament::button>
            <span role="status" aria-live="polite" data-draft-status="shared" data-dirty="{{ $this->draftStatuses()['shared'] ? 'true' : 'false' }}"
                x-bind:data-dirty="draftChanged($wire.data, $wire.savedSharedData) ? 'true' : 'false'"
                x-text="draftChanged($wire.data, $wire.savedSharedData) ? @js(__('catalog.display.unsaved')) : @js(__('catalog.display.no_unsaved'))"
                class="text-sm text-gray-600 dark:text-gray-400">{{ $this->draftStatuses()['shared'] ? __('catalog.display.unsaved') : __('catalog.display.no_unsaved') }}</span>
        </div>
    </form>
    <x-filament::section :heading="__('catalog.display.group')">
        <form wire:submit="saveGroupPresentation" class="space-y-4">
            {{ $this->groupForm }}
            @error('groupData') <p role="alert" class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p> @enderror
            @if ($this->pendingGroupId)
                <div role="alert" class="space-y-2">
                    <p>{{ __('catalog.display.pending') }}</p>
                    <div class="flex flex-wrap gap-2">
                        <x-filament::button wire:click="resolveGroupChange('save')">{{ __('catalog.display.save_switch') }}</x-filament::button>
                        <x-filament::button color="gray" wire:click="resolveGroupChange('discard')">{{ __('catalog.display.discard') }}</x-filament::button>
                        <x-filament::button color="gray" wire:click="resolveGroupChange('stay')">{{ __('catalog.display.stay') }}</x-filament::button>
                    </div>
                </div>
            @endif
            @if ($this->selectedGroupId)
                <div class="flex flex-wrap gap-2">
                    <x-filament::button type="submit" wire:loading.attr="disabled">{{ __('catalog.display.save_group') }}</x-filament::button>
                    <x-filament::button color="gray" wire:click="resetGroupOverrides">{{ __('catalog.display.reset_group') }}</x-filament::button>
                    <span role="status" aria-live="polite" data-draft-status="group" data-dirty="{{ $this->draftStatuses()['group'] ? 'true' : 'false' }}"
                        x-bind:data-dirty="draftChanged($wire.groupData, $wire.savedGroupData, 'requested_group_id') ? 'true' : 'false'"
                        x-text="draftChanged($wire.groupData, $wire.savedGroupData, 'requested_group_id') ? @js(__('catalog.display.unsaved')) : @js(__('catalog.display.no_unsaved'))"
                        class="text-sm text-gray-600 dark:text-gray-400">{{ $this->draftStatuses()['group'] ? __('catalog.display.unsaved') : __('catalog.display.no_unsaved') }}</span>
                </div>
            @else
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('catalog.display.no_group') }}</p>
            @endif
        </form>
    </x-filament::section>
    @if ($this->selectedGroupId)
        <x-filament::section :heading="__('catalog.display.group_tags')">
            <form wire:submit="saveGroupTags" class="space-y-4">
                {{ $this->tagForm }}
                <x-filament::button type="submit" wire:loading.attr="disabled">{{ __('catalog.display.save_tags') }}</x-filament::button>
                <span role="status" aria-live="polite" data-draft-status="tags" data-dirty="{{ $this->draftStatuses()['tags'] ? 'true' : 'false' }}"
                    x-bind:data-dirty="draftChanged($wire.tagData, $wire.savedTagData) ? 'true' : 'false'"
                    x-text="draftChanged($wire.tagData, $wire.savedTagData) ? @js(__('catalog.display.unsaved')) : @js(__('catalog.display.no_unsaved'))"
                    class="text-sm text-gray-600 dark:text-gray-400">{{ $this->draftStatuses()['tags'] ? __('catalog.display.unsaved') : __('catalog.display.no_unsaved') }}</span>
            </form>
        </x-filament::section>
    @endif
    <x-filament::section :heading="__('catalog.display.vocabulary')" collapsible collapsed>
        <div class="flex flex-wrap gap-2">
            {{ $this->categoryAction }}
            {{ $this->valueAction }}
            {{ $this->removeCategoryAction }}
            {{ $this->removeValueAction }}
        </div>
    </x-filament::section>
</x-filament-panels::page>
