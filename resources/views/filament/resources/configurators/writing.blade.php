<x-filament-panels::page>
    <div wire:ignore data-rule-writing-workspace x-data="ruleWritingWorkspace(@js($writingConfig))">
        <div class="rw-heading">
            <a href="{{ $writingConfig['backUrl'] }}" class="rw-back">← {{ __('configurator.writing.back') }}</a>
            <nav aria-label="{{ __('configurator.writing.workspace_tabs') }}" class="rw-tabs">
                <button type="button" :aria-current="tab === 'writing' ? 'page' : null" @click="tab = 'writing'; $nextTick(() => syncEditor())">{{ __('configurator.writing.title') }}</button>
                <button type="button" :aria-current="tab === 'data' ? 'page' : null" @click="showData()">{{ __('configurator.writing.data') }}</button>
            </nav>
            @include('filament.resources.configurators.writing-tools', ['mode' => 'display'])
            <span class="rw-muted" x-show="busy" role="status">{{ __('configurator.writing.working') }}</span>
        </div>
        <p class="rw-notice" role="status" x-show="activeNotice" x-text="activeNotice" x-cloak></p>
        <div class="rw-error" role="alert" x-show="workspaceError" x-cloak>
            <p x-text="workspaceError"></p>
            <a x-show="failedRuleId" :href="ruleEditUrl(failedRuleId)">{{ __('configurator.writing.regular_edit') }}</a>
        </div>
        <section x-show="tab === 'writing'" class="rw-writing-grid" :data-rw-theme="writingDisplay.value.theme" :data-logic-style="writingDisplay.value.style" :style="paletteStyle('writing')">
            <aside data-tour="writing.drafts" class="rw-draft-list" aria-label="{{ __('configurator.writing.shared_drafts') }}">
                <div class="rw-section-heading"><h2>{{ __('configurator.writing.shared_drafts') }}</h2><button type="button" @click="refreshList()" :disabled="busy">{{ __('configurator.writing.refresh') }}</button></div>
                <div class="rw-actions">
                    <x-filament::button size="sm" color="gray" @click="createDraft('Mapping')" x-bind:disabled="busy || !!composing">+ {{ __('configurator.writing.mapping') }}</x-filament::button>
                    <x-filament::button size="sm" color="gray" @click="createDraft('Advanced')" x-bind:disabled="busy || !!composing">+ {{ __('configurator.writing.advanced') }}</x-filament::button>
                </div>
                <x-filament::button color="gray" size="sm" @click="rulePage=1; showRules()" x-bind:disabled="!!composing">{{ __('configurator.writing.use_existing') }}</x-filament::button>
                <form data-tour="writing.draft-search" @submit.prevent="page=1; refreshList()" class="rw-search"><label class="sr-only" for="writing-draft-search">{{ __('configurator.writing.search_drafts') }}</label><input id="writing-draft-search" type="search" x-model="search" placeholder="{{ __('configurator.writing.search_drafts') }}"><button type="submit">{{ __('configurator.writing.search') }}</button></form>
                <ul class="rw-drafts">
                    <template x-for="row in drafts" :key="row.id">
                        <li :data-current="currentId === row.id">
                            <input type="checkbox" :value="row.id" x-model="selected" :aria-label="t('select_draft', 'Select draft') + ': ' + (row.label || row.kind)">
                            <button type="button" @click="openDraft(row.id)"><span x-text="row.label || row.kind"></span><small><span x-text="row.kind"></span> · <span x-text="row.origin_rule_id ? t('update', 'Update') : t('new', 'New')"></span> · <span x-text="'v' + row.revision"></span></small></button>
                        </li>
                    </template>
                </ul>
                <p class="rw-muted" x-show="!drafts.length">{{ __('configurator.writing.no_drafts') }}</p>
                <div class="rw-actions"><button type="button" :disabled="page <= 1 || busy" @click="page--; refreshList()">{{ __('configurator.writing.previous') }}</button><span x-text="page"></span><button type="button" :disabled="!hasMore || busy" @click="page++; refreshList()">{{ __('configurator.writing.next') }}</button></div>
                <div data-tour="writing.batch-save" class="rw-batch"><span x-text="selected.length"></span> {{ __('configurator.writing.selected') }}<x-filament::button @click="saveSelected()" x-bind:disabled="busy || !selected.length || !!composing">{{ __('configurator.writing.save_selected') }}</x-filament::button></div>
                <template x-for="failure in offDraftFailures" :key="failure.draftId">
                    <div class="rw-error" role="alert">
                        <button type="button" @click="openDraft(failure.draftId)" :disabled="!!composing"><strong x-text="failure.label"></strong> · <span x-text="failure.operation === 'save-selected' ? t('save_selected', 'Save selected rules') : failure.operation"></span></button>
                        <p x-text="failure.message"></p>
                    </div>
                </template>
            </aside>
            <main class="rw-editor-panel">
                <div x-show="!current" class="rw-empty">{{ __('configurator.writing.choose_draft') }}</div>
                <template x-if="current">
                    <div>
                        <div class="rw-section-heading"><h2><span x-text="current.kind"></span> · <span x-text="current.origin_rule_id ? t('update', 'Update') + ' #' + current.origin_rule_id : t('new', 'New')"></span></h2><button type="button" @click="duplicate()" :disabled="busy || current.pendingText !== null || !!composing">{{ __('configurator.writing.duplicate') }}</button></div>
                        <label class="rw-field">{{ __('configurator.writing.label') }}<input type="text" :value="current.label ?? ''" @input="changeMetadata('label', $event.target.value)" :disabled="current.refreshing"></label>
                        <details class="rw-description"><summary>{{ __('configurator.writing.description') }}</summary><textarea :value="current.description ?? ''" @input="changeMetadata('description', $event.target.value)" :disabled="current.refreshing" rows="2"></textarea></details>
                        <div class="rw-top-scroll"><div class="rw-top-grid" :data-layout="writingDisplay.value.layout" :style="paneGridStyle">
                        <section class="rw-editor-pane" aria-label="{{ __('configurator.writing.editor') }}">
                        @include('filament.resources.configurators.writing-tools', ['mode' => 'toolbar'])
                        <label class="sr-only" for="writing-source">{{ __('configurator.writing.source') }}</label>
                        <div data-tour="writing.source" class="rw-source-shell" :class="{'rw-highlight':writingDisplay.value.editor.highlight,'rw-line-numbers':writingDisplay.value.editor.lineNumbers,'rw-no-wrap':!writingDisplay.value.editor.wrap}" :style="'--rw-editor-size:'+writingDisplay.value.editor.font+'px'">
                            <div class="rw-source-mirror" aria-hidden="true"><template x-for="line in highlightedLines" :key="line.number"><div class="rw-source-line"><span class="rw-line-number" x-show="writingDisplay.value.editor.lineNumbers" x-text="line.number"></span><span class="rw-source-line-text"><template x-for="(part,index) in line.parts" :key="index"><span :class="'rw-syntax-'+part.type" x-text="part.text"></span></template><span x-show="!line.parts.some(part=>part.text)"> </span></span></div></template></div>
                            <textarea id="writing-source" x-ref="source" class="rw-source" dir="ltr" spellcheck="false" rows="12" :disabled="current.refreshing" :wrap="writingDisplay.value.editor.wrap ? 'soft' : 'off'" :style="'resize:'+(writingDisplay.value.editor.autoHeight?'none':'vertical')" x-init="$nextTick(() => syncEditor())"
                                @beforeinput="beforeInput($event)" @input="input($event)" @keydown="keydown($event)" @compositionstart="compositionStart($event)" @compositionend="compositionEnd($event)" @select="sourceSelection($event)" @click="sourceSelection($event)" @keyup="sourceSelection($event)" @scroll="sourceScroll($event)" @pointerup="saveEditorHeight($event)"></textarea>
                        </div>
                        <div data-tour="writing.status" class="rw-source-status"><span x-text="t('line','Line')+' '+context.line"></span><span x-text="draftStatus()"></span></div>
                        <p class="rw-error" role="alert" x-show="current.error && !writingDisplay.value.visible.feedback" x-text="activeError"></p>
                        <div data-tour="writing.save" class="rw-actions rw-save-actions">
                            <x-filament::button @click="saveCurrent()" x-bind:disabled="busy || current.unknown || current.conflict || current.pendingText !== null || !!composing">{{ __('configurator.writing.save_rule') }}</x-filament::button>
                            <x-filament::button color="gray" @click="flush()" x-bind:disabled="busy || current.unknown || current.conflict || !!composing">{{ __('configurator.writing.save_draft') }}</x-filament::button>
                            <button type="button" x-show="current.origin_rule_id" @click="captureLatest()" :disabled="busy || current.unknown || current.conflict || !!composing">{{ __('configurator.writing.refresh_sections') }}</button>
                            <button type="button" x-show="current.conflict || current.unknown" @click="compareShared()">{{ __('configurator.writing.compare_shared') }}</button>
                        </div>
                        <section class="rw-conflict" x-show="current.latest" x-cloak>
                            <h3>{{ __('configurator.writing.current_shared') }}</h3>
                            <dl><dt>{{ __('configurator.writing.label') }}</dt><dd x-text="current.latest?.label ?? '—'"></dd><dt>{{ __('configurator.writing.description') }}</dt><dd x-text="current.latest?.description ?? '—'"></dd></dl>
                            <pre dir="ltr" x-text="current.latest?.source"></pre><button type="button" @click="loadShared()">{{ __('configurator.writing.load_shared') }}</button>
                        </section>
                        <template x-if="current.capture"><section class="rw-conflict">
                            <h3>{{ __('configurator.writing.refresh_sections') }}</h3><p class="rw-muted">{{ __('configurator.writing.section_help') }}</p>
                            <div class="rw-section-choices"><template x-for="section in ['label','conditions',current.kind === 'Mapping' ? 'mapping' : 'effects']" :key="section"><label><input type="checkbox" :value="section" x-model="current.capture.sections"><span x-text="t(section, section)"></span></label></template></div>
                            <div class="rw-compare"><div><h4>{{ __('configurator.writing.mine') }}</h4><p x-text="current.label ?? '—'"></p><pre dir="ltr" x-text="current.source"></pre></div><div><h4>{{ __('configurator.writing.displayed_latest') }}</h4><p x-text="current.capture?.latest_label ?? '—'"></p><pre dir="ltr" x-text="current.capture?.latest_source"></pre></div></div>
                            <div class="rw-actions"><x-filament::button color="gray" @click="refreshSections()" x-bind:disabled="busy || !current.capture">{{ __('configurator.writing.update_draft') }}</x-filament::button><button type="button" @click="cancelCapture()">{{ __('configurator.writing.cancel') }}</button></div>
                        </section></template>
                        </section>
                        <template x-if="choicesOpen"><div class="rw-divider" role="separator" tabindex="0" aria-orientation="vertical" :aria-valuenow="paneWeights[0]" aria-valuemin="10" aria-valuemax="90" aria-label="{{ __('configurator.writing.resize_choices') }}" @pointerdown="startResize($event,0)" @keydown="resizeKey($event,0)"></div></template>
                        <section class="rw-choices-pane" x-show="choicesOpen" x-cloak aria-label="{{ __('configurator.writing.choices') }}">
                            <div class="rw-pane-heading"><h3>{{ __('configurator.writing.choices') }}</h3><button type="button" @click="closeChoices()" aria-label="{{ __('configurator.writing.close_choices') }}">×</button></div>
                            <p class="rw-muted" x-text="context.attributeKey ? '@'+context.attributeKey : t('choose_explicitly','Choose to insert. Nothing is inserted automatically.')"></p>
                            <div role="listbox" aria-label="{{ __('configurator.writing.choices') }}" @keydown="chooseKey($event)">
                                <template x-for="(choice,index) in context.choices" :key="choice.label+'-'+index"><button type="button" role="option" :aria-selected="choiceIndex===index" :data-tone="choice.tone" :class="{'rw-choice-active':choiceIndex===index}" @pointerdown.prevent="choiceIndex=index; learningAction=choice" @mouseenter="learningAction=choice;choiceIndex=index" @click="applyWritingAction(choice)"><span x-text="choice.label"></span><small x-text="choice.category"></small></button></template>
                            </div><p class="rw-muted" x-show="!context.choices.length">{{ __('configurator.writing.no_choices') }}</p>
                        </section>
                        <template x-if="writingDisplay.value.visible.feedback && topPanes.length > 1"><div class="rw-divider" role="separator" tabindex="0" aria-orientation="vertical" :aria-valuenow="paneWeights[topPanes.length-2]" aria-valuemin="10" aria-valuemax="90" aria-label="{{ __('configurator.writing.resize_feedback') }}" @pointerdown="startResize($event,topPanes.length-2)" @keydown="resizeKey($event,topPanes.length-2)"></div></template>
                        <section data-tour="writing.feedback" class="rw-feedback-pane" x-show="writingDisplay.value.visible.feedback" x-cloak aria-label="{{ __('configurator.writing.feedback') }}">
                            <div class="rw-pane-heading"><span class="rw-muted" x-text="t('line','Line')+' '+context.line"></span><button type="button" @click="closePane('feedback')" aria-label="{{ __('configurator.writing.close_feedback') }}">×</button></div>
                            <h3>{{ __('configurator.writing.issues') }}</h3>
                            <div class="rw-feedback-issues" aria-live="polite"><template x-for="(issue,index) in feedbackIssues" :key="index"><div class="rw-issue"><span class="rw-issue-type" x-text="issue.severity"></span><button type="button" @click="issue.start !== undefined && goToDiagnostic(issue)" x-text="issue.message"></button><small x-show="issue.operation" x-text="issue.operation"></small></div></template><p class="rw-muted" x-show="!feedbackIssues.length">{{ __('configurator.writing.no_writing_issues') }}</p></div>
                            <h3>{{ __('configurator.writing.what_next') }}</h3><template x-for="group in context.nextGroups" :key="group.category"><div class="rw-next-group"><h4 x-text="group.category"></h4><template x-for="action in group.actions" :key="action.label"><button type="button" :data-tone="action.tone" @pointerdown.prevent="rememberEditor()" @mouseenter="learningAction=action" @focus="learningAction=action" @click="applyWritingAction(action)" x-text="action.label"></button></template></div></template>
                            <details class="rw-reference-state"><summary>{{ __('configurator.writing.reference_lists') }}</summary><span class="rw-muted" x-show="referencesLoading">{{ __('configurator.writing.working') }}</span><p class="rw-error" x-show="referenceError" x-text="referenceError"></p><time x-show="references" x-text="references?.read_at"></time><button type="button" @click="refreshReferences()" :disabled="referencesLoading">{{ __('configurator.writing.refresh_references') }}</button></details>
                            <p class="rw-muted">{{ __('configurator.writing.local_logic_help') }}</p>
                        </section>
                        </div></div>
                        <div class="rw-lower-panes"><template x-for="pane in writingDisplay.value.lowerOrder" :key="pane"><section class="rw-lower-pane" x-show="writingDisplay.value.visible[pane]" :aria-label="t(pane,pane)">
                            <div class="rw-pane-heading"><button type="button" :aria-expanded="!writingDisplay.value.collapsed[pane]" @click="setDisplay('writing',{collapsed:{[pane]:!writingDisplay.value.collapsed[pane]}})" x-text="t(pane,pane)"></button><button type="button" @click="closePane(pane)" :aria-label="t('close','Close')+' '+t(pane,pane)">×</button></div>
                            <div :data-tour="pane === 'learning' ? 'writing.learning' : null" x-show="!writingDisplay.value.collapsed[pane]"><template x-if="pane==='logic'">@include('filament.resources.configurators.writing-logic')</template><template x-if="pane==='learning'"><div class="rw-learning"><h3 x-text="activeLearningAction?.label ?? context.learning.title"></h3><p x-text="activeLearningAction?.explanation ?? context.learning.text"></p><details><summary>{{ __('configurator.writing.more_detail') }}</summary><p>{{ __('configurator.writing.learning_detail') }}</p><pre dir="ltr" x-text="exampleSource"></pre></details></div></template></div>
                        </section></template></div>
                    </div>
                </template>
            </main>
        </section>
        <section x-show="tab === 'data'" :data-rw-theme="dataDisplay.value.theme" :style="paletteStyle('data')" x-cloak>@include('filament.resources.configurators.writing-data')</section>
        <section class="rw-picker" x-show="picker" x-trap.inert.noscroll="picker" x-cloak role="dialog" aria-modal="true" aria-label="{{ __('configurator.writing.use_existing') }}" @keydown.escape.stop="picker=false">
            <div><div class="rw-section-heading"><h2>{{ __('configurator.writing.use_existing') }}</h2><button type="button" @click="picker=false">{{ __('configurator.writing.close') }}</button></div>
                <form @submit.prevent="rulePage=1; showRules()" class="rw-search"><label class="sr-only" for="writing-rule-search">{{ __('configurator.writing.search_rules') }}</label><input id="writing-rule-search" type="search" x-model="ruleSearch" placeholder="{{ __('configurator.writing.search_rules') }}"><button type="submit">{{ __('configurator.writing.search') }}</button></form>
                <ul class="rw-drafts"><template x-for="rule in rules" :key="rule.id"><li><button type="button" @click="openRule(rule.id)" :disabled="busy"><span x-text="rule.label"></span><small x-text="rule.kind + ' · #' + rule.id"></small></button></li></template></ul>
                <div class="rw-actions"><button type="button" :disabled="rulePage<=1 || busy" @click="rulePage--;showRules()">{{ __('configurator.writing.previous') }}</button><span x-text="rulePage"></span><button type="button" :disabled="!ruleMore || busy" @click="rulePage++;showRules()">{{ __('configurator.writing.next') }}</button></div>
            </div>
        </section>
    </div>
</x-filament-panels::page>
