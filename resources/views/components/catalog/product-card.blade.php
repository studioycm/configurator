@props(['product'])
<article class="flex h-full flex-col rounded-xl border border-zinc-300 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
    <p class="font-mono text-sm text-zinc-600 dark:text-zinc-400">{{ $product->product_code }}</p>
    <h3 class="mt-2 text-lg font-semibold leading-7">{{ filled($product->product_name) ? $product->product_name : __('Unnamed product') }}</h3>
    <dl class="my-5 space-y-2 text-sm">
        <div><dt class="text-zinc-500">{{ __('Operating pressure') }}</dt><dd>{{ filled($product->pressure) ? $product->pressure : '—' }}</dd></div>
        <div><dt class="text-zinc-500">{{ __('Connection') }}</dt><dd>{{ filled($product->connection) ? $product->connection : '—' }}</dd></div>
    </dl>
    <a class="mt-auto inline-flex min-h-11 items-center font-medium text-teal-700 underline underline-offset-4 dark:text-teal-300" href="{{ route('catalog.products.show', $product->id) }}">{{ __('View product') }}<span class="sr-only">: {{ $product->product_code }}</span></a>
</article>
