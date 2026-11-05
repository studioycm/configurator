@php
    $report = $previewView['inspection'] ?? [];
    $inspectedAttribute = $report['attributes'][$inspectionTarget['attribute_id'] ?? ''] ?? null;
    $inspectedOption = $inspectedAttribute['options'][$inspectionTarget['option_id'] ?? ''] ?? null;
    $inspected = $inspectedOption ?? $inspectedAttribute;
    $causes = $inspectedOption['causes'] ?? $inspectedAttribute['causes'] ?? [];
    $presentations = [...($inspectedAttribute['presentation'] ?? []), ...($inspectedOption['presentation'] ?? [])];
    $traceIndices = $inspectedOption['trace_indices'] ?? $inspectedAttribute['trace_indices'] ?? [];
    foreach ($inspectedAttribute['presentation'] ?? [] as $presentation) {
        $traceIndices = [...$traceIndices, ...$presentation['trace_indices']];
    }
    $traceIndices = array_unique($traceIndices);
    $ruleIds = array_column($causes, 'rule_id');
    foreach ($presentations as $presentation) {
        $ruleIds = [...$ruleIds, ...array_column($presentation['candidates'], 'rule_id')];
    }
    $relatedRules = array_filter($report['rules'] ?? [], fn ($rule) => $inspectedAttribute === null || in_array($rule['id'], $ruleIds, true) || in_array($inspectedAttribute['id'], $rule['affected_attribute_ids'] ?? [], true) || array_intersect($traceIndices, $rule['trace_indices']) !== []);
    $sourceRules = $inspectedAttribute === null ? [] : array_filter($report['rules'] ?? [], fn ($rule) => in_array($inspectedAttribute['id'], $rule['source_attribute_ids'] ?? [], true));
@endphp
<div class="catalog-preview-panel-heading"><h2 id="preview-inspection-heading-{{ $this->getId() }}" data-preview-inspection-heading tabindex="-1">{{ __('configurator.preview.diagnostics_trace') }}</h2><x-filament::icon-button type="button" color="gray" size="sm" icon="heroicon-o-x-mark" :wire:loading.attr="false" :label="__('configurator.preview.hide_diagnostics')" :tooltip="__('configurator.preview.hide_diagnostics')" x-on:click="closeDiagnostics()" /></div>
@if ($inspected !== null)
    <section class="catalog-preview-inspection-section">
        <div class="flex flex-wrap items-start justify-between gap-2"><h3>{{ $inspected['label'] }}</h3><button type="button" class="catalog-preview-text-action" x-on:click="clearInspection()" x-bind:disabled="busy">{{ __('configurator.preview.clear_inspection') }}</button></div>
        @if ($inspectedOption !== null)<p class="text-gray-500 dark:text-gray-400">{{ $inspectedAttribute['label'] }} · {{ $inspectedOption['code'] }}@if ($inspectedOption['semantic_id'] !== null) · {{ $inspectedOption['semantic_id'] }}@endif</p>@endif
        <p>{{ __('configurator.preview.final_state') }}: {{ __('configurator.preview.'.($inspectedOption === null ? 'attribute_states.' : 'states.').$inspected['state']) }}</p>
    </section>
    <section class="catalog-preview-inspection-section"><h3>{{ __('configurator.preview.initial_availability') }}</h3>
        @if ($inspectedOption !== null)
            <dl class="catalog-preview-inspection-facts">@foreach (['active', 'lifecycle_hidden', 'initial_hidden', 'initial_disabled'] as $flag)<div><dt>{{ __('configurator.preview.flags.'.$flag) }}</dt><dd>{{ __($inspectedOption[$flag] ? 'configurator.preview.yes' : 'configurator.preview.no') }}</dd></div>@endforeach</dl>
        @else
            <p>{{ __('configurator.preview.flags.active') }}: {{ __($inspectedAttribute['active'] ? 'configurator.preview.yes' : 'configurator.preview.no') }}</p>
        @endif
    </section>
    <section class="catalog-preview-inspection-section"><h3>{{ __('configurator.preview.applied_restrictions') }}</h3><p class="text-gray-500 dark:text-gray-400">{{ __('configurator.preview.all_restrictions_apply') }}</p>
        @forelse ($causes as $index => $cause)
            <div class="catalog-preview-inspection-cause" wire:key="preview-inspected-cause-{{ $index }}">
                <x-catalog.configurator-preview-cause :cause="$cause" :name="$cause['rule'] ?? $inspected['label']"><p class="font-medium">{{ $cause['rule'] ?? __('configurator.preview.cause_categories.'.($cause['category'] ?? 'system')) }}</p><p>{{ $cause['effect'] ?? '' }}</p></x-catalog.configurator-preview-cause>
                @if (($cause['conditions'] ?? '') !== '')<p class="text-gray-500 dark:text-gray-400">{{ $cause['conditions'] }}</p>@endif
                @if (($cause['set_label'] ?? null) !== null)<p>{{ $cause['set_label'] }}</p>@endif
                @if (isset($cause['additional_options'], $cause['overlapping_options']))<p class="text-gray-500 dark:text-gray-400">{{ __('configurator.preview.restriction_impact', ['additional' => count($cause['additional_options']), 'overlapping' => count($cause['overlapping_options'])]) }}</p>@endif
            </div>
        @empty
            <p class="text-gray-500 dark:text-gray-400">{{ __('configurator.preview.no_recorded_restrictions') }}</p>
        @endforelse
    </section>
    <section class="catalog-preview-inspection-section"><h3>{{ __('configurator.preview.presentation_rules') }}</h3><p class="text-gray-500 dark:text-gray-400">{{ __('configurator.preview.presentation_priority_help') }}</p>
        @forelse ($presentations as $index => $presentation)
            <div class="catalog-preview-inspection-cause" wire:key="preview-presentation-{{ $index }}"><h4 class="font-medium">{{ __('configurator.preview.presentation_fields.'.$presentation['field']) }}</h4>
                <p>{{ $presentation['effective_value'] ?? '—' }}</p>
                @if ($presentation['conflict'])<p class="text-danger-600 dark:text-danger-400">{{ __('configurator.preview.presentation_conflict') }}</p>@endif
                <ul class="space-y-2">@foreach ($presentation['candidates'] as $candidate)<li><x-catalog.configurator-preview-cause :cause="$candidate" :name="$candidate['rule']"><span>{{ $candidate['rule'] }} · {{ __('configurator.preview.priority', ['priority' => $candidate['priority']]) }}@if (in_array($candidate['rule_id'], $presentation['winner_rule_ids'], true)) · {{ __('configurator.preview.winner') }}@endif</span></x-catalog.configurator-preview-cause><p>{{ $candidate['value'] }}</p><p class="text-gray-500 dark:text-gray-400">{{ $candidate['conditions'] }}</p></li>@endforeach</ul>
            </div>
        @empty
            <p class="text-gray-500 dark:text-gray-400">{{ __('configurator.preview.no_presentation_candidates') }}</p>
        @endforelse
    </section>
    <section class="catalog-preview-inspection-section"><h3>{{ __('configurator.preview.effects_other_choices') }}</h3>
        <p class="text-gray-500 dark:text-gray-400">{{ __($inspectedOption !== null && ! $inspectedOption['selected'] ? 'configurator.preview.related_unevaluated' : 'configurator.preview.recorded_effects_only') }}</p>
        @forelse ($sourceRules as $rule)<div class="catalog-preview-inspection-cause"><p class="font-medium">{{ $rule['label'] }}</p><p>{{ $rule['conditions'] }}</p>@if ($inspectedOption === null || $inspectedOption['selected'])<p>{{ __('configurator.preview.rule_outcomes.'.$rule['outcome']) }}</p>@foreach ($rule['trace_indices'] as $traceIndex)@if (isset($report['trace'][$traceIndex]['message']))<p class="text-gray-500 dark:text-gray-400">{{ $report['trace'][$traceIndex]['message'] }}</p>@endif @endforeach @endif</div>@empty<p>{{ __('configurator.preview.no_related_effects') }}</p>@endforelse
    </section>
