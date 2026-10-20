<div x-ref="discardAlert" x-cloak x-show="confirmDiscard" role="alert" class="catalog-editor-discard">
    <p x-text="discardRecord ? {{ \Illuminate\Support\Js::from(__('admin.workspace.editor.discard_named', ['record' => ':record'])) }}.replace(':record', discardRecord) : {{ \Illuminate\Support\Js::from(__('admin.workspace.editor.discard_unnamed')) }}"></p>
    @if (filled($discardScope ?? null))
        <p>{{ $discardScope }}</p>
    @endif
    <div class="flex flex-wrap gap-2">
        <x-filament::button color="gray" type="button" x-on:click="keepEditing()">{{ __('catalog.frontend.copy.keep_editing') }}</x-filament::button>
        <x-filament::button color="danger" type="button" x-on:click="discardChanges()">{{ __('admin.workspace.editor.discard_changes') }}</x-filament::button>
    </div>
</div>
