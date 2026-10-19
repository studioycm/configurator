<x-filament-panels::page
    x-data="{
        hasDraft() { return !!this.$el.querySelector('[data-tag-dirty=true]') },
        isTableControl(target) { return !!target.closest('.catalog-tag-values .fi-ta-header-toolbar, .catalog-tag-values .fi-ta-filter-indicators, .catalog-tag-values .fi-pagination, .catalog-tag-values .fi-ta-header-cell-sort-btn') },
        blockDraftNavigation(event) {
            event.preventDefault(); event.stopImmediatePropagation();
            new FilamentNotification().id('tag-draft-navigation').title({{ \Illuminate\Support\Js::from(__('tags.finish_rows')) }}).warning().send();
        }
    }"
    x-on:beforeunload.window="if (hasDraft()) { $event.preventDefault(); $event.returnValue = ''; }"
    x-on:beforeinput.capture="if (hasDraft() && isTableControl($event.target)) blockDraftNavigation($event)"
    x-on:keydown.capture="if (hasDraft() && isTableControl($event.target) && !['Tab', 'Shift', 'Control', 'Meta', 'Alt', 'Escape'].includes($event.key)) blockDraftNavigation($event)"
    x-on:click.capture="if (hasDraft()) {
        if ($event.target.closest('[data-tag-requires-clean]') || isTableControl($event.target)) {
            blockDraftNavigation($event);
        } else if ($event.target.closest('[data-tag-transition], .catalog-tag-list .fi-ta-row.fi-clickable, .catalog-tag-list .fi-ta-record')) {
            if (!confirm({{ \Illuminate\Support\Js::from(__('tags.discard_drafts')) }})) { $event.preventDefault(); $event.stopImmediatePropagation(); }
        }
    }">
    <p>{{ __('tags.description') }}</p>
    <div class="catalog-tag-workspace {{ $this->selectedTag !== null || $this->isCreatingTag ? 'has-editor' : '' }}">
        <div class="catalog-tag-list">{{ $this->table }}</div>
        @if ($this->selectedTag !== null || $this->isCreatingTag)
            <livewire:catalog.tag-values :tag="$this->selectedTag" :key="'tag-workspace-'.($this->selectedTag === null ? 'new' : hash('sha256', $this->selectedTag))" />
        @endif
    </div>
</x-filament-panels::page>
