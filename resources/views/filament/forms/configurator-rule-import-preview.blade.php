@php($preview = $getLivewire()->ruleImportPreview)
<div class="flex flex-col gap-4">
    @if ($preview === [])
        <p>{{ __('data_exchange.rules.preview_required') }}</p>
    @else
        <p>{{ __('data_exchange.rules.preview_counts', ['rows' => $preview['row_count'], 'creates' => $preview['create_count'], 'updates' => $preview['update_count'], 'skipped' => $preview['skipped_groups']]) }}</p>
        <p>{{ __('data_exchange.rules.issue_counts', ['errors' => count($preview['errors']), 'warnings' => count($preview['warnings'])]) }}</p>
        @foreach (['errors', 'warnings'] as $type)
            @if ($preview[$type] !== [])
                <x-filament::section :heading="__('data_exchange.rules.'.$type)" compact>
                    <ul class="flex flex-col gap-2">
                        @foreach ($preview[$type] as $message)
                            <li class="whitespace-pre-wrap">{{ $message }}</li>
                        @endforeach
                    </ul>
                </x-filament::section>
            @endif
        @endforeach
        @foreach ($preview['configurators'] as $configurator)
            <x-filament::section :heading="$configurator['name'].' (#'.$configurator['id'].')'" compact>
                <div class="flex flex-col gap-4">
                    @foreach ($configurator['changes'] as $change)
                        <div class="flex flex-col gap-2">
                            <h4 class="font-semibold">{{ $change['operation'] }} · {{ $change['label'] }} · {{ $change['rule_key'] }}</h4>
                            <p>{{ __('data_exchange.rules.rule_summary', ['kind' => $change['kind'], 'enabled' => $change['enabled'] ? __('ui.status.enabled') : __('ui.status.disabled'), 'priority' => $change['priority'], 'conditions' => $change['conditions'], 'effects' => $change['effects'], 'sets' => $change['sets']]) }}</p>
                            <ul>
                                @foreach ($change['changes'] as $message)
                                    <li class="whitespace-pre-wrap">{{ $message }}</li>
                                @endforeach
                            </ul>
                            @if ($change['deleted_children'] !== [])
                                <h5 class="font-semibold">{{ __('data_exchange.rules.deleted_children') }}</h5>
                                <ul>
                                    @foreach ($change['deleted_children'] as $message)
                                        <li class="whitespace-pre-wrap">{{ $message }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endforeach
        @if (! $preview['can_import'])
            <p>{{ __('data_exchange.rules.blocked') }}</p>
        @endif
    @endif
</div>
