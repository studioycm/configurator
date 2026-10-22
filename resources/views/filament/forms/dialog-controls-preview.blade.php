<div class="catalog-dialog-preview-heading">
    <strong>{{ $title }}</strong>
    <div class="catalog-dialog-width-controls" role="group" aria-label="{{ __('admin.dialogs.width') }}">
        @foreach (['narrower' => 'heroicon-o-arrows-pointing-in', 'wider' => 'heroicon-o-arrows-pointing-out', 'reset_width' => 'heroicon-o-arrow-path'] as $label => $icon)
            <button type="button" disabled aria-label="{{ __('admin.dialogs.'.$label) }}">
                <span class="catalog-dialog-control-icon" aria-hidden="true">{{ svg($icon) }}</span>
                <span class="catalog-dialog-control-label">{{ __('admin.dialogs.'.$label) }}</span>
            </button>
        @endforeach
    </div>
    <span class="catalog-dialog-preview-close" aria-hidden="true">{{ svg('heroicon-o-x-mark') }}</span>
</div>
