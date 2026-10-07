<div class="space-y-2" aria-live="polite">
    @include('filament.forms.validation-summary')
    @if (filled($this->batchPreview['rows'] ?? []))
        <p class="text-sm">{{ count($this->batchPreview['rows']) }} selected records. Review each change before applying.</p>
        @if ($this->batchPreview['blocked_count'] ?? 0)
            <p class="text-sm text-danger-600">{{ $this->batchPreview['blocked_count'] }} records are blocked. Nothing in this selection can be removed until the dependencies are repaired.</p>
            <ul class="list-disc ps-4 text-sm">
                @foreach ($this->batchPreview['blockers'] as $blocker)
                    <li>{{ $blocker['name'] }} #{{ $blocker['parent_id'] }}: {{ $blocker['count'] }} {{ $blocker['category'] }}. Use View {{ $blocker['category'] }} below.</li>
                @endforeach
            </ul>
        @endif
        <div class="overflow-auto max-h-80">
            <table class="w-full text-sm">
                <thead><tr><th class="text-start">Record</th><th class="text-start">Before</th><th class="text-start">After</th></tr></thead>
                <tbody>
                @foreach ($this->batchPreview['rows'] as $row)
                    <tr class="border-t"><td class="p-2 align-top">{{ $row['name'] }} <span class="text-xs">#{{ $row['id'] }} · {{ $row['changed'] ? 'Changed' : 'Unchanged' }}</span>
                        @if ($row['impact'] ?? null)
                            <p class="mt-1 text-xs">Code change affects {{ $row['impact']['count'] }} existing Configurators.</p>
                            @foreach ($row['impact']['owners'] as $owner)
                                <a class="block text-xs text-primary-600" href="{{ \App\Filament\Resources\Configurators\ConfiguratorResource::getUrl('edit', ['record' => $owner['id']]) }}" target="_blank" rel="noopener">{{ $owner['name'] }}</a>
                            @endforeach
                            @if ($row['impact']['count'] > count($row['impact']['owners']))
                                <p class="text-xs">{{ $row['impact']['count'] - count($row['impact']['owners']) }} more affected Configurators.</p>
                            @endif
                        @endif
                    </td><td class="p-2 align-top"><pre class="whitespace-pre-wrap">{{ json_encode($row['before'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></td><td class="p-2 align-top"><pre class="whitespace-pre-wrap">{{ $row['after'] === [] ? 'Remove' : json_encode($row['after'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="text-sm text-gray-500">Preview changes to see before/after values. No changes have been saved.</p>
    @endif
</div>
