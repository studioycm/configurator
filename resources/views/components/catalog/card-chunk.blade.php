@foreach ($products as $product)
    <div data-product-id="{{ $product->id }}"><x-catalog.product-card :product="$product" :group="$group" :main-group="$mainGroup" :fields="$fields" :appearance="$appearance" :presentation="$presentation" :comparison-enabled="true" /></div>
@endforeach
