@props(['field', 'groupId'])
<fieldset wire:key="filter-{{ $groupId }}-{{ $field['key'] }}" class="min-w-0 rounded-xl border border-slate-200 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-900">
    <legend class="px-2 text-base font-semibold leading-6">{{ $field['label'] }}</legend>
    <div class="grid grid-cols-1 gap-2">
        @foreach ($field['values'] as $choice)
            <button type="button" wire:key="choice-{{ $groupId }}-{{ $field['key'] }}-{{ hash('sha256', $choice['value']) }}"
                wire:click="selectFilter(@js($field['key']), @js($choice['value']))"
                aria-pressed="{{ $choice['selected'] ? 'true' : 'false' }}"
                @class(['min-h-11 w-full min-w-0 rounded-lg border px-3 py-2 text-center text-base leading-6 transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600',
                    'border-blue-600 bg-blue-600 text-white' => $choice['selected'],
                    'border-slate-300 bg-white text-slate-800 hover:border-blue-600 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200' => ! $choice['selected']])>
                <span class="wrap-break-word">{{ $choice['label'] }}</span>
            </button>
        @endforeach
    </div>
</fieldset>
