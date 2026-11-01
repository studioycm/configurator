@props(['card' => null, 'dynamic' => false])
<article class="catalog-model-card" @if (! $dynamic) x-data="{ card: @js($card) }" data-catalog-model-id="{{ $card['id'] }}" data-placement="{{ $card['presentation']['group_image_placement'] }}" data-scale="{{ $card['presentation']['group_image_scale'] }}" @endif :data-placement="card.presentation.group_image_placement" :data-scale="card.presentation.group_image_scale">
    <div class="catalog-model-image" aria-hidden="true">
        <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M12 21h24v18H12zM19 21V9h10v12M9 27H5v8h4m30-8h4v8h-4M17 9h14M19 14h10M18 39v4m12-4v4"/><path d="M20 29h8m-4-4v8"/></svg>
        <span>{{ __('catalog.browse.no_image') }}</span>
    </div>
    <div class="catalog-model-copy">
        <h2><a @if (! $dynamic) href="{{ $card['url'] }}" @endif :href="modelHref(card)" :data-catalog-focus="'model-' + card.id" x-text="card.name">{{ $card['name'] ?? '' }}</a></h2>
        @if ($dynamic)
            <p class="catalog-model-reference" x-show="card.ancestors.length" x-text="card.ancestors.map(ancestor => ancestor.name).join(' / ')"></p>
        @else
            @if ($card['ancestors'] !== [])<p class="catalog-model-reference">{{ implode(' / ', array_column($card['ancestors'], 'name')) }}</p>@endif
        @endif
        @if ($dynamic)
        <template x-for="content in card.presentation.group_card_content" :key="content">
            <div>
                <p x-show="content === 'description' && card.description" x-text="content === 'description' ? card.description : ''" class="catalog-model-description"></p>
                <p x-show="content === 'legacy_id' && card.legacyId" x-text="content === 'legacy_id' ? card.legacyId : ''" class="catalog-model-reference"></p>
                <p x-show="content === 'product_count'" class="catalog-model-reference"><span x-text="card.productCount"></span> {{ __('catalog.browse.direct_products') }}</p>
            </div>
        </template>
        <div x-show="card.presentation.group_card_attribute_keys.length" data-group-card-attributes role="group" aria-label="{{ __('catalog.group_attributes.title') }}">
            <p class="catalog-model-reference" x-show="card.attributesStatus !== 'ready' || !card.attributes.length" x-text="card.attributesStatus === 'unavailable' ? @js(__('catalog.browse.attributes_unavailable')) : @js(__('catalog.browse.attributes_missing'))"></p>
            <dl><template x-for="attribute in card.attributes" :key="attribute.key"><div><dt :class="{'sr-only': !card.presentation.group_card_attribute_labels}" x-text="attribute.label"></dt><dd><template x-for="value in attribute.values" :key="value"><span class="inline-block whitespace-pre-wrap mr-2" x-text="value"></span></template></dd></div></template></dl>
        </div>
        <div x-show="card.presentation.group_card_content.includes('tags') && card.tags.length" class="catalog-model-tags" data-group-card-tags>
            <template x-for="tag in card.tags" :key="tag.id"><span :title="tag.categoryLabel" x-text="tag.label"></span></template>
        </div>
        @else
            @foreach ($card['presentation']['group_card_content'] as $content)
                @if ($content === 'description' && $card['description'] !== null && $card['description'] !== '')<p class="catalog-model-description">{{ $card['description'] }}</p>@endif
                @if ($content === 'legacy_id' && $card['legacyId'] !== null && $card['legacyId'] !== '')<p class="catalog-model-reference">{{ $card['legacyId'] }}</p>@endif
                @if ($content === 'product_count')<p class="catalog-model-reference">{{ $card['productCount'] }} {{ __('catalog.browse.direct_products') }}</p>@endif
            @endforeach
            @if ($card['presentation']['group_card_attribute_keys'] !== [])
                <div data-group-card-attributes role="group" aria-label="{{ __('catalog.group_attributes.title') }}">
                    @if ($card['attributesStatus'] !== 'ready' || $card['attributes'] === [])<p class="catalog-model-reference">{{ __('catalog.browse.attributes_'.($card['attributesStatus'] === 'unavailable' ? 'unavailable' : 'missing')) }}</p>@endif
                    <dl>@foreach ($card['attributes'] as $attribute)<div><dt @class(['sr-only' => ! $card['presentation']['group_card_attribute_labels']])>{{ $attribute['label'] }}</dt><dd>@foreach ($attribute['values'] as $value)<span class="inline-block whitespace-pre-wrap mr-2">{{ $value }}</span>@endforeach</dd></div>@endforeach</dl>
                </div>
            @endif
            @if (in_array('tags', $card['presentation']['group_card_content'], true) && $card['tags'] !== [])
                <div class="catalog-model-tags" data-group-card-tags>@foreach ($card['tags'] as $tag)<span title="{{ $tag['categoryLabel'] }}">{{ $tag['label'] }}</span>@endforeach</div>
            @endif
        @endif
        <a class="catalog-model-open" @if (! $dynamic) href="{{ $card['url'] }}" @endif :href="modelHref(card)" :data-catalog-focus="'model-' + card.id" :aria-label="@js(__('catalog.browse.open_products')) + ' · ' + card.name">{{ __('catalog.browse.open_products') }} <span aria-hidden="true">→</span></a>
    </div>
</article>
