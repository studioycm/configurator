<x-filament-panels::page>
    <div class="flex flex-col lg:flex-row gap-4">
        <aside class="w-full lg:w-80 shrink-0 space-y-4">
            <div class="border border-gray-200/60 dark:border-gray-800/70 rounded-xl bg-white dark:bg-gray-900 p-4 shadow-sm">
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

            <div class="border border-gray-200/60 dark:border-gray-800/70 rounded-xl bg-white dark:bg-gray-900 p-4 shadow-sm space-y-3">
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

            <div class="border border-gray-200/60 dark:border-gray-800/70 rounded-xl bg-white dark:bg-gray-900 p-4 shadow-sm space-y-3">
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

        <div x-data="{ tab: 'configurator' }" class="flex-1 space-y-4">
            <div class="border border-gray-200/60 dark:border-gray-800/70 rounded-xl bg-white dark:bg-gray-900 px-5 py-5 shadow-sm">
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

            <div class="border border-gray-200/60 dark:border-gray-800/70 rounded-xl bg-white dark:bg-gray-900 p-2 shadow-sm">
                <div class="flex flex-wrap gap-2">
                    <button type="button" @click="tab = 'configurator'"
                        class="px-3 py-2 rounded-lg text-sm font-medium transition"
                        :class="tab === 'configurator' ? 'bg-primary-600 text-white shadow-sm' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800'">
                        Configurator
                    </button>
                    <button type="button" @click="tab = 'details'"
                        class="px-3 py-2 rounded-lg text-sm font-medium transition"
                        :class="tab === 'details' ? 'bg-primary-600 text-white shadow-sm' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800'">
                        Product Details
                    </button>
                    <button type="button" @click="tab = 'information'"
                        class="px-3 py-2 rounded-lg text-sm font-medium transition"
                        :class="tab === 'information' ? 'bg-primary-600 text-white shadow-sm' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800'">
                        Information
                    </button>
                    <button type="button" @click="tab = 'complimentary'"
                        class="px-3 py-2 rounded-lg text-sm font-medium transition"
                        :class="tab === 'complimentary' ? 'bg-primary-600 text-white shadow-sm' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800'">
                        Complimentary
                    </button>
                </div>
            </div>

        <div x-show="tab === 'configurator'" x-cloak class="space-y-4">
            <div class="flex gap-4 flex-row-reverse lg:items-start">
                <div class="w-full lg:w-1/4 shrink-0 space-y-4">
                    <div class="border border-gray-200/60 dark:border-gray-800/70 rounded-xl bg-white dark:bg-gray-900 p-4 shadow-sm space-y-3">
                        <div class="text-sm font-semibold text-gray-800 dark:text-gray-100">Group Image</div>

                        @if ($this->groupMainImagePath)
                            <img
                                src="{{ asset($this->groupMainImagePath) }}"
                                alt="{{ $this->group?->name ?? 'Group image' }}"
                                class="aspect-[4/3] w-full rounded-lg object-cover border border-gray-200/60 dark:border-gray-800/70"
                            />
                        @else
                            <div class="aspect-[4/3] w-full rounded-lg bg-gray-50 dark:bg-gray-800/80 border border-dashed border-gray-200/50 dark:border-gray-700/80 flex items-center justify-center text-xs text-gray-500 dark:text-gray-400">
                                Image placeholder
                            </div>
                        @endif
                    </div>

                    <div class="border border-gray-200/60 dark:border-gray-800/70 rounded-xl bg-white dark:bg-gray-900 p-4 shadow-sm space-y-2">
                        <div class="text-sm font-semibold text-gray-800 dark:text-gray-100">Group Files</div>

                        <div class="space-y-2 text-xs">
                            @forelse ($this->groupFiles as $file)
                                <a
                                    href="{{ asset($file->file_path) }}"
                                    target="_blank"
                                    rel="noopener"
                                    class="flex items-center justify-between rounded-lg border border-gray-200/60 dark:border-gray-800/70 px-3 py-2 bg-gray-50 hover:bg-gray-100 dark:bg-gray-800/70 dark:hover:bg-gray-800 transition"
                                >
                                    <span class="text-gray-700 dark:text-gray-200">{{ $file->title }}</span>
                                    <span class="text-primary-600 dark:text-primary-400">Open</span>
                                </a>
                            @empty
                                <div class="text-gray-500 dark:text-gray-400">No files yet.</div>
                            @endforelse
                        </div>
                    </div>

                    <div class="border border-gray-200/60 dark:border-gray-800/70 rounded-xl bg-white dark:bg-gray-900 p-4 shadow-sm space-y-2">
                        <div class="text-sm font-semibold text-gray-800 dark:text-gray-100">Product Files</div>

                        <div class="space-y-2 text-xs">
                            @forelse ($this->productFiles as $file)
                                <a
                                    href="{{ asset($file->file_path) }}"
                                    target="_blank"
                                    rel="noopener"
                                    class="flex items-center justify-between rounded-lg border border-gray-200/60 dark:border-gray-800/70 px-3 py-2 bg-gray-50 hover:bg-gray-100 dark:bg-gray-800/70 dark:hover:bg-gray-800 transition"
                                >
                                    <span class="text-gray-700 dark:text-gray-200">{{ $file->title }}</span>
                                    <span class="text-primary-600 dark:text-primary-400">Open</span>
                                </a>
                            @empty
                                <div class="text-gray-500 dark:text-gray-400">No files yet.</div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="w-full lg:w-3/4 border border-gray-200/60 dark:border-gray-800/70 rounded-xl bg-white dark:bg-gray-900 text-sm divide-y divide-gray-100/40 dark:divide-gray-900/60 shadow-sm">
                    @foreach ($stages as $stage)
                        @php
                            $attrId     = $stage['id'];
                            $selectedId = $selection[$attrId] ?? null;
                            $allowedIds = $allowed[$attrId] ?? [];
                        @endphp

                        <div class="flex items-start gap-3 px-5 py-3">
                            <div class="w-56 shrink-0 font-medium text-gray-800 dark:text-gray-100 pt-1">
                                {{ $stage['label'] }}
                            </div>

                            <div class="flex flex-wrap gap-2">
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
                                        class="inline-flex items-center rounded-full border text-xs px-3 py-1.5 leading-tight transition-colors duration-100
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

            <div x-show="tab === 'details'" x-cloak class="space-y-4">
                <div class="border border-gray-200/60 dark:border-gray-800/70 rounded-xl bg-white dark:bg-gray-900 p-4 shadow-sm">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div class="space-y-1">
                            <div class="text-sm font-semibold text-gray-800 dark:text-gray-100">Product Details</div>
                            <div class="text-xs text-gray-600 dark:text-gray-400">
                                Territory: <span class="font-medium text-gray-800 dark:text-gray-200">{{ $this->territory }}</span>
                                · Application: <span class="font-medium text-gray-800 dark:text-gray-200">{{ $this->application }}</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="text-xs text-gray-500 dark:text-gray-400">Config</span>
                            <span class="font-mono text-sm text-gray-900 dark:text-gray-100">{{ $this->demoConfiguration?->configuration_code ?? '—' }}</span>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div class="lg:col-span-2 border border-gray-200/60 dark:border-gray-800/70 rounded-xl bg-white dark:bg-gray-900 p-4 shadow-sm space-y-3">
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

                    <div class="lg:col-span-1 space-y-4">
                        <div class="border border-gray-200/60 dark:border-gray-800/70 rounded-xl bg-white dark:bg-gray-900 p-4 shadow-sm space-y-3">
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

                        <div class="border border-gray-200/60 dark:border-gray-800/70 rounded-xl bg-white dark:bg-gray-900 p-4 shadow-sm space-y-3">
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
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div class="border border-gray-200/60 dark:border-gray-800/70 rounded-xl bg-white dark:bg-gray-900 p-4 shadow-sm space-y-3">
                        <div class="text-sm font-semibold text-gray-800 dark:text-gray-100">Images</div>

                        <div class="grid grid-cols-2 gap-3">
                            @foreach (collect([
                                $this->group?->mainImage,
                                $this->product?->mainImage,
                                $this->demoConfiguration?->mainImage,
                            ])->filter() as $img)
                                <img
                                    src="{{ asset($img->file_path) }}"
                                    alt="{{ $img->title }}"
                                    class="aspect-[4/3] w-full rounded-lg object-cover border border-gray-200/60 dark:border-gray-800/70"
                                />
                            @endforeach
                        </div>

                        <div class="grid grid-cols-3 gap-3">
                            @foreach (($this->group?->galleryImages ?? collect())->take(3) as $img)
                                <img
                                    src="{{ asset($img->file_path) }}"
                                    alt="{{ $img->title }}"
                                    class="aspect-square w-full rounded-lg object-cover border border-gray-200/60 dark:border-gray-800/70"
                                />
                            @endforeach
                        </div>

                        @if (($this->group?->galleryImages?->count() ?? 0) === 0)
                            <div class="text-gray-500 dark:text-gray-400 text-sm">No gallery images yet.</div>
                        @endif
                    </div>

                    <div class="border border-gray-200/60 dark:border-gray-800/70 rounded-xl bg-white dark:bg-gray-900 p-4 shadow-sm space-y-3">
                        <div class="text-sm font-semibold text-gray-800 dark:text-gray-100">Documents</div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            <div class="space-y-2">
                                <div class="text-xs font-semibold tracking-wide text-gray-500 dark:text-gray-400">GROUP</div>
                                <div class="space-y-2 text-xs">
                                    @forelse ($this->groupFiles as $file)
                                        <a
                                            href="{{ asset($file->file_path) }}"
                                            target="_blank"
                                            rel="noopener"
                                            class="flex items-center justify-between rounded-lg border border-gray-200/60 dark:border-gray-800/70 px-3 py-2 bg-gray-50 hover:bg-gray-100 dark:bg-gray-800/70 dark:hover:bg-gray-800 transition"
                                        >
                                            <span class="text-gray-700 dark:text-gray-200">{{ $file->title }}</span>
                                            <span class="text-primary-600 dark:text-primary-400">Open</span>
                                        </a>
                                    @empty
                                        <div class="text-gray-500 dark:text-gray-400">No files.</div>
                                    @endforelse
                                </div>
                            </div>

                            <div class="space-y-2">
                                <div class="text-xs font-semibold tracking-wide text-gray-500 dark:text-gray-400">PRODUCT</div>
                                <div class="space-y-2 text-xs">
                                    @forelse ($this->productFiles as $file)
                                        <a
                                            href="{{ asset($file->file_path) }}"
                                            target="_blank"
                                            rel="noopener"
                                            class="flex items-center justify-between rounded-lg border border-gray-200/60 dark:border-gray-800/70 px-3 py-2 bg-gray-50 hover:bg-gray-100 dark:bg-gray-800/70 dark:hover:bg-gray-800 transition"
                                        >
                                            <span class="text-gray-700 dark:text-gray-200">{{ $file->title }}</span>
                                            <span class="text-primary-600 dark:text-primary-400">Open</span>
                                        </a>
                                    @empty
                                        <div class="text-gray-500 dark:text-gray-400">No files.</div>
                                    @endforelse
                                </div>
                            </div>

                            <div class="space-y-2">
                                <div class="text-xs font-semibold tracking-wide text-gray-500 dark:text-gray-400">CONFIGURATION</div>
                                <div class="space-y-2 text-xs">
                                    @forelse ($this->configurationFiles as $file)
                                        <a
                                            href="{{ asset($file->file_path) }}"
                                            target="_blank"
                                            rel="noopener"
                                            class="flex items-center justify-between rounded-lg border border-gray-200/60 dark:border-gray-800/70 px-3 py-2 bg-gray-50 hover:bg-gray-100 dark:bg-gray-800/70 dark:hover:bg-gray-800 transition"
                                        >
                                            <span class="text-gray-700 dark:text-gray-200">{{ $file->title }}</span>
                                            <span class="text-primary-600 dark:text-primary-400">Open</span>
                                        </a>
                                    @empty
                                        <div class="text-gray-500 dark:text-gray-400">No files.</div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div x-show="tab === 'education'" x-cloak class="space-y-4">
                <div class="border border-gray-200/60 dark:border-gray-800/70 rounded-xl bg-white dark:bg-gray-900 p-5 shadow-sm space-y-4">
                    <div class="text-sm font-semibold text-gray-800 dark:text-gray-100">Educational Info (placeholder)</div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
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

                <div class="overflow-x-auto">
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

            <div x-show="tab === 'additional'" x-cloak class="space-y-4">
                <div class="border border-gray-200/60 dark:border-gray-800/70 rounded-xl bg-white dark:bg-gray-900 p-5 shadow-sm">
                    <div class="text-sm font-semibold text-gray-800 dark:text-gray-100">Additional Info</div>
                    <div class="text-sm text-gray-600 dark:text-gray-300 mt-2">
                        Placeholder for additional content. We can later include approvals, RFQ notes, revision history, or commercial information.
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
