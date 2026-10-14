<div class="space-y-2" aria-live="polite">
    @include('filament.forms.validation-summary')
    @if (filled($this->batchPreview['rows'] ?? []))
        <p class="text-sm">{{ __('admin.batch.preview.selected', ['count' => count($this->batchPreview['rows'])]) }}</p>
        @if ($this->batchPreview['blocked_count'] ?? 0)
            <p class="text-sm text-danger-600">{{ __('admin.batch.preview.blocked', ['count' => $this->batchPreview['blocked_count']]) }}</p>
            <ul class="list-disc ps-4 text-sm">
                @foreach ($this->batchPreview['blockers'] as $blocker)
                    <li>{{ __('admin.batch.preview.blocker', ['name' => $blocker['name'], 'id' => $blocker['parent_id'], 'count' => $blocker['count'], 'category' => \App\Filament\Resources\DependencyActions::categoryLabel($blocker['category'])]) }}</li>
                @endforeach
            </ul>
        @endif
        <div class="overflow-auto max-h-80">
            <table class="w-full text-sm">
                <thead><tr><th class="text-start">{{ __('admin.workspace.copy.record') }}</th><th class="text-start">{{ __('admin.workspace.copy.before') }}</th><th class="text-start">{{ __('admin.workspace.copy.after') }}</th></tr></thead>
                <tbody>
                @foreach ($this->batchPreview['rows'] as $row)
                    <tr class="border-t"><td class="p-2 align-top">{{ $row['name'] }} <span class="text-xs">#{{ $row['id'] }} · {{ $row['changed'] ? __('admin.batch.preview.changed') : __('admin.batch.preview.unchanged') }}</span>
                        @if ($row['impact'] ?? null)
                            <p class="mt-1 text-xs">{{ __('admin.batch.preview.impact', ['count' => $row['impact']['count']]) }}</p>
                            @foreach ($row['impact']['owners'] as $owner)
                                <a class="block text-xs text-primary-600" href="{{ \App\Filament\Resources\Configurators\ConfiguratorResource::getUrl('edit', ['record' => $owner['id']]) }}" target="_blank" rel="noopener">{{ $owner['name'] }}</a>
                            @endforeach
                            @if ($row['impact']['count'] > count($row['impact']['owners']))
                                <p class="text-xs">{{ __('admin.batch.preview.more_impact', ['count' => $row['impact']['count'] - count($row['impact']['owners'])]) }}</p>
                            @endif
                        @endif
                    </td><td class="p-2 align-top"><pre class="whitespace-pre-wrap">{{ json_encode($row['before'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></td><td class="p-2 align-top"><pre class="whitespace-pre-wrap">{{ $row['after'] === [] ? __('ui.actions.remove') : json_encode($row['after'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="text-sm text-gray-500">{{ __('admin.batch.preview.help') }}</p>
    @endif
</div>
