<x-filament-panels::page>
    <div wire:ignore data-rule-writing-workspace x-data="ruleWritingWorkspace(@js($writingConfig))">
        <div class="rw-heading">
            <a href="{{ $writingConfig['backUrl'] }}" class="rw-back">← {{ __('configurator.writing.back') }}</a>
            <nav aria-label="{{ __('configurator.writing.workspace_tabs') }}" class="rw-tabs">
                <button type="button" :aria-current="tab === 'writing' ? 'page' : null" @click="tab = 'writing'; $nextTick(() => syncEditor())">{{ __('configurator.writing.title') }}</button>
                <button type="button" :aria-current="tab === 'data' ? 'page' : null" @click="showData()">{{ __('configurator.writing.data') }}</button>
            </nav>
            <span class="rw-muted" x-show="busy" role="status">{{ __('configurator.writing.working') }}</span>
        </div>
        <p class="rw-notice" role="status" x-show="notice" x-text="notice" x-cloak></p>
        <div class="rw-error" role="alert" x-show="error && error !== current?.error?.message" x-cloak>
            <p x-text="error"></p>
            <a x-show="failedRuleId" :href="ruleEditUrl(failedRuleId)">{{ __('configurator.writing.regular_edit') }}</a>
        </div>
        <section x-show="tab === 'writing'" class="rw-writing-grid">
            <aside class="rw-draft-list" aria-label="{{ __('configurator.writing.shared_drafts') }}">
                <div class="rw-section-heading"><h2>{{ __('configurator.writing.shared_drafts') }}</h2><button type="button" @click="refreshList()" :disabled="busy">{{ __('configurator.writing.refresh') }}</button></div>
                <div class="rw-actions">
                    <x-filament::button size="sm" color="gray" @click="createDraft('Mapping')" x-bind:disabled="busy || !!composing">+ {{ __('configurator.writing.mapping') }}</x-filament::button>
                    <x-filament::button size="sm" color="gray" @click="createDraft('Advanced')" x-bind:disabled="busy || !!composing">+ {{ __('configurator.writing.advanced') }}</x-filament::button>
                </div>
                <x-filament::button color="gray" size="sm" @click="rulePage=1; showRules()" x-bind:disabled="!!composing">{{ __('configurator.writing.use_existing') }}</x-filament::button>
                <form @submit.prevent="page=1; refreshList()" class="rw-search"><label class="sr-only" for="writing-draft-search">{{ __('configurator.writing.search_drafts') }}</label><input id="writing-draft-search" type="search" x-model="search" placeholder="{{ __('configurator.writing.search_drafts') }}"><button type="submit">{{ __('configurator.writing.search') }}</button></form>
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
                <div class="rw-batch"><span x-text="selected.length"></span> {{ __('configurator.writing.selected') }}<x-filament::button @click="saveSelected()" x-bind:disabled="busy || !selected.length || !!composing">{{ __('configurator.writing.save_selected') }}</x-filament::button></div>
            </aside>
            <main class="rw-editor-panel">
                <div x-show="!current" class="rw-empty">{{ __('configurator.writing.choose_draft') }}</div>
                <template x-if="current">
                    <div>
                        <div class="rw-section-heading"><h2><span x-text="current.kind"></span> · <span x-text="current.origin_rule_id ? t('update', 'Update') + ' #' + current.origin_rule_id : t('new', 'New')"></span></h2><button type="button" @click="duplicate()" :disabled="busy || current.pendingText !== null || !!composing">{{ __('configurator.writing.duplicate') }}</button></div>
                        <label class="rw-field">{{ __('configurator.writing.label') }}<input type="text" :value="current.label ?? ''" @input="changeMetadata('label', $event.target.value)" :disabled="current.refreshing"></label>
                        <details class="rw-description"><summary>{{ __('configurator.writing.description') }}</summary><textarea :value="current.description ?? ''" @input="changeMetadata('description', $event.target.value)" :disabled="current.refreshing" rows="2"></textarea></details>
                        <div class="rw-source-tools">
                            <button type="button" @click="history('undo')" :disabled="current.sequence < 0 || (!current.history.canUndo && current.pendingText === null) || current.refreshing">{{ __('configurator.writing.undo') }}</button>
                            <button type="button" @click="history('redo')" :disabled="current.sequence < 0 || !current.history.canRedo || current.refreshing">{{ __('configurator.writing.redo') }}</button>
                            <button type="button" @click="copy(current.pendingText ?? current.source)">{{ __('configurator.writing.copy_source') }}</button>
                            <span class="rw-muted" x-text="draftStatus()"></span>
                        </div>
                        <label class="sr-only" for="writing-source">{{ __('configurator.writing.source') }}</label>
                        <textarea id="writing-source" x-ref="source" class="rw-source" dir="ltr" spellcheck="false" rows="18" :disabled="current.refreshing" x-init="$nextTick(() => syncEditor())"
                            @beforeinput="beforeInput($event)" @input="input($event)" @keydown="keydown($event)" @compositionstart="compositionStart($event)" @compositionend="compositionEnd($event)" @select="if(current) current.caret=[$event.target.selectionStart,$event.target.selectionEnd]"></textarea>
                        <div class="rw-diagnostics" aria-live="polite">
                            <template x-for="(item,index) in current.diagnostics" :key="index"><button type="button" @click="goToDiagnostic(item)"><span x-text="t('line', 'Line') + ' ' + diagnosticLine(item) + ' · '"></span><span x-text="item.message"></span></button></template>
                            <p class="rw-muted" x-show="!current.diagnostics.length">{{ __('configurator.writing.syntax_complete') }}</p>
                        </div>
                        <div class="rw-error" role="alert" x-show="current.error"><p x-text="current.error?.message"></p><a :href="ruleEditUrl()" x-show="current.origin_rule_id && (current.error?.errors?.draft || current.error?.errors?.rule)">{{ __('configurator.writing.regular_edit') }}</a></div>
                        <div class="rw-actions rw-save-actions">
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
                    </div>
                </template>
            </main>
        </section>
        <section x-show="tab === 'data'" x-cloak>@include('filament.resources.configurators.writing-data')</section>
        <section class="rw-picker" x-show="picker" x-trap.inert.noscroll="picker" x-cloak role="dialog" aria-modal="true" aria-label="{{ __('configurator.writing.use_existing') }}" @keydown.escape.stop="picker=false">
            <div><div class="rw-section-heading"><h2>{{ __('configurator.writing.use_existing') }}</h2><button type="button" @click="picker=false">{{ __('configurator.writing.close') }}</button></div>
                <form @submit.prevent="rulePage=1; showRules()" class="rw-search"><label class="sr-only" for="writing-rule-search">{{ __('configurator.writing.search_rules') }}</label><input id="writing-rule-search" type="search" x-model="ruleSearch" placeholder="{{ __('configurator.writing.search_rules') }}"><button type="submit">{{ __('configurator.writing.search') }}</button></form>
                <ul class="rw-drafts"><template x-for="rule in rules" :key="rule.id"><li><button type="button" @click="openRule(rule.id)" :disabled="busy"><span x-text="rule.label"></span><small x-text="rule.kind + ' · #' + rule.id"></small></button></li></template></ul>
                <div class="rw-actions"><button type="button" :disabled="rulePage<=1 || busy" @click="rulePage--;showRules()">{{ __('configurator.writing.previous') }}</button><span x-text="rulePage"></span><button type="button" :disabled="!ruleMore || busy" @click="rulePage++;showRules()">{{ __('configurator.writing.next') }}</button></div>
            </div>
        </section>
    </div>
</x-filament-panels::page>
