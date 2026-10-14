<div role="separator" aria-label="{{ __('admin.workspace.copy.resize_table_and_editor') }}" aria-orientation="vertical" aria-valuemin="33" aria-valuemax="67" aria-valuenow="50" x-bind:aria-valuenow="percentage"
    tabindex="0" title="{{ __('admin.workspace.splitter_help') }}"
    class="catalog-workspace-splitter" x-on:pointerdown="startDrag($event)" x-on:pointermove="moveDrag($event)"
    x-on:pointerup="endDrag()" x-on:pointercancel="endDrag(true)" x-on:lostpointercapture="endDrag(true)" x-on:keydown="keyDown($event)" x-on:dblclick="reset()"></div>
