@props(['product', 'group', 'propertyKeys', 'mainGroup' => null])
@php
    $values = collect($propertyKeys)
        ->map(fn (string $key) => $product->properties[$key] ?? null)
        ->filter(fn (mixed $value): bool => is_string($value) && $value !== '');
@endphp
<article class="flex h-full flex-col rounded-xl border border-zinc-300 bg-white px-4 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
    <h3 class="text-lg font-semibold leading-7 wrap-break-word">{{ $product->product_code }}</h3>
    <div class="mt-2 flex flex-col gap-1 text-sm">
        @if ($mainGroup)<p class="text-zinc-600 dark:text-zinc-400">{{ $mainGroup->name }}</p>@endif
        <p class="font-medium">{{ $group->name }}</p>
    </div>
    @if ($values->isNotEmpty())
        <p class="my-2.5 text-sm leading-6 wrap-break-word text-zinc-600 dark:text-zinc-400" aria-label="{{ __('Product properties') }}">{{ $values->implode(', ') }}</p>
    @endif
    <a class="mt-auto inline-flex min-h-7 items-center pt-2 font-medium text-teal-700 underline underline-offset-4 dark:text-teal-300" href="{{ route('catalog.products.show', $product->id) }}">{{ __('View product') }}<span class="sr-only">: {{ $product->product_code }}</span></a>
</article>
