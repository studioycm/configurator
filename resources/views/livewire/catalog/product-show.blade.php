<div>
    <x-catalog.breadcrumbs :group="$product->group" :include-current="true" />
    <livewire:catalog.product-configurator :product-id="$product->id" :key="'product-configurator-'.$product->id" />
</div>
