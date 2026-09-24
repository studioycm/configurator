<div>
    <x-catalog.breadcrumbs :group="$product->group" :include-current="true" />
    <p class="text-sm font-medium text-zinc-500">{{ __('Product Code') }}</p>
    <p class="mt-1 font-mono text-lg">{{ $product->product_code }}</p>

    @if ($product->description)<p class="mt-5 max-w-4xl whitespace-pre-line leading-7 text-zinc-600 dark:text-zinc-400">{{ $product->description }}</p>@endif
    <livewire:catalog.product-configurator :product-id="$product->id" :key="'product-configurator-'.$product->id" />
    <x-catalog.product-facts :product="$product" />
</div>
