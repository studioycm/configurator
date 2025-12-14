<x-filament-panels::page>
    <div class="space-y-3">

        {{-- Header / codes inline --}}
        <div class="border border-gray-200/60 dark:border-gray-800/70 rounded-md bg-white dark:bg-gray-900 px-4 py-4">
            <div class="flex flex-col gap-2">
                <div class="text-lg font-semibold leading-tight text-gray-900 dark:text-gray-100">
                    {{ $configProfile->productProfile->short_label ?? 'N/A' }}
                </div>

                <div class="text-sm text-gray-700 dark:text-gray-300">
                    {{ $configProfile->productProfile->name ?? $configProfile->name }}
                </div>

                <div class="flex flex-wrap items-center gap-4 text-sm">
                    <div class="flex items-center gap-2 text-sky-900 dark:text-sky-200">
                        <span class="font-semibold">Product Code:</span>
                        <span class="font-mono text-base">{{ $configProfile->productProfile->product_code ?? '—' }}</span>
                    </div>

                    <div class="flex items-center gap-2 text-sky-900 dark:text-sky-200">
                        <span class="font-semibold">Configuration Code:</span>
                        <span class="font-mono text-base">{{ $this->currentCode ?? '—' }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex gap-3 lg:flex-row flex-col lg:items-start">
            {{-- Sidebar with placeholders (1/4 width) --}}
            <div class="w-full lg:w-1/4 shrink-0 space-y-3">
                <div class="border border-gray-200/60 dark:border-gray-800/70 rounded-md bg-white dark:bg-gray-900 p-4 space-y-3">
                    <div class="text-sm font-semibold text-gray-800 dark:text-gray-100">Product / Group Image</div>
                    <div class="aspect-[4/3] w-full rounded-md bg-gray-50 dark:bg-gray-800/80 border border-dashed border-gray-200/50 dark:border-gray-700/80 flex items-center justify-center text-xs text-gray-500 dark:text-gray-400">
                        Image placeholder
                    </div>
                </div>

                <div class="border border-gray-200/60 dark:border-gray-800/70 rounded-md bg-white dark:bg-gray-900 p-4 space-y-2">
                    <div class="text-sm font-semibold text-gray-800 dark:text-gray-100">Group Files</div>
                    <div class="space-y-1 text-xs text-gray-600 dark:text-gray-400">
                        <div class="flex items-center justify-between rounded border border-gray-200/50 dark:border-gray-800/70 px-2 py-1 bg-gray-50 dark:bg-gray-800/70">
                            <span>Catalog PDF (placeholder)</span>
                            <span class="text-primary-600 dark:text-primary-400">Download</span>
                        </div>
                        <div class="flex items-center justify-between rounded border border-gray-200/50 dark:border-gray-800/70 px-2 py-1 bg-gray-50 dark:bg-gray-800/70">
                            <span>Specs Sheet (placeholder)</span>
                            <span class="text-primary-600 dark:text-primary-400">Download</span>
                        </div>
                    </div>
                </div>

                <div class="border border-gray-200/60 dark:border-gray-800/70 rounded-md bg-white dark:bg-gray-900 p-4 space-y-2">
                    <div class="text-sm font-semibold text-gray-800 dark:text-gray-100">Product Files</div>
                    <div class="space-y-1 text-xs text-gray-600 dark:text-gray-400">
                        <div class="flex items-center justify-between rounded border border-gray-200/50 dark:border-gray-800/70 px-2 py-1 bg-gray-50 dark:bg-gray-800/70">
                            <span>Drawing (placeholder)</span>
                            <span class="text-primary-600 dark:text-primary-400">Download</span>
                        </div>
                        <div class="flex items-center justify-between rounded border border-gray-200/50 dark:border-gray-800/70 px-2 py-1 bg-gray-50 dark:bg-gray-800/70">
                            <span>Installation (placeholder)</span>
                            <span class="text-primary-600 dark:text-primary-400">Download</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Attributes + options, inline rows, slim padding (3/4 width) --}}
            <div class="w-full lg:w-3/4 border border-gray-200/60 dark:border-gray-800/70 rounded-md bg-white dark:bg-gray-900 text-sm divide-y divide-gray-100/40 dark:divide-gray-900/60">
                @foreach ($stages as $stage)
                    @php
                        $attrId     = $stage['id'];
                        $selectedId = $selection[$attrId] ?? null;
                        $allowedIds = $allowed[$attrId] ?? [];
                    @endphp

                    <div class="flex items-center gap-3 px-4 py-2.5">
                        {{-- Attribute label --}}
                        <div class="w-56 shrink-0 font-medium text-gray-800 dark:text-gray-100">
                            {{ $stage['label'] }}:
                        </div>

                        {{-- Options, inline --}}
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($stage['options'] as $opt)
                                @php
                                    $isSelected = $selectedId === $opt['id'];
                                    $isEnabled  = in_array($opt['id'], $allowedIds, true);
                                @endphp

                                <button
                                    type="button"
                                    wire:key="stage-{{ $attrId }}-option-{{ $opt['id'] }}"
                                    wire:click="selectOption({{ $attrId }}, {{ $opt['id'] }})"
                                    @disabled(! $isEnabled)
                                    aria-pressed="{{ $isSelected ? 'true' : 'false' }}"
                                    class="inline-flex items-center rounded-full border text-xs px-3 py-1 leading-tight transition-colors duration-100
                                        @if ($isSelected)
                                            bg-primary-600 text-white border-primary-600 shadow-sm
                                        @elseif ($isEnabled)
                                            bg-white text-gray-800 border-gray-300 hover:bg-gray-50
                                            dark:bg-gray-800 dark:text-gray-100 dark:border-gray-600 dark:hover:bg-gray-700
                                        @else
                                            bg-gray-50 text-gray-400 border-gray-200 opacity-60 cursor-not-allowed pointer-events-none
                                            dark:bg-gray-800/60 dark:text-gray-500 dark:border-gray-700
                                        @endif
                                    "
                                >
                                    <span class="font-medium">{{ $opt['label'] }}</span>
                                    <span class="ml-1 text-[0.65rem] font-mono text-gray-500 dark:text-gray-400">
                                        {{ $opt['code'] }}
                                    </span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-filament-panels::page>
