<div class="space-y-3 text-sm">
    @forelse ($notices as $index => $notice)
        <section class="catalog-preview-notice-detail" data-tone="{{ $notice['type'] ?? 'neutral' }}" wire:key="preview-notice-detail-{{ $index }}">
            <h3 class="font-semibold">{{ $notice['title'] }}</h3><p class="mt-1 whitespace-pre-line break-words">{{ $notice['message'] ?? '' }}</p>
            @if (($notice['action'] ?? null) === 'review')<button type="button" class="catalog-preview-text-action mt-2" x-on:click="review()" x-bind:disabled="busy">{{ __('configurator.preview.review_changes') }}</button>
            @elseif (($notice['action'] ?? null) === 'inspect')<button type="button" class="catalog-preview-text-action mt-2" x-on:click="explainConflicts()" x-bind:disabled="busy">{{ __('configurator.preview.view_conflict_details') }}</button>
            @elseif (($notice['action'] ?? null) === 'rebuild')<button type="button" class="catalog-preview-text-action mt-2" x-on:click="rebuild()" x-bind:disabled="busy">{{ __('configurator.preview.rebuild') }}</button>
            @elseif (($notice['action'] ?? null) === 'refresh')<button type="button" class="catalog-preview-text-action mt-2" x-on:click="refreshSavedPreview()" x-bind:disabled="busy">{{ __('configurator.preview.retry_preview_refresh') }}</button>@endif
        </section>
    @empty
        <p>{{ __('configurator.preview.no_notices') }}</p>
    @endforelse
</div>
