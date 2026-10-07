@props(['product', 'group', 'fields', 'appearance', 'mainGroup' => null])
@php
    $identity = $group->id.'-'.$product->id;
    $properties = $product->properties ?? [];
@endphp
<article {{ $attributes->class(['catalog-product-card']) }}>
    <a href="{{ route('catalog.products.show', $product->id) }}" class="catalog-product-card__link"
        aria-labelledby="catalog-card-title-{{ $identity }}"
        aria-describedby="catalog-card-group-{{ $identity }}{{ $fields === [] ? '' : ' catalog-card-facts-'.$identity }}"
        data-property-columns="{{ $appearance['card_property_columns'] }}"
        data-property-layout="{{ $appearance['card_property_layout'] }}"
        data-property-labels="{{ $appearance['card_show_labels'] ? 'shown' : 'hidden' }}"
        style="--catalog-card-padding-block:{{ $appearance['card_padding_block'] }}px;--catalog-card-padding-inline:{{ $appearance['card_padding_inline'] }}px">
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
            <dl id="catalog-card-facts-{{ $identity }}" aria-label="{{ __('Product properties') }}" class="catalog-product-card__facts">
                @foreach ($fields as $field)
                    @php($value = $properties[$field['key']] ?? null)
                    <div class="catalog-product-card__fact" data-property-key="{{ $field['key'] }}">
                        <dt @class(['sr-only' => ! $appearance['card_show_labels']])>{{ $field['label'] }}</dt>
                        <dd>{{ is_string($value) && $value !== '' ? $value : '—' }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif
    </a>
</article>
