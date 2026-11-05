@props(['group'])
<section data-tour="catalog.comparison-selection" wire:ignore data-comparison-tray tabindex="-1" aria-label="{{ __('catalog.comparison.title') }}" class="sticky bottom-3 z-20 mt-4" x-cloak x-show="comparisonEnabled">
    <div class="space-y-2 rounded-xl border border-slate-300 bg-white p-3 shadow-lg dark:border-zinc-600 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex min-w-0 flex-wrap gap-2">
                <template x-for="item in comparisonItems" :key="item.id">
                    <div class="flex min-w-0 items-center gap-2 rounded-lg border border-slate-300 px-2 py-1 text-sm dark:border-zinc-600">
                        <div class="min-w-0"><span class="break-all font-mono" x-text="item.label"></span><span x-show="!item.inResults" class="block text-xs text-amber-800 dark:text-amber-200">{{ __('catalog.comparison.outside') }}</span></div>
                        <button type="button" @click="removeComparisonProduct(item.id)" :aria-label="@js(__('catalog.comparison.remove')) + ' · ' + item.label" class="rounded px-1 focus-visible:outline-2">×</button>
                    </div>
                </template>
            </div>
            <button type="button" data-comparison-open @click="openComparison()" :disabled="comparisonItems.length < 2 || !comparisonAvailable || comparisonBusy" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-50 dark:bg-blue-600">{{ __('catalog.comparison.compare') }} <span x-text="comparisonItems.length + '/4'"></span></button>
        </div>
        <button type="button" @click="clearComparison()" class="text-sm underline">{{ __('catalog.comparison.clear_exit') }}</button>
        <p x-show="comparisonItems.length < 2" class="text-sm">{{ __('catalog.comparison.choose_more') }}</p>
        <p x-text="comparisonNotice" role="status" class="text-xs text-slate-600 dark:text-zinc-300"></p>
        <p x-show="comparisonError && !comparisonOpen" x-text="comparisonError" role="status" class="text-sm text-red-700 dark:text-red-300"></p>
        <div x-show="comparisonReplacement" class="space-y-2 border-t border-slate-200 pt-2 dark:border-zinc-700" role="group" aria-label="{{ __('catalog.comparison.replace') }}">
            <p class="text-sm">{{ __('catalog.comparison.replace_prompt') }} <strong class="break-all" x-text="comparisonReplacement?.label"></strong>:</p>
            <div class="flex flex-wrap gap-2">
                <template x-for="(item, index) in comparisonItems" :key="item.id"><button type="button" @click="replaceComparisonProduct(index)" class="rounded border border-slate-300 px-2 py-1 text-sm focus-visible:outline-2 dark:border-zinc-600">{{ __('catalog.comparison.replace') }} <span class="break-all" x-text="item.label"></span></button></template>
                <button type="button" @click="cancelComparisonReplacement()" class="rounded border border-slate-300 px-2 py-1 text-sm focus-visible:outline-2 dark:border-zinc-600">{{ __('catalog.comparison.cancel') }}</button>
            </div>
        </div>
    </div>
</section>
<dialog wire:ignore data-comparison-dialog aria-labelledby="comparison-title-{{ $group->id }}" class="m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-5xl overflow-y-auto rounded-xl border border-slate-300 bg-white p-0 text-slate-900 shadow-xl backdrop:bg-slate-950/50 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100">
    <div class="space-y-3 p-4">
        <div class="flex items-start justify-between gap-4">
            <div><h2 id="comparison-title-{{ $group->id }}" class="text-lg font-semibold">{{ __('catalog.comparison.title') }}</h2><p class="text-sm text-slate-600 dark:text-zinc-300">{{ $group->name }}</p></div>
            <button type="button" @click="clearComparison()" class="text-sm underline">{{ __('catalog.comparison.clear_exit') }}</button>
            <button type="button" @click="closeComparison()" class="rounded-lg border border-slate-300 px-3 py-1 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 dark:border-zinc-600">{{ __('catalog.comparison.close') }}</button>
        </div>
        <p x-show="comparisonBusy" role="status">{{ __('catalog.comparison.loading') }}</p>
        <div x-show="comparisonError" role="status" class="space-y-2 text-sm"><p x-text="comparisonError"></p><button type="button" @click="retryComparison()" x-show="comparisonItems.length >= 2" :disabled="comparisonBusy" class="rounded border border-slate-300 px-3 py-1 focus-visible:outline-2 disabled:opacity-50 dark:border-zinc-600">{{ __('catalog.comparison.retry') }}</button></div>
        <div x-show="comparisonResult">
            <p x-show="comparisonResult && !comparisonResult.differences.length && !comparisonResult.same.length" class="mb-3 text-sm">{{ __('catalog.comparison.empty') }}</p>
            <div class="overflow-x-auto" tabindex="0" role="region" aria-label="{{ __('catalog.comparison.title') }}">
                <table class="w-full min-w-[36rem] border-collapse text-sm">
                    <thead><tr>
                        <th scope="col" class="min-w-40 border border-slate-300 p-3 text-start dark:border-zinc-600">{{ __('catalog.comparison.property') }}</th>
                        <template x-for="product in comparisonResult?.products ?? []" :key="product.id">
                            <th scope="col" class="min-w-40 border border-slate-300 p-3 text-start align-top dark:border-zinc-600"><x-catalog.main-image class="mb-2 h-20 w-24" x-effect="setImage(product.mainImage ?? null)" /><span class="break-all font-mono" x-text="product.code"></span></th>
                        </template>
                    </tr></thead>
                    <template x-for="section in {{ \Illuminate\Support\Js::from([['key' => 'differences', 'label' => __('catalog.comparison.differences')], ['key' => 'same', 'label' => __('catalog.comparison.same')]]) }}" :key="section.key">
                        <tbody>
                            <tr><th scope="rowgroup" :colspan="(comparisonResult?.products.length ?? 0) + 1" class="border border-slate-300 bg-slate-100 p-2 text-start font-semibold dark:border-zinc-600 dark:bg-zinc-800" x-text="section.label"></th></tr>
                            <template x-for="row in comparisonResult?.[section.key] ?? []" :key="row.key">
                                <tr><th scope="row" class="break-words border border-slate-300 p-3 text-start font-medium dark:border-zinc-600" x-text="row.label"></th>
                                    <template x-for="(value, column) in row.values" :key="column"><td class="whitespace-pre-wrap break-words border border-slate-300 p-3 align-top dark:border-zinc-600" x-text="value ?? @js(__('catalog.comparison.missing'))"></td></template>
                                </tr>
                            </template>
                            <tr x-show="comparisonResult && !comparisonResult[section.key].length"><td :colspan="(comparisonResult?.products.length ?? 0) + 1" class="border border-slate-300 p-3 text-slate-600 dark:border-zinc-600 dark:text-zinc-300">{{ __('catalog.comparison.empty_section') }}</td></tr>
                        </tbody>
                    </template>
                </table>
            </div>
        </div>
    </div>
</dialog>
