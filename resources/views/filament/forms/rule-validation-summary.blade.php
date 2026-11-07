@if ($entries !== [])
    <div role="alert" tabindex="-1" data-rule-error-summary wire:key="rule-errors-{{ sha1(json_encode($entries)) }}" x-data x-init="$nextTick(() => $el.focus())" class="rounded-lg border border-red-300 bg-red-50 p-4 text-red-950">
        <p class="font-semibold">{{ __('admin.workspace.copy.repair_these_items_before_saving') }}</p>
        <ul class="mt-2 space-y-1">
            @foreach ($entries as $entry)
                <li wire:key="rule-error-{{ sha1($entry['path']) }}">
                    @if ($entry['target'] !== null)
                        <button type="button" class="text-left underline" data-rule-error-target="{{ $entry['target'] }}" x-data="ruleErrorNavigation(@js($entry['target']))" x-on:click="reveal()">
                            {{ $entry['label'] }}: {{ implode(' ', $entry['messages']) }}
                        </button>
                    @else
                        {{ $entry['label'] !== '' ? $entry['label'].': ' : '' }}{{ implode(' ', $entry['messages']) }}
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endif
