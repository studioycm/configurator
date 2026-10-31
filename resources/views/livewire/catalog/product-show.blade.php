<x-slot:breadcrumbs>
    <x-catalog.breadcrumbs :group="$product->group" :include-current="true" :catalog-return-url="$catalogReturnUrl" :group-return-url="$groupReturnUrl" compact />
</x-slot:breadcrumbs>

<div>
    @if ($journeyNotice)<p role="status" class="text-sm text-zinc-600 dark:text-zinc-400">{{ $journeyNotice }}</p>@endif
    <livewire:catalog.product-configurator :product-id="$product->id" :key="'product-configurator-'.$product->id" />
</div>
