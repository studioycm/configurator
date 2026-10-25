@php($recovery = $getLivewire()->ruleImportRecovery)
<div>
    @if ($recovery !== [])
        <x-filament::section :heading="__('data_exchange.rules.import_review_title')" compact>
            <div class="flex flex-col gap-4">
                <p>{{ __('data_exchange.rules.import_unknown') }}</p>
                <x-filament::button type="button" wire:click="inspectRuleImportOutcome" wire:loading.attr="disabled" wire:target="inspectRuleImportOutcome,finishRuleImportReview">
                    {{ __('data_exchange.rules.import_inspect') }}
                </x-filament::button>
                @if ($recovery['inspected'])
                    <p>{{ __('data_exchange.rules.import_current_note') }}</p>
                    @foreach ($recovery['current'] as $configurator)
                        <div class="flex flex-col gap-2">
                            <h4 class="font-semibold">{{ $configurator['name'] }} (#{{ $configurator['id'] }})</h4>
                            @if (! $configurator['available'])
                                <p>{{ __('data_exchange.rules.import_owner_unavailable') }}</p>
                            @else
                                <ul class="flex flex-col gap-2">
                                    @forelse ($configurator['rules'] as $rule)
                                        <li>
                                            <p class="font-medium">{{ $rule['label'] }} · #{{ $rule['id'] }} · {{ $rule['kind'] }} · {{ __('configurator.rules.fields.priority') }} {{ $rule['priority'] }}</p>
                                            <p class="whitespace-pre-wrap">{{ $rule['summary'] }}</p>
                                        </li>
                                    @empty
                                        <li>{{ __('data_exchange.rules.import_no_saved_rules') }}</li>
                                    @endforelse
                                </ul>
                            @endif
                        </div>
                    @endforeach
                    <x-filament::button type="button" wire:click="finishRuleImportReview" wire:loading.attr="disabled" wire:target="inspectRuleImportOutcome,finishRuleImportReview">
                        {{ __('data_exchange.rules.import_review_done') }}
                    </x-filament::button>
                @endif
            </div>
        </x-filament::section>
    @endif
</div>
