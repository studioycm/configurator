@props(['product'])
<div class="space-y-8">
    @foreach (['properties' => 'Product facts', 'parts' => 'Parts', 'extra_data' => 'Preserved source data'] as $bucket => $heading)
        @php
            $facts = $bucket === 'parts' ? collect(range(1, 28))->mapWithKeys(fn ($number) => ['Part'.$number => $product->parts['Part'.$number] ?? ''])->all() : array_slice($product->{$bucket} ?? [], 0, 100, true);
        @endphp
        <section aria-label="{{ __($heading) }}">
            <h2 class="mb-4 text-lg font-semibold">{{ __($heading) }}</h2>
            <dl class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($facts as $key => $value)
                    <div class="grid gap-1 py-3 sm:grid-cols-3 sm:gap-6">
                        <dt class="text-sm font-medium text-zinc-600 dark:text-zinc-400">{{ $key }}</dt>
                        <dd class="min-w-0 whitespace-pre-wrap break-words text-sm sm:col-span-2">{{ $value === '' || $value === null ? '—' : \Illuminate\Support\Str::limit(is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 4000) }}</dd>
                    </div>
                @empty
                    <p class="text-sm text-zinc-500">{{ __('No source facts supplied.') }}</p>
                @endforelse
            </dl>
        </section>
    @endforeach
</div>
