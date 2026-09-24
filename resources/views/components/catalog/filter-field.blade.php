@props(['field', 'groupId'])
<fieldset wire:key="filter-{{ $groupId }}-{{ $field['key'] }}" class="min-w-0 rounded-xl border border-slate-200 bg-white px-2 py-1.5 dark:border-zinc-700 dark:bg-zinc-900">
    <legend class="px-2 text-sm font-semibold leading-5">{{ $field['label'] }}</legend>
    <div class="grid grid-cols-1 gap-1">
        @foreach ($field['values'] as $choice)
            <button type="button" wire:key="choice-{{ $groupId }}-{{ $field['key'] }}-{{ hash('sha256', $choice['value']) }}"
                wire:click="selectFilter(@js($field['key']), @js($choice['value']))"
                aria-pressed="{{ $choice['selected'] ? 'true' : 'false' }}"
                data-availability="{{ $choice['compatible'] ? 'available' : 'empty' }}"
                @if (! $choice['compatible']) aria-describedby="catalog-choice-help" title="{{ __('No match with the other choices. Selecting this may clear an earlier choice.') }}" @endif
                @class(['min-h-8 w-full min-w-0 rounded-lg border px-2 py-1 text-center text-base leading-5 transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600',
                    'border-blue-600 bg-blue-600 text-white' => $choice['selected'],
                    'border-slate-300 bg-white text-slate-800 hover:border-blue-600 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200' => ! $choice['selected'] && $choice['compatible'],
                    'border-dashed border-slate-300 bg-slate-100 text-slate-600 hover:border-slate-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400' => ! $choice['selected'] && ! $choice['compatible']])>
                <span class="wrap-break-word">{{ $choice['label'] }}</span>
            </button>
        @endforeach
    </div>
</fieldset>
