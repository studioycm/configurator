@props(['preview' => false, 'label' => null])
<div role="separator" aria-label="{{ $label ?? __($preview ? 'configurator.preview.resize_panels' : 'admin.workspace.copy.resize_table_and_editor') }}" aria-orientation="vertical" aria-valuemin="30" aria-valuemax="70" aria-valuenow="50" x-bind:aria-valuemin="minimumPercentage" x-bind:aria-valuemax="maximumPercentage" x-bind:aria-valuenow="percentage" @if ($preview) x-bind:aria-valuetext="percentage + '% ' + {{ \Illuminate\Support\Js::from(__('configurator.preview.configuration_share')) }}" @endif
    tabindex="0" x-id="['splitter-help']" x-bind:aria-describedby="$id('splitter-help')"
    x-on:mouseenter="showSplitterHint()" x-on:mouseleave="hideSplitterHint()" x-on:focus="showSplitterHint()" x-on:blur="hideSplitterHint()"
    @class(['catalog-workspace-splitter', 'catalog-preview-splitter' => $preview]) x-bind:data-dragging="String(dragging)" x-on:pointerdown="startDrag($event)" x-on:pointermove="moveDrag($event)"
    x-on:pointerup="endDrag()" x-on:pointercancel="endDrag(true)" x-on:lostpointercapture="endDrag(true)" x-on:keydown="keyDown($event)" x-on:dblclick="reset()">
    <span role="tooltip" x-bind:id="$id('splitter-help')" x-cloak x-show="splitterHintVisible" class="catalog-splitter-help">{{ __($preview ? 'configurator.preview.splitter_help' : 'admin.workspace.splitter_help') }}</span>
</div>
