<div class="catalog-configurator-preview space-y-3" wire:loading.attr="aria-busy">
    <div class="catalog-configurator-preview-toolbar flex flex-wrap items-center justify-between gap-2">
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-sm font-medium">Saved definition</span>
            <x-filament::icon-button color="gray" size="sm" icon="heroicon-o-question-mark-circle" label="About saved Preview"
                tooltip="Preview reads saved Rules and Product facts. Unsaved Attribute, Option and Rule drafts are not included. Choose a real Product from an assigned Group to test its configuration. Rule diagnostics show the current legal, hidden and disabled choices. Enable engine trace to inspect evaluation steps." />
            <x-filament::button color="gray" size="sm" wire:click="refreshDefinition" wire:loading.attr="disabled" wire:target="refreshDefinition">Refresh saved definition</x-filament::button>
            <x-filament::button color="gray" size="sm" wire:click="toggleTrace" wire:loading.attr="disabled" wire:target="toggleTrace">{{ $showTrace ? 'Hide engine trace' : 'Show engine trace' }}</x-filament::button>
        </div>
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
            @if ($dashboardProduct)
                <x-filament::button tag="a" :href="route('catalog.products.show', $dashboardProduct)" target="_blank" rel="noopener noreferrer" icon="heroicon-o-arrow-top-right-on-square" size="sm">View in dashboard catalog</x-filament::button>
                @if ($productId === null)<span class="text-xs text-gray-600 dark:text-gray-300">{{ $dashboardProduct->product_code }} · First available Product</span>@endif
            @else
                <p class="text-xs text-gray-600 dark:text-gray-300">{{ $productId === null ? 'No dashboard Product is available in the assigned Groups.' : 'The selected Product is not available for this Configurator in the dashboard catalog.' }}</p>
            @endif
        </div>
    </div>
    @if ($definitionStale)
        <p role="status" class="rounded-md border border-amber-300 p-2 text-sm">Saved changes are available. Refresh to retest your choices.</p>
    @endif
    <div class="catalog-configurator-preview-layout">
        <div class="catalog-configurator-preview-controls min-w-0 space-y-3">
            @if ($result)
                <div class="rounded-lg border border-gray-200 px-3 py-2 dark:border-gray-700">
                    <x-catalog.configuration-result :result="$result" inline :show-diagnostics="false" />
                </div>
            @endif
            <div class="catalog-configurator-preview-form">{{ $this->form }}</div>
        </div>
        <aside class="catalog-configurator-preview-diagnostics min-w-0 text-sm" aria-label="Preview diagnostics">
            <details open class="min-w-0 rounded-lg border border-gray-200 p-2 break-words dark:border-gray-700">
                <summary class="cursor-pointer font-medium">Rule diagnostics</summary>
                @if ($result && $result->diagnostics !== [])
                    <ul class="mt-2 space-y-1 text-xs" aria-live="polite">
                        @foreach ($result->diagnostics as $diagnostic)<li>{{ $diagnostic['message'] }}</li>@endforeach
                    </ul>
                @endif
                @if ($result?->definition)
                    <ul class="mt-2 space-y-2 text-xs">
                        @foreach ($result->definition->rules as $rule)
                            <li>{{ $rule->label }} · {{ $rule->kind->value }} · {{ $rule->active ? 'Enabled' : 'Disabled' }} · Priority {{ $rule->priority }}</li>
                        @endforeach
                        @foreach ($result->attributes as $id => $state)
                            <li>
                                <p class="font-medium">{{ $state['label'] }} · {{ $state['applicable'] ? count($state['legal']).' legal Options' : 'Inapplicable' }}</p>
                                <p class="text-gray-500">Hidden: {{ collect($state['hidden'])->map(fn ($optionId) => $result->definition->attributes[$id]->options[$optionId]->code)->implode(', ') ?: 'none' }} · Disabled: {{ collect($state['disabled'])->map(fn ($optionId) => $result->definition->attributes[$id]->options[$optionId]->code)->implode(', ') ?: 'none' }}</p>
                                @if ($state['applicable'])
                                    @include('livewire.catalog.configurator-preview-notes', ['attribute' => $result->definition->attributes[$id], 'selectedId' => $result->selections[$id] ?? null])
                                @endif
                            </li>
                        @endforeach
                        @foreach ($publicOnlyRules as $label)
                            <li>{{ $label }} · Future public only · Skipped in dashboard Preview</li>
                        @endforeach
                    </ul>
                @else
                    <span class="mt-2 block text-xs text-gray-500">{{ $result ? 'Unavailable' : 'Choose a Product' }}</span>
                @endif
            </details>
            <details open class="min-w-0 rounded-lg border border-gray-200 p-2 break-words dark:border-gray-700">
                <summary class="cursor-pointer font-medium">Engine trace</summary>
                @if ($showTrace && $result?->definition)
                    <ol class="mt-2 space-y-2 text-xs">
                        @foreach ($result->trace as $entry)
                            <li>{{ $result->definition->attributes[$entry['attribute_id']]->label }} · {{ $entry['message'] }}
                                @if (isset($entry['excluded']))
                                    · Excluded: {{ collect($entry['excluded'])->map(fn ($id) => $result->definition->attributes[$entry['attribute_id']]->options[$id]->code)->implode(', ') ?: 'none' }}
                                @endif
                                @if (isset($entry['choice_id'])) · {{ $result->definition->attributes[$entry['attribute_id']]->options[$entry['choice_id']]->code }} @endif
                            </li>
                        @endforeach
                    </ol>
                @else
                    <span class="mt-2 block text-xs text-gray-500">{{ ! $showTrace ? 'Trace is off' : ($result ? 'Unavailable' : 'Choose a Product') }}</span>
                @endif
            </details>
        </aside>
    </div>
</div>
