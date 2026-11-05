<div data-batch-preview class="space-y-2" aria-live="polite">
    <p x-cloak x-show="batchIsOutdated || $wire.batchPreviewStale" role="status" class="text-sm font-medium text-warning-700 dark:text-warning-300">{{ __('admin.batch.preview.outdated') }}</p>
    @include('filament.forms.validation-summary')
    @if (filled($getLivewire()->batchPreview['rows'] ?? []))
        <p class="text-sm">{{ trans_choice('admin.batch.preview.selected', count($getLivewire()->batchPreview['rows'])) }}</p>
        @if ($getLivewire()->batchPreview['blocked_count'] ?? 0)
            <p class="text-sm text-danger-600">{{ trans_choice('admin.batch.preview.blocked', $getLivewire()->batchPreview['blocked_count']) }}</p>
            <ul class="list-disc ps-4 text-sm">
                @foreach ($getLivewire()->batchPreview['blockers'] as $blocker)
                    <li>{{ __('admin.batch.preview.blocker', ['name' => $blocker['name'], 'id' => $blocker['parent_id'], 'count' => $blocker['count'], 'category' => \App\Filament\Resources\DependencyActions::categoryLabel($blocker['category'])]) }}</li>
                @endforeach
            </ul>
        @endif
        <div class="max-h-96 space-y-3 overflow-auto">
            @foreach ($getLivewire()->batchPreview['rows'] as $row)
                @php($differences = \App\Filament\Resources\BatchPreviewPresentation::differences($row, $getLivewire()->batchPreview['form_state'] ?? []))
                <section class="rounded-lg border border-gray-200 p-3 dark:border-gray-700" aria-label="{{ $row['name'] }} #{{ $row['id'] }}">
                    <h3 class="text-sm font-semibold">{{ $row['name'] }} <span class="font-normal">#{{ $row['id'] }} · {{ $row['changed'] ? __('admin.batch.preview.changed') : __('admin.batch.preview.unchanged') }}</span></h3>
                    @if ($row['impact'] ?? null)
                        <p class="mt-1 text-xs">{{ trans_choice('admin.batch.preview.impact', $row['impact']['count']) }}</p>
                        @foreach ($row['impact']['owners'] as $owner)
                            <a class="block text-xs text-primary-600" href="{{ \App\Filament\Resources\Configurators\ConfiguratorResource::getUrl('edit', ['record' => $owner['id']]) }}" target="_blank" rel="noopener">{{ $owner['name'] }}</a>
                        @endforeach
                        @if ($row['impact']['count'] > count($row['impact']['owners']))
                            <p class="text-xs">{{ trans_choice('admin.batch.preview.more_impact', $row['impact']['count'] - count($row['impact']['owners'])) }}</p>
                        @endif
                    @endif
                    @if (($getLivewire()->batchPreview['form_state']['operation'] ?? '') === 'remove')
                        <p class="mt-2 text-sm font-medium text-danger-600 dark:text-danger-400">{{ __('admin.batch.preview.remove_record') }}</p>
                    @elseif ($differences === [])
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('admin.batch.preview.no_field_changes') }}</p>
                    @else
                        <h4 class="mt-2 text-xs font-semibold">{{ __('admin.batch.preview.changed_fields') }}</h4>
                        <table class="mt-1 w-full table-fixed text-sm">
                            <thead><tr><th scope="col" class="w-1/3 p-2 text-start">{{ __('ui.fields.field') }}</th><th scope="col" class="p-2 text-start">{{ __('admin.workspace.copy.before') }}</th><th scope="col" class="p-2 text-start">{{ __('admin.workspace.copy.after') }}</th></tr></thead>
                            <tbody>
                            @foreach ($differences as $difference)
                                <tr class="border-t border-gray-200 dark:border-gray-700">
                                    <th scope="row" class="p-2 text-start align-top font-medium">{{ $difference['label'] }}
                                        @if ($difference['mode'])<span class="block text-xs font-normal">{{ $difference['mode'] }}</span>@endif
                                    </th>
                                    <td class="p-2 align-top break-words">@include('filament.resources.batch-preview-value', ['value' => $difference['before'], 'present' => $difference['before_present']])</td>
                                    <td class="p-2 align-top break-words">@include('filament.resources.batch-preview-value', ['value' => $difference['after'], 'present' => $difference['after_present']])</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @endif
                    <details class="mt-2 text-xs">
                        <summary class="cursor-pointer font-medium">{{ __('admin.batch.preview.original_data') }}</summary>
                        <div class="mt-2 grid grid-cols-2 gap-3">
                            <div><h4 class="font-semibold">{{ __('admin.workspace.copy.before') }}</h4><pre class="whitespace-pre-wrap break-words">{{ json_encode($row['before'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></div>
                            <div><h4 class="font-semibold">{{ __('admin.workspace.copy.after') }}</h4><pre class="whitespace-pre-wrap break-words">{{ json_encode($row['after'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></div>
                        </div>
                    </details>
                </section>
            @endforeach
        </div>
    @else
        <p class="text-sm text-gray-500">{{ __('admin.batch.preview.help') }}</p>
    @endif
</div>
