@props(['product', 'propertyKeys' => null, 'buckets' => ['properties' => __('configurator.copy.product_facts'), 'parts' => __('catalog.products.buckets.parts'), 'extra_data' => __('configurator.copy.preserved_source_data')]])
<div class="space-y-4">
    @foreach ($buckets as $bucket => $heading)
        @php
            $facts = $bucket === 'parts' ? collect(range(1, 28))->mapWithKeys(fn ($number) => ['Part'.$number => $product->parts['Part'.$number] ?? ''])->all() : array_slice($product->{$bucket} ?? [], 0, 100, true);
            if ($bucket === 'properties' && $propertyKeys !== null) {
                $facts = array_intersect_key($facts, array_flip($propertyKeys));
            }
        @endphp
        <section aria-label="{{ $heading }}">
            <h2 class="mb-2 text-sm font-semibold">{{ $heading }}</h2>
            <dl class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($facts as $key => $value)
                    <div class="grid grid-flow-col grid-cols-[minmax(0,1fr)_minmax(0,2fr)] content-stretch items-baseline justify-between justify-items-stretch gap-x-3 py-1.5">
                        <dt class="min-w-0 break-words text-sm font-medium text-zinc-600 dark:text-zinc-400">{{ str_replace('_', ' ', $key) }}</dt>
                        <dd class="min-w-0 whitespace-pre-wrap break-words text-right text-sm">{{ $value === '' || $value === null ? '—' : \Illuminate\Support\Str::limit(is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 4000) }}</dd>
                    </div>
                @empty
                    <p class="text-sm text-zinc-500">{{ __('catalog.frontend.copy.no_source_facts_supplied') }}</p>
                @endforelse
            </dl>
        </section>
    @endforeach
</div>
