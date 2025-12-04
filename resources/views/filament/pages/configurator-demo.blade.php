<x-filament-panels::page>
    <div class="space-y-3">

        {{-- Compact header / codes --}}
        <div class="border border-gray-200 dark:border-gray-700 rounded-md bg-white dark:bg-gray-900 px-4 py-3">
            <div class="flex flex-col gap-1">
                <div class="text-lg font-semibold leading-tight text-gray-900 dark:text-gray-100">
                    {{ $configProfile->productProfile->short_label ?? 'N/A' }}
                </div>

                <div class="text-xs text-gray-600 dark:text-gray-400">
                    {{ $configProfile->productProfile->name ?? $configProfile->name }}
                </div>
            </div>

            <div class="mt-2 flex flex-wrap gap-4 text-xs">
                <div class="text-gray-800 dark:text-gray-200">
                    <span class="font-semibold">Product Code:</span>
                    <span class="ml-1 font-mono">
                        {{ $configProfile->productProfile->product_code ?? '—' }}
                    </span>
                </div>

                <div class="text-gray-800 dark:text-gray-200">
                    <span class="font-semibold">Configuration Code:</span>
                    <span class="ml-1 font-mono px-2 py-0.5 rounded border border-gray-200 dark:border-gray-600 bg-gray-100 dark:bg-gray-800">
                        {{ $this->currentCode ?? '—' }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Attributes + options, inline rows, slim padding --}}
        <div class="border border-gray-200 dark:border-gray-700 rounded-md bg-white dark:bg-gray-900 text-sm">
            @foreach ($stages as $stage)
                @php
                    $attrId     = $stage['id'];
                    $selectedId = $selection[$attrId] ?? null;
                    $allowedIds = $allowed[$attrId] ?? [];
                @endphp

                <div class="flex items-center gap-3 px-4 py-1.5 border-t border-gray-100 dark:border-gray-800 first:border-t-0">
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
                                wire:click="selectOption({{ $attrId }}, {{ $opt['id'] }})"
                                @disabled(! $isEnabled)
                                class="inline-flex items-center rounded-full border text-xs px-3 py-1 leading-tight
                                    @if ($isSelected)
                                        bg-primary-600 text-white border-primary-600
                                    @elseif ($isEnabled)
                                        bg-white text-gray-800 border-gray-300 hover:bg-gray-50
                                        dark:bg-gray-800 dark:text-gray-100 dark:border-gray-600 dark:hover:bg-gray-700
                                    @else
                                        bg-gray-50 text-gray-400 border-gray-200 opacity-60 cursor-not-allowed
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
</x-filament-panels::page>
