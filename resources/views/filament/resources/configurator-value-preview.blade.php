<div data-value-preview class="space-y-1">
    <p class="text-sm font-medium">{{ $name }}</p>
    @if (trim($subtitle) !== '' && $subtitle !== $name)<p data-value-subtitle class="text-sm text-gray-500 dark:text-gray-400">{{ $subtitle }}</p>@endif
    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('configurator.subtitles.preview_help') }}</p>
</div>
