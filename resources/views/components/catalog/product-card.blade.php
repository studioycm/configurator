@props(['product', 'group', 'fields', 'appearance', 'mainGroup' => null, 'presentation' => null, 'comparisonEnabled' => false])
@php
    $identity = $group->id.'-'.$product->id;
    $properties = $product->profile_fields ?? [];
    $imagePresentation = $presentation ?? app(\App\Services\CatalogPresentation::class)->defaults();
@endphp
<article {{ $attributes->class(['catalog-product-card', 'flex flex-col gap-1' => $comparisonEnabled]) }}>
    <a href="{{ route('catalog.products.show', $product->id) }}" class="catalog-product-card__link"
        data-catalog-product-url="{{ route('catalog.products.show', $product->id) }}" data-catalog-product-id="{{ $product->id }}" data-catalog-focus="product-{{ $product->id }}"
        :href="productHref($el.dataset.catalogProductUrl, $el.dataset.catalogProductId)"
        data-image-placement="{{ $imagePresentation['product_image_placement'] }}" data-image-scale="{{ $imagePresentation['product_image_scale'] }}"
        aria-labelledby="catalog-card-title-{{ $identity }}"
        aria-describedby="catalog-card-group-{{ $identity }}{{ $fields === [] ? '' : ' catalog-card-facts-'.$identity }}"
        data-property-columns="{{ $appearance['card_property_columns'] }}"
        data-property-layout="{{ $appearance['card_property_layout'] }}"
        data-property-labels="{{ $appearance['card_show_labels'] ? 'shown' : 'hidden' }}"
        style="--catalog-card-padding-block:{{ $appearance['card_padding_block'] }}px;--catalog-card-padding-inline:{{ $appearance['card_padding_inline'] }}px;@if ($comparisonEnabled)height:auto;flex:1;@endif">
        <x-catalog.image-placeholder class="catalog-product-card__image" />
        <div class="catalog-product-card__body">
            <header class="catalog-product-card__header">
                <div class="catalog-product-card__heading">
                    <h3 id="catalog-card-title-{{ $identity }}">{{ $product->product_code }}</h3>
                    <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" class="catalog-product-card__link-icon"><path d="M5 15 15 5M5 5h10v10" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                </div>
                <div id="catalog-card-group-{{ $identity }}" class="catalog-product-card__groups">
                    @if ($mainGroup)<p>{{ $mainGroup->name }}</p>@endif
                    <p>{{ $group->name }}</p>
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
        <button type="button" data-comparison-product="{{ $product->id }}" :aria-pressed="comparisonIsSelected('{{ $product->id }}')" @click="chooseProductComparison({{ \Illuminate\Support\Js::from((string) $product->id) }}, {{ \Illuminate\Support\Js::from($product->product_code) }})"
            class="shrink-0 rounded-md border border-slate-300 bg-white px-3 py-1 text-sm text-slate-700 aria-pressed:border-blue-600 aria-pressed:text-blue-700 focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-50 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200 dark:aria-pressed:text-blue-300"
            :aria-label="(comparisonIsSelected('{{ $product->id }}') ? @js(__('catalog.comparison.remove')) : @js(__('catalog.comparison.add'))) + ' · ' + {{ \Illuminate\Support\Js::from($product->product_code) }}"
            x-text="comparisonIsSelected('{{ $product->id }}') ? @js(__('catalog.comparison.remove')) : @js(__('catalog.comparison.add'))">{{ __('catalog.comparison.add') }}</button>
    @endif
</article>
