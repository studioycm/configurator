<div class="space-y-6" wire:loading.attr="aria-busy">
    <p class="text-sm font-medium">Saved definition · Preview reads the saved Rules and Product facts. Unsaved Attribute, Option and Rule drafts are not included.</p>
    @if ($definitionStale)
        <div role="status" class="rounded-lg border border-amber-300 p-3 text-sm">Saved changes are available. Refresh to retest your choices.</div>
    @endif
    <div class="flex flex-wrap gap-2">
        <x-filament::button color="gray" wire:click="refreshDefinition" wire:loading.attr="disabled" wire:target="refreshDefinition">Refresh saved definition</x-filament::button>
        <x-filament::button color="gray" wire:click="toggleTrace" wire:loading.attr="disabled" wire:target="toggleTrace">{{ $showTrace ? 'Hide engine trace' : 'Show engine trace' }}</x-filament::button>
    </div>
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
                        <li class="text-gray-500">Hidden: {{ collect($state['hidden'])->map(fn ($optionId) => $result->definition->attributes[$id]->options[$optionId]->code)->implode(', ') ?: 'none' }} · Disabled: {{ collect($state['disabled'])->map(fn ($optionId) => $result->definition->attributes[$id]->options[$optionId]->code)->implode(', ') ?: 'none' }}</li>
                    @endforeach
                    @foreach ($publicOnlyRules as $label)
                        <li>{{ $label }} · Future public only · Skipped in dashboard Preview</li>
                    @endforeach
                </ul>
            </details>
            @if ($showTrace)
                <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                    <p class="font-medium">Engine trace</p>
                    <ol class="mt-2 space-y-2 text-sm">
                        @foreach ($result->trace as $entry)
                            <li>{{ $result->definition->attributes[$entry['attribute_id']]->label }} · {{ $entry['message'] }}
                                @if (isset($entry['excluded']))
                                    · Excluded: {{ collect($entry['excluded'])->map(fn ($id) => $result->definition->attributes[$entry['attribute_id']]->options[$id]->code)->implode(', ') ?: 'none' }}
                                @endif
                                @if (isset($entry['choice_id'])) · {{ $result->definition->attributes[$entry['attribute_id']]->options[$entry['choice_id']]->code }} @endif
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endif
        @endif
    @else
        <p class="text-sm text-gray-600 dark:text-gray-300">Choose a real Product from an assigned Group. No Product facts or configuration have been invented for this preview.</p>
    @endif
</div>
