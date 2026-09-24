@props(['field', 'groupId'])
<fieldset wire:key="filter-{{ $groupId }}-{{ $field['key'] }}" class="min-w-0">
    <legend class="mb-2 text-xs font-semibold leading-4">{{ $field['label'] }}</legend>
    <div class="flex flex-wrap gap-1.5">
        @foreach ($field['values'] as $choice)
            <button type="button" wire:key="choice-{{ $groupId }}-{{ $field['key'] }}-{{ hash('sha256', $choice['value']) }}"
                wire:click="selectFilter(@js($field['key']), @js($choice['value']))"
                aria-pressed="{{ $choice['selected'] ? 'true' : 'false' }}" @if ($choice['count'] === 0) aria-describedby="catalog-choice-help" @endif
                @class(['min-h-9 max-w-full rounded-md border px-2 py-1.5 text-left text-xs leading-4 wrap-break-word transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600',
                    'border-teal-700 bg-teal-700 text-white dark:border-teal-300 dark:bg-teal-300 dark:text-zinc-950' => $choice['selected'],
                    'border-zinc-300 bg-white text-zinc-800 hover:border-teal-600 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200' => ! $choice['selected'] && $choice['count'] > 0,
                    'border-dashed border-zinc-300 text-zinc-500 dark:border-zinc-600 dark:text-zinc-400' => ! $choice['selected'] && $choice['count'] === 0])>
                {{ $choice['label'] }} <span class="ml-0.5 inline-block text-[11px] tabular-nums">({{ $choice['count'] }})</span>
                @if ($choice['selected'])<span aria-hidden="true">✓</span>@endif
            </button>
        @endforeach
    </div>
</fieldset>
