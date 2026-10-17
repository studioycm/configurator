@props(['cause', 'name'])
<div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
    <div class="min-w-0 flex-1 break-words">{{ $slot }}</div>
    @if ($cause['target'] !== null)
        @php($action = __($cause['target']['type'] === 'rule' ? 'configurator.preview.edit_rule' : 'configurator.preview.edit_value'))
        <button type="button" class="shrink-0 text-xs text-primary-600 underline disabled:opacity-50 dark:text-primary-400"
            aria-label="{{ __('configurator.preview.edit_source_named', ['action' => $action, 'name' => $name]) }}"
            x-on:click="openSource({{ \Illuminate\Support\Js::from($cause['target']) }})" x-bind:disabled="collecting">{{ $action }}</button>
    @endif
</div>
