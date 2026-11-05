<div data-tour="writing.logic" class="rw-logic" x-show="logic">
    <section class="rw-logic-section" x-show="logic?.conditions.length">
        <h3>{{ __('configurator.writing.when') }}</h3>
        <template x-for="(node,index) in logic?.conditions ?? []" :key="node.start">
            <div>
                <div class="rw-join" x-show="index > 0"><span class="rw-join-badge" data-tone="and">AND</span></div>
                <template x-if="node.kind === 'condition'"><div class="rw-condition-row" :data-active="node.active">
                    <div class="rw-attribute-bar" data-tone="source"><span x-text="node.field.label"></span><small x-text="(node.source_kind==='ProductProperty'?'$':'@')+node.field.key"></small></div>
                    <div class="rw-middle"><span class="rw-join-badge" x-text="node.comparison"></span></div>
                    <div class="rw-values"><template x-for="value in node.values" :key="value.key"><span class="rw-value-badge" data-tone="source" :data-unresolved="value.unresolved" :title="value.key"><span x-text="value.label"></span><small x-show="writingDisplay.value.semantic && value.key !== value.label" x-text="'#'+value.key"></small></span></template></div>
                </div></template>
                <template x-if="node.kind === 'group'"><div class="rw-logic-group" :data-group="node.operator" role="group" :aria-label="node.operator">
                    <template x-for="(child,childIndex) in node.children" :key="child.start"><div>
                        <div class="rw-join" x-show="childIndex > 0"><span class="rw-join-badge" :data-tone="node.operator==='Any'?'or':'and'" x-text="node.operator==='Any'?'OR':'AND'"></span></div>
                        <div class="rw-condition-row" :data-active="child.active"><div class="rw-attribute-bar" data-tone="source"><span x-text="child.field.label"></span><small x-text="(child.source_kind==='ProductProperty'?'$':'@')+child.field.key"></small></div><div class="rw-middle"><span class="rw-join-badge" x-text="child.comparison"></span></div><div class="rw-values"><template x-for="value in child.values" :key="value.key"><span class="rw-value-badge" data-tone="source" :data-unresolved="value.unresolved" :title="value.key"><span x-text="value.label"></span><small x-show="writingDisplay.value.semantic && value.key !== value.label" x-text="'#'+value.key"></small></span></template></div></div>
                    </div></template>
                </div></template>
            </div>
        </template>
    </section>
    <section class="rw-logic-section" x-show="logic?.mapping.source">
        <h3>{{ __('configurator.writing.mapping') }}</h3>
        <div class="rw-mapping-head"><div class="rw-attribute-bar" data-tone="source"><span x-text="logic?.mapping.source?.label"></span><small x-text="'@'+logic?.mapping.source?.key"></small></div><span class="rw-middle" aria-hidden="true"></span><div class="rw-attribute-bar" data-tone="allowed"><span x-text="logic?.mapping.target?.label"></span><small x-text="'@'+logic?.mapping.target?.key"></small></div></div>
        <template x-for="row in logic?.mapping.cases ?? []" :key="row.start"><div class="rw-mapping-row" :data-active="row.active">
            <div class="rw-case-side rw-values"><template x-for="value in row.source" :key="value.key"><span class="rw-value-badge" data-tone="source" :data-unresolved="value.unresolved" :title="value.key"><span x-text="value.label"></span><small x-show="writingDisplay.value.semantic && value.key !== value.label" x-text="'#'+value.key"></small></span></template></div>
            <div class="rw-middle rw-mapping-arrow" aria-label="{{ __('configurator.writing.allows') }}">→</div>
            <div class="rw-case-side"><div class="rw-values"><template x-for="value in row.target" :key="value.key"><span class="rw-value-badge" data-tone="allowed" :data-unresolved="value.unresolved" :title="value.key"><span x-text="value.label"></span><small x-show="writingDisplay.value.semantic && value.key !== value.label" x-text="'#'+value.key"></small></span></template></div><span class="rw-other" :data-tone="row.behavior==='Hide'?'hidden':'excluded'" x-text="row.behavior==='Hide'?t('others_hidden','Others hidden'):t('others_disabled','Others disabled')"></span></div>
        </div></template>
    </section>
    <section class="rw-logic-section" x-show="logic?.effects.length">
        <h3>{{ __('configurator.writing.effects') }}</h3>
        <template x-for="row in logic?.effects ?? []" :key="row.start"><div class="rw-effect-row" :data-active="row.active">
            <span class="rw-effect-kind" :data-tone="row.tone" x-text="row.label"></span>
            <div class="rw-attribute-bar" :data-tone="row.tone" :data-unresolved="row.target.unresolved"><span x-text="row.target.label"></span><small x-text="'@'+row.target.key"></small></div>
            <div class="rw-effect-outcome"><div class="rw-values"><template x-for="value in row.values" :key="value.key"><span class="rw-value-badge" :data-tone="row.tone" :data-unresolved="value.unresolved" :title="value.key"><span x-text="value.label"></span><small x-show="writingDisplay.value.semantic && value.key !== value.label" x-text="'#'+value.key"></small></span></template></div>
                <span class="rw-entire" x-show="row.scope === 'Attribute' && row.text === null">{{ __('configurator.writing.entire_attribute') }}</span>
                <span class="rw-text-outcome" x-show="row.text !== null" x-text="row.text"></span>
                <span class="rw-other" data-tone="excluded" x-show="row.kind==='allow'">{{ __('configurator.writing.others_disabled') }}</span>
            </div>
        </div></template>
    </section>
    <p class="rw-muted" x-show="!logic?.conditions.length && !logic?.mapping.source && !logic?.effects.length">{{ __('configurator.writing.logic_empty') }}</p>
    <div class="rw-legend"><template x-for="key in ['source','allowed','excluded','hidden','text']" :key="key"><span><i :data-tone="key" aria-hidden="true"></i><span x-text="palette[key].label"></span></span></template></div>
</div>