@elseif ($inspectionTarget !== null)
    <section class="catalog-preview-inspection-section"><p>{{ __('configurator.preview.inspection_unavailable') }}</p><button type="button" class="catalog-preview-text-action" x-on:click="clearInspection()" x-bind:disabled="busy">{{ __('configurator.preview.clear_inspection') }}</button></section>
@else
    <p class="text-gray-500 dark:text-gray-400">{{ __('configurator.preview.inspection_overview') }}</p>
@endif
@if (($previewErrors ?? []) !== [] || ($inventoryErrors ?? []) !== [] || ($blockingIssues ?? []) !== [])
    <section class="catalog-preview-inspection-section" data-preview-conflicts tabindex="-1"><h3>{{ __('configurator.preview.preview_issues') }}</h3><ul class="space-y-1">@foreach (array_unique([...($inventoryErrors ?? []), ...($previewErrors ?? []), ...($blockingIssues ?? [])]) as $issue)<li>{{ $issue }}</li>@endforeach</ul></section>
@endif
<section class="catalog-preview-inspection-section"><h3>{{ __('configurator.copy.rule_diagnostics') }}</h3>
    @foreach ($report['diagnostics'] ?? [] as $index => $diagnostic)<p wire:key="preview-diagnostic-{{ $index }}">{{ $diagnostic['message'] ?? '' }}</p>@endforeach
    <ul class="space-y-3">@foreach ($relatedRules as $rule)<li wire:key="preview-rule-{{ $rule['id'] }}"><x-catalog.configurator-preview-cause :cause="$rule" :name="$rule['label']"><span class="font-medium">{{ $rule['label'] }}</span></x-catalog.configurator-preview-cause><p class="text-gray-500 dark:text-gray-400">{{ __('configurator.preview.rule_details', ['label' => $rule['label'], 'kind' => __($rule['kind'] === 'Mapping' ? 'admin.workspace.labels.mapping' : 'admin.workspace.labels.advanced'), 'status' => __($rule['disabled'] ? 'ui.status.disabled' : 'ui.status.enabled'), 'priority' => $rule['priority']]) }}</p><p>{{ __('configurator.preview.rule_outcomes.'.$rule['outcome']) }}@if ($rule['disabled']) · {{ __('ui.status.disabled') }}@endif @if ($rule['excluded']) · {{ __('configurator.preview.preview_excluded') }}@endif</p><p class="text-gray-500 dark:text-gray-400">{{ $rule['conditions'] }}</p></li>@endforeach</ul>
</section>
<section data-tour="preview.trace" class="catalog-preview-inspection-section"><h3>{{ __('configurator.copy.engine_trace') }}</h3><ol class="list-inside list-decimal space-y-2">
    @foreach ($report['trace'] ?? [] as $index => $entry)
        @if ($inspectedAttribute === null || in_array($index, $traceIndices, true))<li wire:key="preview-trace-{{ $index }}">{{ $entry['message'] ?? '' }}</li>@endif
    @endforeach
</ol></section>
