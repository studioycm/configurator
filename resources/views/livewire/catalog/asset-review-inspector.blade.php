@php($inspection = $this->inspector())
<aside data-tour="images.review-inspector" class="catalog-images-inspector" aria-label="Review inspector">
    @if($inspection)
        <h3 class="font-semibold">{{ $inspection['title'] }}</h3>
        <p>{{ $inspection['position']['position'] ?? 'Outside filters' }} / {{ $inspection['position']['total'] ?? '—' }} · {{ $inspection['status'] }} · {{ $inspection['included'] ? 'Included' : 'Not included' }}</p>
        <div class="catalog-images-bar">
            <x-filament::button color="gray" x-on:click="command('navigateReviewItem', 'previous')" x-bind:disabled="running || unknown">Previous</x-filament::button>
            <x-filament::button color="gray" x-on:click="command('navigateReviewItem', 'next')" x-bind:disabled="running || unknown">Next</x-filament::button>
        </div>
        @if(($inspection['manual'] ?? false) && $operation === 'folder_matches' && !($applyProgress['accepted'] ?? false))
            <x-filament::button color="gray" x-on:click="command('clearReviewOverride')" x-bind:disabled="running || unknown">Use recipe again</x-filament::button>
        @endif
        <dl><dt>Raw field</dt><dd>{{ $inspection['raw'] ?? '—' }} {{ $inspection['truncated'] ? '(display shortened)' : '' }}</dd>
            <dt>Identifier</dt><dd>{{ $inspection['identifier'] ?? '—' }}</dd><dt>Proposed filename</dt><dd>{{ $inspection['filename'] ?? '—' }}</dd>
            <dt>Reason</dt><dd>{{ str_replace('_', ' ', $inspection['reason']) }}</dd></dl>
        @if($inspection['apply_code'])<p>{{ str_replace('_', ' ', $inspection['apply_code']) }}</p>@endif
        @foreach($inspection['warnings'] as $warning)<p class="catalog-images-notice">{{ str_replace('_', ' ', $warning) }}</p>@endforeach
        <div class="catalog-asset-preview-pair">
            @foreach(['saved_url'=>'Reviewed before','current_url'=>'Saved now','proposed_url'=>'Proposed source image'] as $key=>$caption)
                <figure><figcaption>{{ $caption }}</figcaption>
                    @if($inspection[$key])<img src="{{ $inspection[$key] }}" alt="" x-on:error="$el.hidden=true; $el.nextElementSibling.hidden=false" /><p hidden>Image unavailable</p>
                    @else<p>No image</p>@endif
                </figure>
            @endforeach
        </div>
        <p class="text-sm">{{ $inspection['accepted'] ? 'Apply outcome: '.$inspection['apply_status'] : 'This proposal has not been accepted.' }}</p>
    @else<p>Inspect a review row to compare saved and proposed images.</p>@endif
</aside>
