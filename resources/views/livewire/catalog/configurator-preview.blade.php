<div class="catalog-configurator-preview" data-preview-operation-owner x-data="configuratorPreviewWorkspace({{ $configuratorId }})" x-on:configurator-preview-visibility.window="visibility($event.detail)" wire:loading.attr="aria-busy">
    <div wire:key="preview-display-owner" x-data="configuratorPreviewDisplay({{ \Illuminate\Support\Js::from(['user' => auth()->id(), 'componentId' => $this->getId(), 'configuratorId' => $configuratorId]) }})" x-bind:style="displayStyle" x-bind:data-preview-compact="String(settings.compact)">
        @php($notices = $notices ?? $this->notices())
        @php($headline = $notices[0] ?? ['type' => 'neutral', 'title' => __('configurator.preview.ready'), 'message' => __('configurator.preview.tested_only_this_case')])
        <div data-tour="preview.feedback" class="catalog-preview-notice" data-tone="{{ $headline['type'] }}" x-bind:data-tone="collectionFailed || navigationFailed || savePermissionDenied || phase === 'reconcile_failed' || phase === 'outcome_unknown' ? 'error' : '{{ $headline['type'] }}'" role="region" aria-label="{{ __('configurator.preview.feedback_label') }}" aria-live="polite" wire:key="preview-notice-slot">
            <x-filament::icon icon="heroicon-o-information-circle" />
            <div class="min-w-0">
                <div x-cloak x-show="collectionFailed || navigationFailed" role="alert"><h3>{{ __('configurator.preview.operation_failed') }}</h3><p x-text="collectionFailed ? {{ \Illuminate\Support\Js::from(__('configurator.preview.collection_failed')) }} : {{ \Illuminate\Support\Js::from(__('configurator.preview.source_unavailable')) }}"></p></div>
                <div x-cloak x-show="!collectionFailed && !navigationFailed && phase === 'reconcile_failed'" role="alert"><h3>{{ __('configurator.preview.reconcile_failed') }}</h3><p>{{ __('configurator.preview.reconcile_help') }}</p></div>
                <div x-cloak x-show="!collectionFailed && !navigationFailed && phase === 'outcome_unknown'" role="alert"><h3>{{ __('configurator.preview.outcome_unknown') }}</h3><p>{{ __('configurator.preview.outcome_unknown_help') }}</p><p x-show="outcomeInspectionFailed">{{ __('configurator.preview.outcome_inspection_failed') }}</p></div>
                <div x-cloak x-show="savePermissionDenied" role="alert"><h3>{{ __('configurator.preview.save_permission_denied') }}</h3><p>{{ __('configurator.preview.permission_draft_kept') }}</p></div>
                <div x-cloak x-show="phase === 'outcome_review'" role="status"><h3>{{ __('configurator.preview.review_saved_state') }}</h3><p>{{ __('configurator.preview.outcome_review_help') }}</p></div>
                <div x-cloak x-show="!collectionFailed && !navigationFailed && !['reconcile_failed','outcome_unknown','outcome_review'].includes(phase) && busy"><h3>{{ __('configurator.preview.working') }}</h3><p>{{ __('configurator.preview.collecting') }}</p></div>
                <div x-show="!collectionFailed && !navigationFailed && !savePermissionDenied && !['reconcile_failed','outcome_unknown','outcome_review'].includes(phase) && !busy"><h3>{{ $headline['title'] }}</h3><p>{{ $headline['message'] ?? '' }}</p></div>
            </div>
            <div class="catalog-preview-notice-actions">
                <button type="button" class="catalog-preview-text-action" x-cloak x-show="phase === 'reconcile_failed'" x-on:click="retryReconciliation()">{{ __('configurator.preview.retry_editor_refresh') }}</button>
                <button type="button" class="catalog-preview-text-action" x-cloak x-show="['outcome_unknown','outcome_review'].includes(phase)" x-on:click="inspectUnknownOutcome()">{{ __('configurator.preview.check_saved_state') }}</button>
                <button type="button" class="catalog-preview-text-action" x-cloak x-show="phase === 'outcome_review'" x-on:click="finishUnknownReview()">{{ __('configurator.preview.finish_outcome_review') }}</button>
                @if (($saveReceipt['refresh_error'] ?? null) !== null)
                    <button type="button" class="catalog-preview-text-action" x-on:click="refreshSavedPreview()" x-bind:disabled="busy">{{ __('configurator.preview.retry_preview_refresh') }}</button>
                @endif
                @if (in_array($headline['action'] ?? null, ['review', 'review_changes'], true))
                    <button type="button" class="catalog-preview-text-action" x-on:click="review()" x-bind:disabled="busy">{{ __('configurator.preview.review_changes') }}</button>
                @elseif (($headline['action'] ?? null) === 'rebuild')
                    <button type="button" class="catalog-preview-text-action" x-on:click="rebuild()" x-bind:disabled="busy">{{ __('configurator.preview.rebuild') }}</button>
                @elseif (in_array($headline['action'] ?? null, ['inspect', 'conflicts'], true))
                    <button type="button" class="catalog-preview-text-action" x-on:click="explainConflicts()" x-bind:disabled="busy">{{ __('configurator.preview.view_conflict_details') }}</button>
                @endif
                @if ($notices !== [])<button type="button" class="catalog-preview-text-action" x-on:click="notices()" x-bind:disabled="busy">{{ __('configurator.preview.all_notices') }}@if (count($notices) > 1) ({{ count($notices) }})@endif</button>@endif
            </div>
        </div>
        @if ($saveOutcomeReview !== null)
            <section x-cloak x-show="phase === 'outcome_review'" class="space-y-4" aria-label="{{ __('configurator.preview.review_saved_state') }}">
                @if ($saveOutcomeReview['new_rules'])
                    <p role="alert">{{ __('configurator.preview.unknown_new_rule_help') }}</p>
                    <h3>{{ __('configurator.preview.current_saved_rules') }}</h3>
                    <ul>@foreach ($saveOutcomeReview['saved_rules'] as $rule)<li>{{ $rule['label'] }} · {{ $rule['kind'] }} (#{{ $rule['id'] }})</li>@endforeach</ul>
                @endif
                @if ($saveOutcomeReview['unavailable'] !== [])
                    <p role="alert">{{ __('configurator.preview.outcome_unavailable') }}</p>
                    <ul>@foreach ($saveOutcomeReview['unavailable'] as $row)<li>{{ __('configurator.preview.types.'.$row['type']) }} #{{ $row['id'] }}</li>@endforeach</ul>
                @endif
                @include('livewire.catalog.configurator-preview-changes', ['changes' => $saveOutcomeReview['changes'], 'tested' => [], 'inventoryErrors' => $saveOutcomeReview['errors'], 'previewErrors' => [], 'recovery' => true])
            </section>
        @endif
        @include('livewire.catalog.configurator-preview-controls')
        <div class="catalog-preview-panels" wire:key="preview-primary-split" x-data="workspaceSplit({ preview: { read: () => splitRatio, write: value => setSplitRatio(value), sideCount: () => visibleSideCount, threshold: 1040, primaryMinimum: 440, sideMinimum: 240, track: 16, gap: 0, sideGap: 12 } })" x-effect="syncPreviewPanels(visibleSideCount, splitRatio)" x-bind:data-split-stacked="String(stacked)" x-bind:style="{ '--workspace-columns': columns }">
            <section data-tour="preview.choices" id="preview-choices-{{ $this->getId() }}" class="catalog-preview-configuration" aria-labelledby="preview-configuration-heading-{{ $this->getId() }}">
                <div class="catalog-preview-code">
                    <h2 id="preview-configuration-heading-{{ $this->getId() }}">{{ __('configurator.copy.configuration_code') }}</h2>
                    @if (($previewView['is_complete'] ?? false) && $previewView['configuration_code'] !== null)
                        <p class="font-mono">{{ $previewView['configuration_code'] }}</p>
                    @else
                        <p class="text-xs">{{ __($productId === null ? 'configurator.preview.choose_product' : (($previewView['status'] ?? 'unavailable') === 'unavailable' ? ($previewSource === 'unsaved' && ($previewErrors ?? []) !== [] ? 'configurator.preview.current_unavailable' : 'configurator.copy.configuration_is_not_available_for_this_product') : 'configurator.copy.configuration_is_incomplete_no_configuration_code_is_available')) }}</p>
                    @endif
                </div>
                <div x-show="settings.presentation === 'all'" x-bind:inert="settings.presentation !== 'all'" x-bind:aria-hidden="settings.presentation !== 'all'" wire:key="preview-all-presentation">
                    @include('livewire.catalog.configurator-preview-values')
                </div>
                <div x-cloak x-show="settings.presentation === 'catalog'" x-bind:inert="settings.presentation !== 'catalog'" x-bind:aria-hidden="settings.presentation !== 'catalog'" wire:key="preview-catalog-presentation">
                    @if (($previewView['status'] ?? 'unavailable') === 'ready')
                        @if (($previewView['excluded_rules'] ?? []) !== [])<p class="mb-3 text-xs text-gray-500 dark:text-gray-400">{{ __('configurator.preview.catalog_scope') }}</p>@endif
                        <x-catalog.configuration-fields :projected-rows="$previewView['inspection']['attributes']" input-namespace="preview-catalog" :preview-workspace="true" />
                    @else
                        <p class="py-3 text-sm">{{ __($productId === null ? 'configurator.preview.choose_product' : 'configurator.preview.current_unavailable') }}</p>
                    @endif
                </div>
            </section>
            <x-catalog.workspace-splitter :preview="true" />
            <div class="catalog-preview-side-panels" x-show="visibleSideCount > 0" x-bind:data-side-count="visibleSideCount">
                <section data-tour="preview.notes" class="catalog-preview-panel catalog-preview-notes" x-show="settings.notes" aria-labelledby="preview-notes-heading-{{ $this->getId() }}" wire:key="preview-selection-notes">
                    <div class="catalog-preview-panel-heading"><h2 id="preview-notes-heading-{{ $this->getId() }}" tabindex="-1">{{ __('configurator.preview.rules_and_state_notes') }}</h2></div>
                    @if (($notes['warnings'] ?? []) !== [])
                        <ul class="space-y-2" aria-live="polite">@foreach ($notes['warnings'] as $index => $warning)<li wire:key="preview-warning-{{ $index }}">{{ $warning['message'] }} @if (isset($warning['attribute_id']))<button type="button" class="catalog-preview-text-action" x-on:click="rememberInspectionTrigger($el); inspect({{ \Illuminate\Support\Js::from($warning['attribute_id']) }})" x-bind:disabled="busy">{{ __('configurator.preview.explain_effect') }}</button>@endif</li>@endforeach</ul>
                    @endif
                    @forelse ($notes['effects'] ?? [] as $index => $note)
                        <div class="catalog-preview-selection-effect" wire:key="preview-selection-effect-{{ $index }}"><h3>{{ $note['attribute'] }} · {{ $note['message'] }}</h3>
                            @if ($note['values'] !== [])<p>{{ implode(', ', $note['values']) }}</p>@endif
                            @if ($note['text'] !== '')<p>{{ $note['text'] }}</p>@endif
                            @if ($note['sources'] !== [])<p class="text-gray-500 dark:text-gray-400">{{ __('configurator.preview.selection_sources') }}: {{ implode(' · ', $note['sources']) }}</p>@endif
                            <p class="text-gray-500 dark:text-gray-400">{{ __('configurator.preview.selection_rule') }}: {{ $note['rule'] }}</p>
                            @if (isset($note['attribute_id']))<button type="button" class="catalog-preview-text-action" x-on:click="rememberInspectionTrigger($el); inspect({{ \Illuminate\Support\Js::from($note['attribute_id']) }}, {{ \Illuminate\Support\Js::from($note['option_id'] ?? null) }})" x-bind:disabled="busy">{{ __('configurator.preview.explain_effect') }}</button>@endif
                        </div>
                    @empty
                        @if (($notes['warnings'] ?? []) === [])<p class="text-gray-500 dark:text-gray-400">{{ __('configurator.preview.no_selection_effects') }}</p>@endif
                    @endforelse
                    @if (($notes['selected_values'] ?? []) !== [])
                        <details wire:key="preview-selected-value-ids" wire:ignore.self class="catalog-preview-selected-ids"><summary>{{ __('configurator.preview.selected_value_ids') }}</summary><dl>@foreach ($notes['selected_values'] as $index => $value)<div wire:key="preview-selected-value-{{ $index }}"><dt>{{ $value['attribute'] }}</dt><dd class="text-gray-500 dark:text-gray-400">{{ $value['value'] }}</dd></div>@endforeach</dl></details>
                    @endif
                </section>
                <aside data-tour="preview.diagnostics" id="preview-diagnostics-{{ $this->getId() }}" class="catalog-preview-panel catalog-preview-inspection" x-cloak x-show="settings.trace" aria-labelledby="preview-inspection-heading-{{ $this->getId() }}" wire:key="preview-inspection-panel">
                    @include('livewire.catalog.configurator-preview-inspection')
                </aside>
            </div>
        </div>
    </div>
    <x-filament-actions::modals />
</div>
