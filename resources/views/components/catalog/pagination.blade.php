@props(['products'])
@if ($products->hasPages())
    <nav aria-label="Product pagination" class="mt-4 flex items-center justify-between gap-4">
        <button type="button" wire:click="goToPage({{ $products->currentPage() - 1 }})" @disabled($products->onFirstPage()) class="min-h-8 rounded-lg border border-zinc-300 px-3 disabled:opacity-40 dark:border-zinc-600">{{ __('Previous') }}</button>
        <span class="text-sm">{{ __('Page :page of :total', ['page' => $products->currentPage(), 'total' => $products->lastPage()]) }}</span>
        <button type="button" wire:click="goToPage({{ $products->currentPage() + 1 }})" @disabled(! $products->hasMorePages()) class="min-h-8 rounded-lg border border-zinc-300 px-3 disabled:opacity-40 dark:border-zinc-600">{{ __('Next') }}</button>
    </nav>
@endif
