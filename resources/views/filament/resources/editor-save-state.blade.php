<div class="catalog-editor-save-state">
    <div role="status" aria-live="polite">
        <span x-cloak x-show="{{ $dirty }}" class="catalog-editor-dirty">{{ __('admin.workspace.editor.unsaved') }}</span>
        <span x-show="!({{ $dirty }})">{{ __('admin.workspace.editor.no_unsaved') }}</span>
    </div>
    <p>{{ $scope }}</p>
</div>
