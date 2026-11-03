<div class="rw-section-heading">
    <h2>{{ __('configurator.writing.data') }}</h2><span class="rw-muted" x-text="inspectedId"></span>
    <button type="button" @click="inspect(inspectedId ?? currentId)" :disabled="busy || (!inspectedId && !currentId)">{{ __('configurator.writing.refresh') }}</button>
</div>
<p class="rw-muted" x-show="!inspection">{{ __('configurator.writing.inspect_prompt') }}</p>
<template x-if="inspection">
    <div class="rw-data-grid">
        <nav aria-label="{{ __('configurator.writing.data_views') }}" class="rw-data-tabs"><template x-for="view in ['records','json','yaml','debug']" :key="view"><button type="button" :aria-current="dataView === view ? 'page' : null" @click="dataView=view" x-text="t(view,view.toUpperCase())"></button></template></nav>
        <div class="rw-data-content">
            <div class="rw-data-state"><span x-text="t(inspection.debug.status.target, inspection.debug.status.target)"></span><span x-text="t(inspection.debug.status.capture, inspection.debug.status.capture)"></span><time x-text="inspection.debug.inspected_at"></time></div>
            <div x-show="dataView === 'records'">
                <h3>{{ __('configurator.writing.current_records') }}</h3>
                <p class="rw-muted" x-show="!inspection.records.length">{{ __('configurator.writing.no_records') }}</p>
                <template x-for="group in inspection.records" :key="group.key"><details class="rw-record-group" open><summary><span x-text="group.label"></span> (<span x-text="group.rows.length"></span>)</summary><template x-for="(row,index) in group.rows" :key="index"><dl class="rw-record"><template x-for="cell in row.cells" :key="cell.name"><div><dt x-text="cell.name"></dt><dd><small x-text="cell.type"></small><pre dir="ltr" x-text="cell.value"></pre></dd></div></template></dl></template></details></template>
                <details class="rw-record-group"><summary>{{ __('configurator.writing.shared_draft_record') }}</summary><pre dir="ltr" x-text="inspection.draft_json"></pre></details>
                <details class="rw-record-group"><summary>{{ __('configurator.writing.last_capture') }}</summary><pre dir="ltr" x-text="inspection.capture_json"></pre></details>
            </div>
            <div x-show="dataView === 'json' || dataView === 'yaml'"><div class="rw-section-heading"><h3 x-text="dataView.toUpperCase()"></h3><button type="button" @click="copy(inspection[dataView])">{{ __('configurator.writing.copy_complete') }}</button></div><pre class="rw-data-code" dir="ltr" tabindex="0" x-text="inspection[dataView]"></pre></div>
            <div x-show="dataView === 'debug'"><h3>{{ __('configurator.writing.inspection_snapshot') }}</h3><pre dir="ltr" x-text="JSON.stringify(inspection.debug,null,2)"></pre><h3>{{ __('configurator.writing.local_state') }}</h3><pre dir="ltr" x-text="JSON.stringify({currentDraft:currentId,selected,sharedRevision:current?.revision,localSequence:current?.sequence,acknowledgedSequence:current?.ack.sequence,unknownOutcome:current?.unknown,lastCommittedReceiptReceived:!!receipt},null,2)"></pre><p class="rw-muted">{{ __('configurator.writing.debug_help') }}</p></div>
        </div>
    </div>
</template>
