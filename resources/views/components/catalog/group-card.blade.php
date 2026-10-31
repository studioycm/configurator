@props(['card' => null, 'dynamic' => false])
<article class="catalog-model-card" @if (! $dynamic) x-data="{ card: @js($card) }" data-catalog-model-id="{{ $card['id'] }}" data-placement="{{ $card['presentation']['group_image_placement'] }}" data-scale="{{ $card['presentation']['group_image_scale'] }}" @endif :data-placement="card.presentation.group_image_placement" :data-scale="card.presentation.group_image_scale">
    <div class="catalog-model-image" aria-hidden="true">
        <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M12 21h24v18H12zM19 21V9h10v12M9 27H5v8h4m30-8h4v8h-4M17 9h14M19 14h10M18 39v4m12-4v4"/><path d="M20 29h8m-4-4v8"/></svg>
        <span>{{ __('catalog.browse.no_image') }}</span>
    </div>
    <div class="catalog-model-copy">
        <h2><a @if (! $dynamic) href="{{ $card['url'] }}" @endif :href="card.url" :data-catalog-focus="'model-' + card.id" x-text="card.name">{{ $card['name'] ?? '' }}</a></h2>
        <template x-for="content in card.presentation.group_card_content" :key="content">
            <div>
                <p x-show="content === 'description' && card.description" x-text="content === 'description' ? card.description : ''" class="catalog-model-description"></p>
                <p x-show="content === 'legacy_id' && card.legacyId" x-text="content === 'legacy_id' ? card.legacyId : ''" class="catalog-model-reference"></p>
                <p x-show="content === 'product_count'" class="catalog-model-reference"><span x-text="card.productCount"></span> {{ __('catalog.browse.direct_products') }}</p>
                <div x-show="content === 'tags' && card.tags.length" class="catalog-model-tags">
                    <template x-for="tag in card.tags" :key="tag.id"><span :title="tag.categoryLabel" x-text="tag.label"></span></template>
                </div>
            </div>
        </template>
        @if (! $dynamic)
            <noscript>@if ($card['description'] !== null && $card['description'] !== '')<p>{{ $card['description'] }}</p>@endif</noscript>
        @endif
        <a class="catalog-model-open" @if (! $dynamic) href="{{ $card['url'] }}" @endif :href="card.url" :aria-label="@js(__('catalog.browse.open_products')) + ' · ' + card.name">{{ __('catalog.browse.open_products') }} <span aria-hidden="true">→</span></a>
    </div>
</article>
