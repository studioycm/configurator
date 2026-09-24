<x-slot:breadcrumbs>
    <x-catalog.breadcrumbs :group="$product->group" :include-current="true" compact />
</x-slot:breadcrumbs>

<div>
    <livewire:catalog.product-configurator :product-id="$product->id" :key="'product-configurator-'.$product->id" />
</div>
