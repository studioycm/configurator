@props(['workspace' => 'attributes'])
<div {{ $attributes }} class="catalog-workspace-split catalog-related-workspace" x-data="Object.assign(workspaceSplit({{ \Illuminate\Support\Js::from(['user' => auth()->id(), 'owner' => $this->getOwnerRecord()->getKey(), 'tab' => $workspace]) }}), editorDrafts(), configuratorDrafts({{ \Illuminate\Support\Js::from($workspace) }}), { fromPreview: false })"
    x-init="$nextTick(() => $dispatch('configurator-preview-editor-ready', {editor: {{ \Illuminate\Support\Js::from($workspace) }} }))"
    x-on:configurator-preview-editor-opened="fromPreview = true"
    x-bind:data-split-stacked="String(stacked)" x-bind:style="{ '--workspace-columns': columns }"
    x-bind:data-draft-dirty="hasPendingDrafts()"
    x-on:configurator-editor-filled="confirmDiscard = false"
    x-on:configurator-option-editor-filled="confirmDiscard = false"
    x-on:beforeunload.window="if (hasPendingDrafts()) { $event.preventDefault(); $event.returnValue = ''; }"
    x-on:click.capture="if (!allowTransition && ((hasPendingDrafts() && $event.target.closest('[data-close-editor]')) || (hasOptionDraft() && $event.target.closest('[data-close-option-editor]')))) { const target = $event.target.closest('[data-close-editor], [data-close-option-editor]'); const editor = $el.querySelector(target.matches('[data-close-option-editor]') ? '[data-preview-value-editor]' : '[data-current-editor]'); requestDiscard($event, target, null, editor?.dataset.editorRecord); }">
    <div class="catalog-related-list min-w-0">{{ $list }}</div>
    <x-catalog.workspace-splitter />
    <div class="catalog-related-pane min-w-0 scroll-mt-6" data-preview-editor-panel tabindex="-1">
        <button type="button" x-cloak x-show="fromPreview{{ $workspace === 'attributes' ? ' && ! $wire.selectedOptionId' : '' }}" class="mb-3 text-xs text-primary-600 underline dark:text-primary-400"
            x-on:click="$dispatch('configurator-select-editor-tab', {editor: 'preview'})">{{ __('configurator.preview.back_to_preview') }}</button>
        @include('filament.resources.editor-discard-alert', ['discardScope' => __('admin.workspace.editor.discard_workspace_scope')])
        {{ $slot }}
    </div>
    <x-filament-panels::unsaved-action-changes-alert />
</div>
