<div x-data="localTraceProfile({{ \Illuminate\Support\Js::from(['user' => auth()->id()]) }})" class="catalog-configurator-preview space-y-3" wire:loading.attr="aria-busy">
    <div wire:key="preview-workspace-controls" class="space-y-3" x-data="configuratorPreviewWorkspace({{ $configuratorId }})" x-on:configurator-preview-visibility.window="visibility($event.detail)">
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
        <div class="flex flex-wrap items-end gap-2">
            <fieldset class="min-w-0">
                <legend class="mb-1 text-xs font-medium">{{ __('configurator.preview.test_using') }}</legend>
                <div class="flex flex-wrap items-center gap-1">
                    <x-filament::button size="sm" :color="$previewSource === 'saved' ? 'primary' : 'gray'" aria-pressed="{{ $previewSource === 'saved' ? 'true' : 'false' }}" x-on:click="rebuild('saved')" x-bind:disabled="collecting">{{ __('configurator.preview.saved_source') }}</x-filament::button>
                    <x-filament::button size="sm" :color="$previewSource === 'unsaved' ? 'primary' : 'gray'" aria-pressed="{{ $previewSource === 'unsaved' ? 'true' : 'false' }}" x-on:click="rebuild('unsaved')" x-bind:disabled="collecting">{{ __('configurator.preview.unsaved_source') }}</x-filament::button>
                    <x-filament::icon-button color="gray" size="sm" icon="heroicon-o-question-mark-circle" :label="__('configurator.preview.source_help')" :tooltip="__('configurator.preview.source_help')" />
                </div>
            </fieldset>
            <x-filament::button color="gray" size="sm" x-on:click="rebuild()" x-bind:disabled="collecting">{{ __('configurator.preview.rebuild') }}</x-filament::button>
            @if ($previewSource === 'unsaved' && $previewChanges !== [])
                <x-filament::button size="sm" x-on:click="review()" x-bind:disabled="collecting" :disabled="! $this->canSaveTestedChanges()">{{ __('configurator.preview.save_tested') }}</x-filament::button>
            @endif
            <x-filament::button color="gray" size="sm" wire:click="toggleTrace" wire:loading.attr="disabled" wire:target="toggleTrace" aria-controls="preview-diagnostics-{{ $this->getId() }}" aria-expanded="{{ $showTrace ? 'true' : 'false' }}">{{ __($showTrace ? 'configurator.preview.hide_diagnostics' : 'configurator.preview.show_diagnostics') }}</x-filament::button>
        </div>
    </div>
    <div wire:key="preview-feedback" class="catalog-configurator-preview-feedback space-y-1 text-xs text-gray-500" role="region" aria-label="{{ __('configurator.preview.feedback_label') }}" tabindex="0">
        <p x-cloak x-show="collecting" role="status">{{ __('configurator.preview.collecting') }}</p>
        <p x-cloak x-show="collectionFailed" role="alert" class="text-danger-600">{{ __('configurator.preview.collection_failed') }}</p>
        <p x-cloak x-show="navigationFailed" role="alert" class="text-danger-600">{{ __('configurator.preview.source_unavailable') }}</p>
        <div x-show="!collecting" role="status">
            @if ($previewSource === 'unsaved' && $previewChanges !== [])
                <p>{{ trans_choice('configurator.preview.pending_count', count($previewChanges), ['count' => count($previewChanges)]) }} · {{ __('configurator.preview.tested_scope') }}</p>
            @elseif ($previewSource === 'unsaved' && $previewErrors === [])
                <p>{{ __('configurator.preview.no_unsaved') }}</p>
            @endif
        </div>
        @if ($productResetNotice)<p role="status">{{ $productResetNotice }}</p>@endif
        @if ($definitionStale)<p role="status" class="text-warning-600">{{ __('configurator.copy.saved_changes_are_available_refresh_to_retest_your_choices') }}</p>@endif
    </div>
    @if ($previewErrors !== [])
        <div id="preview-errors-{{ $this->getId() }}" tabindex="-1" role="alert" class="scroll-mt-6 rounded-lg border border-danger-300 p-3 text-sm text-danger-700 dark:text-danger-400">
            <p class="font-medium">{{ __('configurator.preview.invalid_edits') }}</p>
            <ul class="mt-1 list-inside list-disc">@foreach ($previewErrors as $index => $error)<li wire:key="preview-build-error-{{ $index }}">{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    @if ($previewErrors === [] && $blockingIssues !== [])
        <div id="preview-errors-{{ $this->getId() }}" tabindex="-1" role="alert" class="scroll-mt-6 space-y-2 rounded-lg border border-danger-300 p-3 text-sm text-danger-700 dark:text-danger-400">
            <ul>@foreach ($blockingIssues as $index => $issue)<li wire:key="preview-issue-{{ $index }}">{{ $issue }}</li>@endforeach</ul>
            @foreach ($notes['conflicts'] as $conflict)
                <div class="space-y-2" wire:key="preview-conflict-actions-{{ $conflict['attribute_id'] }}">
                    @if (count($notes['conflicts']) > 1)<p class="font-medium">{{ $conflict['attribute'] }}</p>@endif
                    @foreach ($conflict['causes'] as $cause)
                        <x-catalog.configurator-preview-cause :cause="$cause" :name="$cause['rule']">{{ $cause['rule'] }}</x-catalog.configurator-preview-cause>
                    @endforeach
                    @foreach ($conflict['values'] as $value)
                        @foreach ($value['causes'] as $cause)
                            @if (($cause['target']['type'] ?? null) !== 'rule')
                                <x-catalog.configurator-preview-cause :cause="$cause" :name="$value['value']">{{ $value['value'] }} · {{ $cause['message'] }}</x-catalog.configurator-preview-cause>
                            @endif
                        @endforeach
                    @endforeach
                </div>
            @endforeach
            @if ($notes['conflicts'] !== [])<x-filament::button color="gray" size="sm" x-on:click="explainConflicts()">{{ __('configurator.preview.view_conflict_details') }}</x-filament::button>@endif
        </div>
    @endif
    <div @class(['catalog-configurator-preview-layout', 'catalog-configurator-preview-layout-with-diagnostics' => $showTrace])>
        <div id="preview-choices-{{ $this->getId() }}" tabindex="-1" class="catalog-configurator-preview-controls min-w-0 scroll-mt-6 space-y-3">
            @if ($result)
                <div class="catalog-configurator-preview-result rounded-lg border border-gray-200 px-3 py-2 dark:border-gray-700">
                    @if ($result->definition)
                        <x-catalog.configuration-result :result="$result" inline :show-diagnostics="false" />
                    @elseif ($previewErrors === [])
                        <p class="text-sm">{{ __('configurator.copy.configuration_is_not_available_for_this_product') }}</p>
                    @endif
                    @php($feedbackErrors = count($previewErrors ?: $blockingIssues))
                    <div class="catalog-configurator-preview-mobile-feedback mt-2 flex flex-wrap items-center justify-between gap-2 border-t border-gray-200 pt-2 text-xs dark:border-gray-700" aria-live="polite">
                        @if ($feedbackErrors)
                            <span class="font-medium text-danger-600">{{ trans_choice('configurator.preview.feedback_errors', $feedbackErrors, ['count' => $feedbackErrors]) }}</span>
                            <a class="text-primary-600 underline" href="#preview-errors-{{ $this->getId() }}">{{ __('configurator.preview.view_errors') }}</a>
                        @else
                            <span>{{ trans_choice('configurator.preview.feedback_notices', count($selectionWarnings), ['count' => count($selectionWarnings)]) }} · {{ trans_choice('configurator.preview.feedback_effects', count($notes['effects']), ['count' => count($notes['effects'])]) }}</span>
                            <a class="text-primary-600 underline" href="#preview-notes-heading-{{ $this->getId() }}">{{ __('configurator.preview.view_notes') }}</a>
                        @endif
                    </div>
                </div>
            @endif
            <div class="catalog-configurator-preview-form">{{ $this->form }}</div>
        </div>
        <section class="catalog-configurator-preview-state-notes min-w-0 rounded-lg border border-gray-200 p-3 text-xs break-words dark:border-gray-700" aria-labelledby="preview-notes-heading-{{ $this->getId() }}">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 id="preview-notes-heading-{{ $this->getId() }}" tabindex="-1" class="scroll-mt-6 text-sm font-medium">{{ __('configurator.preview.rules_and_state_notes') }}</h3>
                <a class="catalog-configurator-preview-mobile-feedback text-primary-600 underline" href="#preview-choices-{{ $this->getId() }}">{{ __('configurator.preview.back_to_choices') }}</a>
            </div>
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
                <p class="mt-2 text-gray-500">{{ __($result?->definition ? 'configurator.preview.no_selection_effects' : ($productId === null ? 'configurator.preview.choose_product' : 'configurator.preview.unavailable')) }}</p>
            @endif
            @if ($notes['selected_values'] !== [])
                <details wire:key="preview-selected-value-ids" wire:ignore.self class="mt-3 border-t border-gray-200 pt-2 dark:border-gray-700">
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
            @if ($notes['conflicts'] !== [])
                <section data-preview-conflicts tabindex="-1" class="scroll-mt-6 space-y-3 rounded-lg border border-danger-300 p-3 text-xs" aria-label="{{ __('configurator.preview.unavailable_values_heading') }}">
                    <h3 class="font-medium">{{ __('configurator.preview.unavailable_values_heading') }}</h3>
                    <p>{{ __('configurator.preview.all_restrictions_apply') }}</p>
                    @foreach ($notes['conflicts'] as $conflict)
                        <div wire:key="preview-conflict-{{ $conflict['attribute_id'] }}" class="space-y-2">
                            <h4 class="font-semibold">{{ $conflict['attribute'] }}</h4>
                            @foreach ($conflict['causes'] as $cause)
                                <x-catalog.configurator-preview-cause :cause="$cause" :name="$cause['rule']"><p><span class="font-medium">{{ $cause['rule'] }}</span><br>{{ $cause['conditions'] }}</p></x-catalog.configurator-preview-cause>
                            @endforeach
                            <dl class="space-y-2">
                                @foreach ($conflict['values'] as $value)
                                    <div><dt class="font-medium">{{ $value['value'] }}</dt><dd class="space-y-1">
                                        @foreach ($value['causes'] as $cause)
                                            <x-catalog.configurator-preview-cause :cause="$cause" :name="($cause['target']['type'] ?? null) === 'rule' ? $cause['message'] : $value['value']">{{ $cause['message'] }}</x-catalog.configurator-preview-cause>
                                        @endforeach
                                    </dd></div>
                                @endforeach
                            </dl>
                        </div>
                    @endforeach
                </section>
            @endif
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
    <x-filament-actions::modals />
    </div>
</div>
