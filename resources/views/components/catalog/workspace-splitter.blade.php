<div role="separator" aria-label="{{ __('Resize table and editor') }}" aria-orientation="vertical" aria-valuemin="33" aria-valuemax="67" aria-valuenow="50" x-bind:aria-valuenow="percentage"
    tabindex="0" title="{{ __('Drag to resize. Arrow keys change width; double-click or Enter resets to half.') }}"
    class="catalog-workspace-splitter" x-on:pointerdown="startDrag($event)" x-on:pointermove="moveDrag($event)"
    x-on:pointerup="endDrag()" x-on:pointercancel="endDrag(true)" x-on:lostpointercapture="endDrag(true)" x-on:keydown="keyDown($event)" x-on:dblclick="reset()"></div>
