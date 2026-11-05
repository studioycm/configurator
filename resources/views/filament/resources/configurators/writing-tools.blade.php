@if (($mode ?? 'toolbar') === 'display')
<x-filament::dropdown placement="bottom-end" width="lg">
    <x-slot name="trigger"><x-filament::button color="gray" size="sm" icon="heroicon-o-adjustments-horizontal">{{ __('configurator.writing.display') }}</x-filament::button></x-slot>
    <div class="rw-display-menu">
        <h3 x-text="tab === 'data' ? t('data_display','Resulting data display') : t('writing_display','Writing display')"></h3>
        <label>{{ __('configurator.writing.theme') }}<select :value="display.value.theme" @change="setDisplay(tab === 'data' ? 'data' : 'writing',{theme:$event.target.value})"><option value="inherit">{{ __('configurator.writing.inherit') }}</option><option value="light">{{ __('configurator.writing.light') }}</option><option value="dark">{{ __('configurator.writing.dark') }}</option></select></label>
        <template x-if="tab === 'writing'"><div>
            <label>{{ __('configurator.writing.logic_style') }}<select :value="writingDisplay.value.style" @change="setDisplay('writing',{style:$event.target.value})"><option value="outline">{{ __('configurator.writing.outline') }}</option><option value="bands">{{ __('configurator.writing.background_groups') }}</option></select></label>
            <label>{{ __('configurator.writing.layout') }}<select :value="writingDisplay.value.layout" @change="setDisplay('writing',{layout:$event.target.value})"><option value="columns">{{ __('configurator.writing.columns') }}</option><option value="stacked">{{ __('configurator.writing.stacked') }}</option></select></label>
            <fieldset><legend>{{ __('configurator.writing.visible_panes') }}</legend><template x-for="key in ['feedback','logic','learning']" :key="key"><label class="rw-check"><input type="checkbox" :checked="writingDisplay.value.visible[key]" @change="setDisplay('writing',{visible:{[key]:$event.target.checked}})"><span x-text="t(key,key)"></span></label></template></fieldset>
            <label class="rw-check"><input type="checkbox" :checked="writingDisplay.value.semantic" @change="setDisplay('writing',{semantic:$event.target.checked})">{{ __('configurator.writing.semantic') }}</label>
            <fieldset><legend>{{ __('configurator.writing.lower_order') }}</legend><template x-for="(key,index) in writingDisplay.value.lowerOrder" :key="key"><div class="rw-setting-row"><span x-text="t(key,key)"></span><button type="button" @click="moveSection(key,-1)" :disabled="index===0" :aria-label="t('move_up','Move up')+' '+key">↑</button><button type="button" @click="moveSection(key,1)" :disabled="index===1" :aria-label="t('move_down','Move down')+' '+key">↓</button></div></template></fieldset>
            <fieldset><legend>{{ __('configurator.writing.editor_display') }}</legend>
                <template x-for="key in ['lineNumbers','highlight','wrap','autoHeight']" :key="key"><label class="rw-check"><input type="checkbox" :checked="writingDisplay.value.editor[key]" @change="setDisplay('writing',{editor:{[key]:$event.target.checked}})"><span x-text="t(key,key)"></span></label></template>
                <label>{{ __('configurator.writing.font_size') }}<input type="number" min="11" max="22" :value="writingDisplay.value.editor.font" @change="setDisplay('writing',{editor:{font:Number($event.target.value)}})"></label>
                <label x-show="!writingDisplay.value.editor.autoHeight">{{ __('configurator.writing.editor_height') }}<input type="number" min="180" max="1600" step="20" :value="writingDisplay.value.editor.height" @change="setDisplay('writing',{editor:{height:Number($event.target.value)}})"></label>
            </fieldset>
        </div></template>
        <template x-if="tab === 'data'"><div>
            <fieldset><legend>{{ __('configurator.writing.record_sections') }}</legend><template x-for="(key,index) in dataDisplay.value.order" :key="key"><div class="rw-setting-row"><label class="rw-check"><input type="checkbox" :checked="dataDisplay.value.visible[key]" @change="setDisplay('data',{visible:{[key]:$event.target.checked}})"><span x-text="t(key,key)"></span></label><button type="button" @click="moveSection(key,-1)" :disabled="index===0" :aria-label="t('move_up','Move up')+' '+key">↑</button><button type="button" @click="moveSection(key,1)" :disabled="index===5" :aria-label="t('move_down','Move down')+' '+key">↓</button></div></template></fieldset>
            <template x-for="key in ['hideEmpty','technical','semantic','wrap']" :key="key"><label class="rw-check"><input type="checkbox" :checked="dataDisplay.value[key]" @change="setDisplay('data',{[key]:$event.target.checked})"><span x-text="t(key,key)"></span></label></template>
            <label>{{ __('configurator.writing.font_size') }}<input type="number" min="11" max="22" :value="dataDisplay.value.font" @change="setDisplay('data',{font:Number($event.target.value)})"></label>
        </div></template>
        <details><summary>{{ __('configurator.writing.colors') }}</summary>
            <label>{{ __('configurator.writing.light_background') }}<input type="color" :value="display.value.backgroundLight" @input="setDisplay(tab==='data'?'data':'writing',{backgroundLight:$event.target.value})"></label>
            <label>{{ __('configurator.writing.dark_background') }}<input type="color" :value="display.value.backgroundDark" @input="setDisplay(tab==='data'?'data':'writing',{backgroundDark:$event.target.value})"></label>
            <template x-for="(value,key) in palette" :key="key"><label><span x-text="value.label"></span><input type="color" :value="display.value.colors[key]" @input="setDisplay(tab==='data'?'data':'writing',{colors:{[key]:$event.target.value}})"></label></template>
        </details>
        <x-filament::button color="gray" size="sm" @click="resetDisplay()">{{ __('configurator.writing.reset_display') }}</x-filament::button>
    </div>
