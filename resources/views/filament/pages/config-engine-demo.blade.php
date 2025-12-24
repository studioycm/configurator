<x-filament-panels::page>
    <x-filament-actions::modals />
    <div class="flex flex-col lg:flex-row gap-3">
        <aside class="w-full lg:w-80 shrink-0 space-y-4">
            <div class="border border-gray-200/60 dark:border-gray-800/70 rounded-xl bg-white dark:bg-gray-900 p-3 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="size-10 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-sm font-semibold text-gray-700 dark:text-gray-200">
                        {{ str(auth()->user()?->name ?? 'Guest')->substr(0, 1)->upper() }}
                    </div>
                    <div class="min-w-0">
                        <div class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">
                            {{ auth()->user()?->name ?? 'Guest' }}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 truncate">
                            {{ $this->product?->short_label ?? 'D-060 Series' }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="border border-gray-200/60 dark:border-gray-800/70 rounded-xl bg-white dark:bg-gray-900 p-3 shadow-sm space-y-2">
                <div class="flex items-center justify-between">
                    <div class="text-xs font-semibold tracking-wide text-gray-500 dark:text-gray-400">TERRITORY</div>
                    <button
                        type="button"
                        wire:click="mountAction('editContext')"
                        class="text-xs font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300"
                    >
                        Change
                    </button>
                </div>

                <div class="flex flex-wrap gap-2">
                    <span class="inline-flex items-center rounded-full bg-gray-100 dark:bg-gray-800 px-2.5 py-1 text-xs font-medium text-gray-700 dark:text-gray-200">
                        {{ $this->territory }}
                    </span>
                </div>
            </div>

            <div class="border border-gray-200/60 dark:border-gray-800/70 rounded-xl bg-white dark:bg-gray-900 p-3 shadow-sm space-y-2">
                <div class="flex items-center justify-between">
                    <div class="text-xs font-semibold tracking-wide text-gray-500 dark:text-gray-400">APPLICATION</div>
                    <button
                        type="button"
                        wire:click="mountAction('editContext')"
                        class="text-xs font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300"
                    >
                        Change
                    </button>
                </div>

                <div class="flex flex-wrap gap-2">
                    <span class="inline-flex items-center rounded-full bg-gray-100 dark:bg-gray-800 px-2.5 py-1 text-xs font-medium text-gray-700 dark:text-gray-200">
                        {{ $this->application }}
                    </span>
                </div>
            </div>
        </aside>

        <div x-data="{ tab: 'configurator' }" class="flex-1 space-y-3">
            <div class="border border-gray-200/60 dark:border-gray-800/70 rounded-xl bg-white dark:bg-gray-900 px-4 py-4 shadow-sm">
                <div class="flex flex-col gap-2">
                    <div class="text-lg font-semibold leading-tight text-gray-900 dark:text-gray-100">
                        {{ $this->product?->short_label ?? 'N/A' }}
                    </div>

                    <div class="text-sm text-gray-700 dark:text-gray-300">
                        {{ $this->product?->name ?? $configProfile->name }}
                    </div>

                    <div class="flex flex-col gap-4 text-sm">
                        <div class="flex items-center gap-2 text-sky-900 dark:text-sky-200">
                            <span class="font-semibold">Product Code:</span>
                            <span class="font-mono text-xl">{{ $this->product?->product_code ?? '—' }}</span>
                        </div>

                        <div class="flex items-center gap-2 text-sky-900 dark:text-sky-200">
                            <span class="font-semibold">Configuration Code:</span>
                            <span class="font-mono text-xl">{{ $this->currentCode ?? '—' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <x-filament::tabs>
                <x-filament::tabs.item
                    alpine-active="tab === 'configurator'"
                    x-on:click="tab = 'configurator'"
                    icon="heroicon-o-cog-6-tooth"
                >
                    Configurator
                </x-filament::tabs.item>

                <x-filament::tabs.item
                    alpine-active="tab === 'details'"
                    x-on:click="tab = 'details'"
                    icon="heroicon-o-information-circle"
                >
                    Product Details
                </x-filament::tabs.item>

                <x-filament::tabs.item
                    alpine-active="tab === 'information'"
                    x-on:click="tab = 'information'"
                    icon="heroicon-o-document-text"
                >
                    Information
                </x-filament::tabs.item>

                <x-filament::tabs.item
                    alpine-active="tab === 'complimentary'"
                    x-on:click="tab = 'complimentary'"
                    icon="heroicon-o-sparkles"
                >
                    Complimentary
                </x-filament::tabs.item>
            </x-filament::tabs>

            <div x-show="tab === 'configurator'" x-cloak class="space-y-3">
                <div class="grid grid-cols-4 gap-3 lg:items-start">
                    <div class="col-span-4 lg:col-span-1 space-y-3">
                        {{ $this->groupInfolist }}
                    </div>

                    <div class="col-span-4 lg:col-span-3">
                        {{ $this->form }}
                    </div>
                </div>
            </div>

            <div x-show="tab === 'details'" x-cloak class="space-y-3">
                <div class="grid grid-cols-7 gap-3">
                    {{-- Parts (3fr) --}}
                    <div class="col-span-3 border border-gray-200/60 dark:border-gray-800/70 rounded-xl bg-white dark:bg-gray-900 p-3 shadow-sm space-y-2">
                        <div class="text-sm font-semibold text-gray-800 dark:text-gray-100">Parts</div>

                        @if ($this->demoConfiguration && $this->demoConfiguration->configurationParts->isNotEmpty())
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="text-left text-xs text-gray-500 dark:text-gray-400">
                                            <th class="py-2 pr-3">No.</th>
                                            <th class="py-2 pr-3">Part</th>
                                            <th class="py-2">Material</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100/60 dark:divide-gray-800/60">
                                        @foreach ($this->demoConfiguration->configurationParts->sortBy('part_number') as $line)
                                            <tr>
                                                <td class="py-2 pr-3 text-gray-600 dark:text-gray-400">{{ $line->part_number }}</td>
                                                <td class="py-2 pr-3 text-gray-900 dark:text-gray-100">{{ $line->label ?? ($line->part?->name ?? '—') }}</td>
                                                <td class="py-2 text-gray-700 dark:text-gray-200">{{ $line->material ?? ($line->part?->default_material ?? '—') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-gray-500 dark:text-gray-400 text-sm">No parts yet.</div>
                        @endif
                    </div>

                    {{-- Stacked: Specifications, Dimensions (2fr) --}}
                    <div class="col-span-2 space-y-3">
                        {{-- Specifications --}}
                        <div class="border border-gray-200/60 dark:border-gray-800/70 rounded-xl bg-white dark:bg-gray-900 p-3 shadow-sm space-y-2">
                            <div class="text-sm font-semibold text-gray-800 dark:text-gray-100">Specifications</div>

                            @if ($this->specifications->isNotEmpty())
                                <div class="space-y-2">
                                    @foreach ($this->specifications as $line)
                                        <div class="flex items-start justify-between gap-4">
                                            <div class="text-xs text-gray-600 dark:text-gray-400">{{ $line->key }}</div>
                                            <div class="text-xs font-medium text-gray-900 dark:text-gray-100 text-right">{{ $line->value }}{{ $line->unit ? ' ' . $line->unit : '' }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-gray-500 dark:text-gray-400 text-sm">No specifications yet.</div>
                            @endif
                        </div>

                        {{-- Dimensions --}}
                        <div class="border border-gray-200/60 dark:border-gray-800/70 rounded-xl bg-white dark:bg-gray-900 p-3 shadow-sm space-y-2">
                            <div class="text-sm font-semibold text-gray-800 dark:text-gray-100">Dimensions</div>

                            @if ($this->dimensions->isNotEmpty())
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr class="text-left text-xs text-gray-500 dark:text-gray-400">
                                                <th class="py-2 pr-3">Key</th>
                                                <th class="py-2">Value</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100/60 dark:divide-gray-800/60">
                                            @foreach ($this->dimensions as $line)
                                                <tr>
                                                    <td class="py-2 pr-3 text-gray-900 dark:text-gray-100 font-medium">{{ $line->key }}</td>
                                                    <td class="py-2 text-gray-700 dark:text-gray-200">{{ $line->value }}{{ $line->unit ? ' ' . $line->unit : '' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="text-gray-500 dark:text-gray-400 text-sm">No dimensions yet.</div>
                            @endif
                        </div>
                    </div>

                    <div class="col-span-1 space-y-3">
                        {{ $this->productInfolist }}
                    </div>

                </div>
            </div>

            <div x-show="tab === 'information'" x-cloak class="space-y-3">
                <div class="border border-gray-200/60 dark:border-gray-800/70 rounded-xl bg-white dark:bg-gray-900 p-4 shadow-sm space-y-3">
                    <div class="text-sm font-semibold text-gray-800 dark:text-gray-100">Educational Info (placeholder)</div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                    <div class="rounded-xl border border-gray-200/60 dark:border-gray-800/70 p-4 bg-gray-50 dark:bg-gray-800/50">
                        <div class="font-medium text-gray-900 dark:text-gray-100">What is a combination air valve?</div>
                        <div class="text-sm text-gray-600 dark:text-gray-300 mt-1">Short educational paragraph placeholder. Replace with real content later.</div>
                    </div>
                    <div class="rounded-xl border border-gray-200/60 dark:border-gray-800/70 p-4 bg-gray-50 dark:bg-gray-800/50">
                        <div class="font-medium text-gray-900 dark:text-gray-100">Typical installation locations</div>
                        <div class="text-sm text-gray-600 dark:text-gray-300 mt-1">Short educational paragraph placeholder. Replace with real content later.</div>
                    </div>
                    <div class="rounded-xl border border-gray-200/60 dark:border-gray-800/70 p-4 bg-gray-50 dark:bg-gray-800/50">
                        <div class="font-medium text-gray-900 dark:text-gray-100">Maintenance highlights</div>
                        <div class="text-sm text-gray-600 dark:text-gray-300 mt-1">Short educational paragraph placeholder. Replace with real content later.</div>
                    </div>
                    <div class="rounded-xl border border-gray-200/60 dark:border-gray-800/70 p-4 bg-gray-50 dark:bg-gray-800/50">
                        <div class="font-medium text-gray-900 dark:text-gray-100">Safety notes</div>
                        <div class="text-sm text-gray-600 dark:text-gray-300 mt-1">Short educational paragraph placeholder. Replace with real content later.</div>
                    </div>
                </div>

                <div class="overflow-x-auto mt-2">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-gray-500 dark:text-gray-400">
                                <th class="py-2 pr-3">Key</th>
                                <th class="py-2">Value</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100/60 dark:divide-gray-800/60">
                            <tr>
                                <td class="py-2 pr-3 text-gray-900 dark:text-gray-100">Topic</td>
                                <td class="py-2 text-gray-700 dark:text-gray-200">Placeholder</td>
                            </tr>
                            <tr>
                                <td class="py-2 pr-3 text-gray-900 dark:text-gray-100">Audience</td>
                                <td class="py-2 text-gray-700 dark:text-gray-200">Placeholder</td>
                            </tr>
                            <tr>
                                <td class="py-2 pr-3 text-gray-900 dark:text-gray-100">Notes</td>
                                <td class="py-2 text-gray-700 dark:text-gray-200">Placeholder</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                </div>
            </div>

            <div x-show="tab === 'complimentary'" x-cloak class="space-y-3">
                <div class="border border-gray-200/60 dark:border-gray-800/70 rounded-xl bg-white dark:bg-gray-900 p-4 shadow-sm">
                    <div class="text-sm font-semibold text-gray-800 dark:text-gray-100">Additional Info</div>
                    <div class="text-sm text-gray-600 dark:text-gray-300 mt-2">
                        Placeholder for additional content. We can later include approvals, RFQ notes, revision history, or commercial information.
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
