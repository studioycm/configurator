<div class="rw-section-heading">
    <h2>{{ __('configurator.writing.data') }}</h2><span class="rw-muted" x-text="inspectedId"></span>
    <x-filament::button color="gray" size="sm" @click="inspect(inspectedId ?? currentId)" x-bind:disabled="busy || (!inspectedId && !currentId)">{{ __('configurator.writing.refresh') }}</x-filament::button>
</div>
<p class="rw-muted" x-show="!inspection">{{ __('configurator.writing.inspect_prompt') }}</p>
<template x-if="inspection">
    <div class="rw-data-grid" :class="{'rw-code-no-wrap':!dataDisplay.value.wrap}">
        <nav aria-label="{{ __('configurator.writing.data_views') }}" class="rw-data-tabs"><template x-for="view in ['records','json','yaml','debug']" :key="view"><button type="button" :aria-current="dataView === view ? 'page' : null" @click="dataView=view" x-text="t(view,view.toUpperCase())"></button></template></nav>
        <div class="rw-data-content">
            <div class="rw-data-state"><span x-text="t(inspection.debug.status.target, inspection.debug.status.target)"></span><span x-text="t(inspection.debug.status.capture, inspection.debug.status.capture)"></span><time x-text="inspection.debug.inspected_at"></time></div>
            <details class="rw-data-identity"><summary>{{ __('configurator.writing.identity_freshness') }}</summary><dl><dt>{{ __('configurator.writing.shared_draft_record') }}</dt><dd x-text="inspectedId"></dd><dt>{{ __('configurator.writing.current_fingerprint') }}</dt><dd x-text="inspection.debug.current_fingerprint"></dd><dt>{{ __('configurator.writing.capture_fingerprint') }}</dt><dd x-text="inspection.debug.capture_fingerprint"></dd></dl></details>
            <div x-show="dataView === 'records'">
                <h3>{{ __('configurator.writing.current_records') }}</h3>
                <p class="rw-muted" x-show="!inspection.records.length">{{ __('configurator.writing.no_records') }}</p>
                <template x-for="section in inspectionSections" :key="section.key"><section class="rw-record-section" :data-tone="section.key==='conditions'?'source':section.key==='sets'?'allowed':section.key==='effects'?'text':'hidden'"><h4 class="rw-value-badge" x-text="section.label"></h4><button type="button" :aria-expanded="!dataDisplay.value.collapsed[section.key]" @click="setDisplay('data',{collapsed:{[section.key]:!dataDisplay.value.collapsed[section.key]}})" x-text="dataDisplay.value.collapsed[section.key]?t('expand','Expand'):t('collapse','Collapse')"></button>
                    <div x-show="!dataDisplay.value.collapsed[section.key]">
                        <template x-for="group in section.buckets" :key="group.key"><details class="rw-record-group" open><summary><span x-text="group.label"></span> (<span x-text="group.rows.length"></span>)</summary><template x-for="(row,index) in group.rows" :key="index"><dl class="rw-record"><template x-for="cell in recordCells(row)" :key="cell.name"><div><dt x-text="cell.name"></dt><dd><small x-show="dataDisplay.value.technical" x-text="cell.type"></small><pre dir="ltr" x-text="cell.value"></pre></dd></div></template></dl></template></details></template>
                        <template x-for="block in section.blocks" :key="block.key"><details class="rw-record-group"><summary x-text="block.label"></summary><pre dir="ltr" x-text="block.json"></pre></details></template>
                    </div>
                </section></template>
            </div>
            <div x-show="dataView === 'json' || dataView === 'yaml'"><div class="rw-section-heading"><h3 x-text="dataView.toUpperCase()"></h3><x-filament::button size="sm" color="gray" @click="copy(inspection[dataView])">{{ __('configurator.writing.copy_complete') }}</x-filament::button></div><pre class="rw-data-code" dir="ltr" tabindex="0" x-text="inspection[dataView]"></pre></div>
            <div x-show="dataView === 'debug'"><h3>{{ __('configurator.writing.inspection_snapshot') }}</h3><pre dir="ltr" x-text="JSON.stringify(inspection.debug,null,2)"></pre><h3>{{ __('configurator.writing.local_state') }}</h3><pre dir="ltr" x-text="JSON.stringify({currentDraft:currentId,selected,sharedRevision:current?.revision,localSequence:current?.sequence,acknowledgedSequence:current?.ack.sequence,unknownOutcome:current?.unknown,lastCommittedReceiptReceived:!!receipt,referenceFingerprint:references?.fingerprint??null,referenceReadAt:references?.read_at??null},null,2)"></pre><p class="rw-muted">{{ __('configurator.writing.debug_help') }}</p></div>
        </div>
    </div>
</template>
