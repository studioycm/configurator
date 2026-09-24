<div class="space-y-6" wire:loading.attr="aria-busy">
    <p class="text-sm font-medium">Saved definition · Preview reads the current saved rules and Product facts. Unsaved dialog changes are not included.</p>
    {{ $this->form }}
    @if ($result)
        <x-catalog.configuration-result :result="$result" />
        @if ($result->definition)
            <details class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                <summary class="cursor-pointer font-medium">Rule diagnostics</summary>
                <ul class="mt-3 space-y-2 text-sm">
                    @foreach ($result->definition->rules as $rule)
                        <li>{{ $rule->label }} · {{ $rule->kind->value }} · {{ $rule->active ? 'Enabled' : 'Disabled' }} · Priority {{ $rule->priority }}</li>
                    @endforeach
                    @foreach ($result->attributes as $id => $state)
                        <li>{{ $state['label'] }} · {{ $state['applicable'] ? count($state['legal']).' legal Options' : 'Inapplicable' }}</li>
                    @endforeach
                </ul>
            </details>
        @endif
        <x-filament::button color="gray" wire:click="refreshDefinition">Refresh saved definition</x-filament::button>
    @else
        <p class="text-sm text-gray-600 dark:text-gray-300">Choose a real Product from an assigned Group. No Product facts or configuration have been invented for this preview.</p>
    @endif
</div>
