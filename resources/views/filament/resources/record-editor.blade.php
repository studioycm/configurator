<x-filament-panels::page x-data="{ savedDraft: JSON.stringify({{ $this->editorDraftStateExpression() }}) }"
    data-editor-record="{{ strip_tags((string) $this->getTitle()) }}"
    x-bind:data-draft-dirty="JSON.stringify({{ $this->editorDraftStateExpression() }}) !== savedDraft"
    x-on:catalog-editor-saved="savedDraft = JSON.stringify({{ $this->editorDraftStateExpression() }})">
    @if (property_exists($this, 'batchEditorStale') && $this->batchEditorStale)
        <div role="alert" class="text-sm text-warning-600">
            <p>{{ $errors->first('data.group_classification') ?: __('admin.workspace.drafts.record_changed') }}</p>
            <x-filament::button color="warning" wire:click="reloadBatchEditor">{{ __('admin.workspace.copy.reload_saved_record') }}</x-filament::button>
        </div>
    @endif
    {{ $this->content }}
</x-filament-panels::page>
