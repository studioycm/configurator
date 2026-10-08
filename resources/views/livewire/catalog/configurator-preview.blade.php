<div class="catalog-configurator-preview" wire:loading.attr="aria-busy">
    <div class="catalog-configurator-preview-layout">
        <div class="min-w-0 space-y-3">
            @if ($dashboardProduct)
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <x-filament::button tag="a" :href="route('catalog.products.show', $dashboardProduct)" target="_blank" rel="noopener noreferrer" icon="heroicon-o-arrow-top-right-on-square" size="sm">View in dashboard catalog</x-filament::button>
                    @if ($productId === null)<span class="text-xs text-gray-600 dark:text-gray-300">{{ $dashboardProduct->product_code }} · First available Product</span>@endif
                </div>
            @else
                <p class="text-xs text-gray-600 dark:text-gray-300">{{ $productId === null ? 'No dashboard Product is available in the assigned Groups.' : 'The selected Product is not available for this Configurator in the dashboard catalog.' }}</p>
            @endif
            @if ($result)
                <div class="rounded-lg border border-gray-200 px-3 py-2 dark:border-gray-700">
                    <x-catalog.configuration-result :result="$result" inline :show-diagnostics="false" />
                </div>
            @endif
            <div class="catalog-configurator-preview-form">{{ $this->form }}</div>
            @if (! $result)
                <p class="text-sm text-gray-600 dark:text-gray-300">Choose a real Product from an assigned Group. No Product facts or configuration have been invented for this preview.</p>
            @endif
        </div>
        <aside class="min-w-0 space-y-3 text-sm" aria-label="Preview diagnostics">
            <div class="space-y-2 rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                <p class="font-medium">Saved definition</p>
                <p class="text-xs text-gray-600 dark:text-gray-300">Preview reads saved Rules and Product facts. Unsaved Attribute, Option and Rule drafts are not included.</p>
                <div class="flex flex-wrap gap-2">
                    <x-filament::button color="gray" size="sm" wire:click="refreshDefinition" wire:loading.attr="disabled" wire:target="refreshDefinition">Refresh saved definition</x-filament::button>
                    <x-filament::button color="gray" size="sm" wire:click="toggleTrace" wire:loading.attr="disabled" wire:target="toggleTrace">{{ $showTrace ? 'Hide engine trace' : 'Show engine trace' }}</x-filament::button>
                </div>
                @if ($definitionStale)
                    <p role="status" class="rounded-md border border-amber-300 p-2">Saved changes are available. Refresh to retest your choices.</p>
                @endif
            </div>
            @if ($result)
                @if ($result->diagnostics !== [])
                    <ul class="space-y-1 rounded-lg border border-gray-200 p-3 dark:border-gray-700" aria-live="polite">
                        @foreach ($result->diagnostics as $diagnostic)<li>{{ $diagnostic['message'] }}</li>@endforeach
                    </ul>
                @endif
                @if ($result->definition)
                    <details open class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                        <summary class="cursor-pointer font-medium">Rule diagnostics</summary>
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
                    </details>
                    @if ($showTrace)
                        <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                            <p class="font-medium">Engine trace</p>
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
                        </div>
                    @endif
                @endif
            @endif
        </aside>
    </div>
</div>
