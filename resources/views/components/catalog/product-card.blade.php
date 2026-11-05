@props(['product', 'group', 'fields', 'appearance', 'mainGroup' => null, 'presentation' => null, 'comparisonEnabled' => false, 'mainImage' => null])
@php
    $identity = $group->id.'-'.$product->id;
    $properties = $product->profile_fields ?? [];
    $imagePresentation = $presentation ?? app(\App\Services\CatalogPresentation::class)->defaults();
    $sideImage = in_array($imagePresentation['product_image_placement'], ['left', 'right'], true);
@endphp
<article {{ $attributes->class(['catalog-product-card', 'relative' => $comparisonEnabled]) }}>
    <a href="{{ route('catalog.products.show', $product->id) }}" class="catalog-product-card__link"
        data-catalog-product-url="{{ route('catalog.products.show', $product->id) }}" data-catalog-product-id="{{ $product->id }}" data-catalog-focus="product-{{ $product->id }}"
        :href="productHref($el.dataset.catalogProductUrl, $el.dataset.catalogProductId)"
        data-image-placement="{{ $imagePresentation['product_image_placement'] }}" data-image-scale="{{ $imagePresentation['product_image_scale'] }}"
        aria-labelledby="catalog-card-title-{{ $identity }}"
        aria-describedby="catalog-card-group-{{ $identity }}{{ $fields === [] ? '' : ' catalog-card-facts-'.$identity }}"
        data-property-columns="{{ $appearance['card_property_columns'] }}"
        data-property-layout="{{ $appearance['card_property_layout'] }}"
        data-property-labels="{{ $appearance['card_show_labels'] ? 'shown' : 'hidden' }}"
        style="--catalog-card-padding-block:{{ $appearance['card_padding_block'] }}px;--catalog-card-padding-inline:{{ $appearance['card_padding_inline'] }}px;">
        @if (! $sideImage)
            <x-catalog.main-image :image="$mainImage" class="catalog-product-card__image" />
        @endif
        <div class="catalog-product-card__body">
            <header class="catalog-product-card__header">
                @if ($sideImage)
                    <x-catalog.main-image :image="$mainImage" class="catalog-product-card__image" />
                @endif
                <div class="catalog-product-card__identity">
                    <div class="catalog-product-card__heading">
                        <h3 id="catalog-card-title-{{ $identity }}">{{ $product->product_code }}</h3>
                        <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" class="catalog-product-card__link-icon"><path d="M5 15 15 5M5 5h10v10" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    </div>
                    <div id="catalog-card-group-{{ $identity }}" class="catalog-product-card__groups">
                        @if ($mainGroup)<p>{{ $mainGroup->name }}</p>@endif
                        <p>{{ $group->name }}</p>
                    </div>
                </div>
            </header>
            @if ($fields !== [])
                <dl id="catalog-card-facts-{{ $identity }}" aria-label="{{ __('catalog.frontend.copy.product_properties') }}" class="catalog-product-card__facts">
                    @foreach ($fields as $field)
                        @php($value = $properties[$field['key']] ?? null)
                        <div class="catalog-product-card__fact" data-property-key="{{ $field['key'] }}">
                            <dt @class(['sr-only' => ! $appearance['card_show_labels']])>{{ $field['label'] }}</dt>
                            <dd>{{ is_string($value) && $value !== '' ? $value : '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </div>
    </a>
    @if ($comparisonEnabled)
        <button x-cloak x-show="comparisonEnabled" type="button" data-comparison-product="{{ $product->id }}" :aria-pressed="comparisonIsSelected('{{ $product->id }}')" @click="chooseProductComparison({{ \Illuminate\Support\Js::from((string) $product->id) }}, {{ \Illuminate\Support\Js::from($product->product_code) }})"
            class="absolute -top-2 -right-2 z-10 flex size-7 items-center justify-center rounded-full border border-slate-400 bg-white text-slate-700 shadow-sm aria-pressed:border-blue-600 aria-pressed:bg-blue-700 aria-pressed:text-white focus-visible:outline-2 dark:bg-zinc-900 dark:text-zinc-200"
            :aria-label="(comparisonIsSelected('{{ $product->id }}') ? @js(__('catalog.comparison.remove')) : @js(__('catalog.comparison.add'))) + ' · ' + {{ \Illuminate\Support\Js::from($product->product_code) }}">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" class="size-4"><path d="M4 4h6v16H4zM14 4h6v16h-6zM10 12h4" stroke-width="1.5" /></svg>
        </button>
    @endif
</article>
