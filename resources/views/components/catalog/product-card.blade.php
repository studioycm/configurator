@props(['product', 'group', 'mainGroup' => null])
<article class="flex h-full flex-col rounded-xl border border-zinc-300 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
    <h3 class="text-lg font-semibold leading-7 wrap-break-word">{{ $product->product_code }}</h3>
    <div class="mt-2 flex flex-col gap-1 text-sm">
        @if ($mainGroup)<p class="text-zinc-600 dark:text-zinc-400">{{ $mainGroup->name }}</p>@endif
        <p class="font-medium">{{ $group->name }}</p>
    </div>
    <ul class="my-5 grid gap-1 text-sm leading-6 text-zinc-600 dark:text-zinc-400" aria-label="{{ __('Product properties') }}">
        @foreach (\App\Services\CatalogImportParser::propertyKeys() as $key)
            @if (is_string($product->properties[$key] ?? null) && $product->properties[$key] !== '')
                <li wire:key="product-{{ $product->id }}-property-{{ $key }}" class="wrap-break-word">{{ $product->properties[$key] }}</li>
            @endif
        @endforeach
    </ul>
    <a class="mt-auto inline-flex min-h-11 items-center font-medium text-teal-700 underline underline-offset-4 dark:text-teal-300" href="{{ route('catalog.products.show', $product->id) }}">{{ __('View product') }}<span class="sr-only">: {{ $product->product_code }}</span></a>
</article>
