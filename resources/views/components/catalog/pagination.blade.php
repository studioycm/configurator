@props(['products'])
@if ($products->hasPages())
    <nav aria-label="{{ __('catalog.frontend.copy.product_pagination') }}" class="mt-4 flex items-center justify-between gap-4">
        <button type="button" wire:click="goToPage({{ $products->currentPage() - 1 }})" @disabled($products->onFirstPage()) class="min-h-8 rounded-lg border border-zinc-300 px-3 disabled:opacity-40 dark:border-zinc-600">{{ __('catalog.frontend.copy.previous') }}</button>
        <span class="text-sm">{{ __('catalog.frontend.copy.page_page_of_total', ['page' => $products->currentPage(), 'total' => $products->lastPage()]) }}</span>
        <button type="button" wire:click="goToPage({{ $products->currentPage() + 1 }})" @disabled(! $products->hasMorePages()) class="min-h-8 rounded-lg border border-zinc-300 px-3 disabled:opacity-40 dark:border-zinc-600">{{ __('catalog.frontend.copy.next') }}</button>
    </nav>
@endif