</x-filament::dropdown>
@else
<div data-tour="writing.toolbar" class="rw-toolbar" role="toolbar" aria-label="{{ __('configurator.writing.writing_helpers') }}" @pointerdown="rememberEditor()">
    <div class="rw-toolbar-row">
        @foreach (['commands' => '/', 'attributes' => '@', 'values' => '#', 'products' => '$'] as $key => $symbol)
            <x-filament::button size="xs" color="gray" x-on:click="openChoices({{ \Illuminate\Support\Js::from($key) }})" x-bind:disabled="!!composing || current.pendingText !== null || !context.toolbar[{{ \Illuminate\Support\Js::from($key) }}]"><span class="rw-token">{{ $symbol }}</span> {{ __('configurator.writing.'.$key) }}</x-filament::button>
        @endforeach
        <span class="rw-toolbar-separator" aria-hidden="true"></span>
        <x-filament::icon-button icon="heroicon-o-arrow-uturn-left" label="{{ __('configurator.writing.undo') }}" color="gray" @click="history('undo')" x-bind:disabled="!!composing || current.refreshing || (!current.history.canUndo && current.pendingText === null)" />
        <x-filament::icon-button icon="heroicon-o-arrow-uturn-right" label="{{ __('configurator.writing.redo') }}" color="gray" @click="history('redo')" x-bind:disabled="!!composing || current.refreshing || !current.history.canRedo" />
        <button type="button" @click="copy(current.pendingText ?? current.source)">{{ __('configurator.writing.copy_source') }}</button>
    </div>
    <div class="rw-toolbar-row">
        @foreach (['condition','group','compare','join','mapping','effects'] as $group)
        <div @if ($group === 'mapping') x-show="current.kind === 'Mapping'" @elseif ($group === 'effects') x-show="current.kind === 'Advanced'" @endif>
            <x-filament::dropdown placement="bottom-start">
                <x-slot name="trigger"><x-filament::button size="xs" color="gray" x-bind:disabled="!helperEnabled({{ \Illuminate\Support\Js::from($group) }})">{{ __('configurator.writing.'.$group) }} ⌄</x-filament::button></x-slot>
                <x-filament::dropdown.list><template x-for="item in helperItems({{ \Illuminate\Support\Js::from($group) }})" :key="item.text"><x-filament::dropdown.list.item @pointerdown.prevent="rememberEditor()" @click="item.action ? applyWritingAction(item.action) : insertLiteral(item.text); close()"><span x-text="item.label"></span><small x-text="item.text"></small></x-filament::dropdown.list.item></template></x-filament::dropdown.list>
            </x-filament::dropdown>
        </div>
        @endforeach
        <x-filament::button size="xs" color="gray" @click="closeConditionGroup()" x-bind:disabled="!!composing || !context.toolbar.closeGroup" title="{{ __('configurator.writing.close_group') }}">}</x-filament::button>
        <x-filament::button size="xs" color="gray" @click="insertLiteral(' -> ','Mapping')" x-bind:disabled="!!composing || !context.toolbar.arrow" title="{{ __('configurator.writing.insert_arrow') }}">→</x-filament::button>
        <x-filament::button size="xs" color="gray" @click="context.toolbar.addValue ? insertLiteral(' | ') : openChoices('values')" x-bind:disabled="!!composing || (!context.toolbar.addValue && !context.toolbar.values)">+ {{ __('configurator.writing.value') }}</x-filament::button>
        <x-filament::button size="xs" color="gray" @click="openTextHelper()" x-bind:disabled="!!composing || current.pendingText !== null || !context.toolbar.text">{{ __('configurator.writing.insert_text') }}</x-filament::button>
        <x-filament::button size="xs" color="gray" @click="openExamples()">{{ __('configurator.writing.examples') }}</x-filament::button>
    </div>
    <div class="rw-inline-helper" x-show="textOpen" x-cloak>
        <label>{{ __('configurator.writing.text') }}<textarea x-model="textValue" rows="3"></textarea></label>
        <label>{{ __('configurator.writing.format') }}<select x-model="textMode"><option value="block">[[ {{ __('configurator.writing.multiline_text') }} ]]</option><option value="quoted">{{ __('configurator.writing.quoted_text') }}</option></select></label>
        <x-filament::button size="sm" @click="insertText()">{{ __('configurator.writing.insert_text') }}</x-filament::button><button type="button" @click="textOpen=false">{{ __('configurator.writing.cancel') }}</button>
    </div>
    <div class="rw-inline-helper" x-show="examplesOpen" x-cloak>
        <h3>{{ __('configurator.writing.current_reference_example') }}</h3><pre dir="ltr" x-text="exampleSource"></pre>
        <x-filament::button size="sm" color="gray" @click="insertExample()" x-bind:disabled="!references || references.attributes.length < 2">{{ __('configurator.writing.insert_example') }}</x-filament::button>
        <details><summary>{{ __('configurator.writing.syntax_reference') }}</summary>
            <dl class="rw-cheats"><dt>/when · /and · /or</dt><dd>{{ __('configurator.writing.conditions_help') }}</dd><dt>/when all { … any { … } }</dt><dd>{{ __('configurator.writing.groups_help') }}</dd><dt>/pair @source -> @target<br>/case #source | #another -> #target<br>/other hide · /other disable</dt><dd>{{ __('configurator.writing.mapping_help') }}</dd><dt>/allow · /exclude · /hide · /disable<br>/set-label · /set-display-value · /set-hint</dt><dd>{{ __('configurator.writing.effects_help') }}</dd><dt>= · != · in · not-in · contains<br>"text" · [[multi-line text]]</dt><dd>{{ __('configurator.writing.text_help') }}</dd></dl>
        </details>
        <button type="button" @click="examplesOpen=false">{{ __('configurator.writing.close') }}</button>
    </div>
</div>
@endif
