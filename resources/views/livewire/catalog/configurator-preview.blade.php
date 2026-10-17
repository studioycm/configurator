<div x-data="localTraceProfile({{ \Illuminate\Support\Js::from(['user' => auth()->id()]) }})" class="catalog-configurator-preview space-y-3" wire:loading.attr="aria-busy">
    <div class="catalog-configurator-preview-toolbar rounded-lg border border-gray-200 p-2 dark:border-gray-700">
        <div class="catalog-configurator-preview-selection">
            @include('livewire.catalog.configurator-preview-product-search')
            @php($catalogHint = $dashboardProduct ? __($productId === null ? 'configurator.preview.opens_first_product' : 'configurator.preview.opens_product', ['code' => $dashboardProduct->product_code]) : __($productId === null ? 'configurator.preview.no_dashboard_product' : 'configurator.preview.selected_product_unavailable'))
            <div class="catalog-configurator-preview-catalog-actions">
                <x-filament::icon-button color="gray" size="sm" icon="heroicon-o-information-circle" :label="$catalogHint" :tooltip="$catalogHint" />
                @if ($dashboardProduct)
                    <x-filament::button class="catalog-configurator-preview-catalog-action" tag="a" :href="route('catalog.products.show', $dashboardProduct)" target="_blank" rel="noopener noreferrer" :aria-label="__('configurator.copy.view_in_dashboard_catalog')" :tooltip="$catalogHint" icon="heroicon-o-arrow-top-right-on-square" size="sm"><span class="catalog-configurator-preview-catalog-label">{{ __('configurator.copy.view_in_dashboard_catalog') }}</span></x-filament::button>
                @endif
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-sm font-medium">{{ __('configurator.copy.saved_definition') }}</span>
            <x-filament::icon-button color="gray" size="sm" icon="heroicon-o-question-mark-circle" label="{{ __('configurator.copy.about_saved_preview') }}"
                tooltip="{{ __('configurator.preview.saved_definition_help') }}" />
            <x-filament::button color="gray" size="sm" wire:click="refreshDefinition" wire:loading.attr="disabled" wire:target="refreshDefinition">{{ __('configurator.copy.refresh_saved_definition') }}</x-filament::button>
            <x-filament::button color="gray" size="sm" wire:click="toggleTrace" wire:loading.attr="disabled" wire:target="toggleTrace" aria-controls="preview-diagnostics-{{ $this->getId() }}" aria-expanded="{{ $showTrace ? 'true' : 'false' }}">{{ __($showTrace ? 'configurator.preview.hide_diagnostics' : 'configurator.preview.show_diagnostics') }}</x-filament::button>
        </div>
    </div>
    @if ($definitionStale)
        <p role="status" class="rounded-md border border-amber-300 p-2 text-sm">{{ __('configurator.copy.saved_changes_are_available_refresh_to_retest_your_choices') }}</p>
    @endif
    <div @class(['catalog-configurator-preview-layout', 'catalog-configurator-preview-layout-with-diagnostics' => $showTrace])>
        <div class="catalog-configurator-preview-controls min-w-0 space-y-3">
            @if ($result)
                <div class="rounded-lg border border-gray-200 px-3 py-2 dark:border-gray-700">
                    @if ($result->definition)
                        <x-catalog.configuration-result :result="$result" inline :show-diagnostics="false" />
                    @else
                        <p class="text-sm">{{ __('configurator.copy.configuration_is_not_available_for_this_product') }}</p>
                    @endif
                </div>
            @endif
            <div class="catalog-configurator-preview-form">{{ $this->form }}</div>
        </div>
        <section class="catalog-configurator-preview-state-notes min-w-0 rounded-lg border border-gray-200 p-3 text-xs break-words dark:border-gray-700" aria-labelledby="preview-notes-heading-{{ $this->getId() }}">
            <h3 id="preview-notes-heading-{{ $this->getId() }}" class="text-sm font-medium">{{ __('configurator.preview.rules_and_state_notes') }}</h3>
            @if ($selectionWarnings !== [])
                <ul class="mt-2 space-y-1" aria-live="polite">
                    @foreach ($selectionWarnings as $index => $diagnostic)<li wire:key="preview-note-{{ $index }}">{{ $diagnostic['message'] }}</li>@endforeach
                </ul>
            @endif
            @if ($notes['effects'] !== [])
                <ul class="mt-2 space-y-3">
                    @foreach ($notes['effects'] as $index => $note)
                        <li wire:key="preview-selection-effect-{{ $index }}" class="space-y-1">
                            <p class="font-medium">{{ $note['attribute'] }} · {{ $note['message'] }}</p>
                            @if ($note['values'] !== [])<p class="break-words">{{ implode(', ', $note['values']) }}</p>@endif
                            @if ($note['text'] !== '')<p>{{ $note['text'] }}</p>@endif
                            @if ($note['sources'] !== [])<p class="text-gray-500">{{ __('configurator.preview.selection_sources') }}: {{ implode(' · ', $note['sources']) }}</p>@endif
                            <p class="text-gray-500">{{ __('configurator.preview.selection_rule') }}: {{ $note['rule'] }}</p>
                        </li>
                    @endforeach
                </ul>
            @elseif ($selectionWarnings === [])
                <p class="mt-2 text-gray-500">{{ __($result?->definition ? 'configurator.preview.no_selection_effects' : 'configurator.preview.choose_product') }}</p>
            @endif
            @if ($notes['selected_values'] !== [])
                <details class="mt-3 border-t border-gray-200 pt-2 dark:border-gray-700">
                    <summary class="cursor-pointer font-medium">{{ __('configurator.preview.selected_value_ids') }}</summary>
                    <dl class="mt-2 space-y-2">
                        @foreach ($notes['selected_values'] as $index => $value)
                            <div wire:key="preview-selected-value-{{ $index }}"><dt>{{ $value['attribute'] }}</dt><dd class="text-gray-500">{{ $value['value'] }}</dd></div>
                        @endforeach
                    </dl>
                </details>
            @endif
        </section>
        @if ($showTrace)
        <aside id="preview-diagnostics-{{ $this->getId() }}" class="catalog-configurator-preview-diagnostics min-w-0 text-sm" aria-label="{{ __('configurator.copy.preview_diagnostics') }}">
            <section class="min-w-0 rounded-lg border border-gray-200 p-2 break-words dark:border-gray-700" aria-labelledby="preview-rules-heading-{{ $this->getId() }}">
                <h3 id="preview-rules-heading-{{ $this->getId() }}" class="font-medium">{{ __('configurator.copy.rule_diagnostics') }}</h3>
                @if ($result)
                    <ul class="mt-2 space-y-1 text-xs">
                        @foreach ($notes['diagnostics'] as $index => $diagnostic)<li wire:key="preview-system-diagnostic-{{ $index }}">{{ $diagnostic['message'] }}</li>@endforeach
                    </ul>
                @endif
                @if ($result?->definition)
                    <ul class="mt-2 space-y-2 text-xs">
                        @foreach ($result->definition->rules as $rule)
                            <li wire:key="preview-rule-{{ $rule->id }}">{{ __('configurator.preview.rule_details', ['label' => $rule->label, 'kind' => __($rule->kind->value === 'Mapping' ? 'admin.workspace.labels.mapping' : 'admin.workspace.labels.advanced'), 'status' => __($rule->active ? 'ui.status.enabled' : 'ui.status.disabled'), 'priority' => $rule->priority]) }}</li>
                        @endforeach
                        @foreach ($result->attributes as $id => $state)
                            <li wire:key="preview-attribute-diagnostic-{{ $id }}">
                                <p class="font-medium">{{ $state['label'] }} · {{ $state['applicable'] ? trans_choice('configurator.preview.legal_values', count($state['legal'])) : __('configurator.preview.inapplicable') }}</p>
                                <p class="text-gray-500">{{ __('configurator.preview.hidden_and_disabled', ['hidden' => collect($state['hidden'])->map(fn ($optionId) => $result->definition->attributes[$id]->options[$optionId]->code)->implode(', ') ?: __('configurator.preview.none'), 'disabled' => collect($state['disabled'])->map(fn ($optionId) => $result->definition->attributes[$id]->options[$optionId]->code)->implode(', ') ?: __('configurator.preview.none')]) }}</p>
                                @if ($state['applicable'])
                                    @include('livewire.catalog.configurator-preview-notes', ['attribute' => $result->definition->attributes[$id], 'selectedId' => $result->selections[$id] ?? null])
                                @endif
                            </li>
                        @endforeach
                        @foreach ($publicOnlyRules as $index => $label)
                            <li wire:key="preview-public-rule-{{ $index }}">{{ __('configurator.preview.skipped_public_rule', ['label' => $label]) }}</li>
                        @endforeach
                    </ul>
                @else
                    <span class="mt-2 block text-xs text-gray-500">{{ __($result ? 'configurator.preview.unavailable' : 'configurator.preview.choose_product') }}</span>
                @endif
            </section>
            <section class="min-w-0 rounded-lg border border-gray-200 p-2 break-words dark:border-gray-700" aria-labelledby="preview-trace-heading-{{ $this->getId() }}">
                <h3 id="preview-trace-heading-{{ $this->getId() }}" class="font-medium">{{ __('configurator.copy.engine_trace') }}</h3>
                @if ($showTrace && $result?->definition)
                    <ol class="mt-2 space-y-2 text-xs">
                        @foreach ($result->trace as $index => $entry)
                            <li wire:key="preview-trace-{{ $index }}">{{ $result->definition->attributes[$entry['attribute_id']]->label }} · {{ $entry['message'] }}
                                @if (isset($entry['excluded']))
                                    · {{ __('configurator.preview.excluded_values', ['values' => collect($entry['excluded'])->map(fn ($id) => $result->definition->attributes[$entry['attribute_id']]->options[$id]->code)->implode(', ') ?: __('configurator.preview.none')]) }}
                                @endif
                                @if (isset($entry['choice_id'])) · {{ $result->definition->attributes[$entry['attribute_id']]->options[$entry['choice_id']]->code }} @endif
                            </li>
                        @endforeach
                    </ol>
                @else
                    <span class="mt-2 block text-xs text-gray-500">{{ __(! $showTrace ? 'configurator.preview.trace_off' : ($result ? 'configurator.preview.unavailable' : 'configurator.preview.choose_product')) }}</span>
                @endif
            </section>
        </aside>
        @endif
    </div>
</div>
