<div class="space-y-2" aria-live="polite">
    @include('filament.forms.validation-summary')
    @if ($this->tagPreview['token'] ?? null)
        <p>{{ __('tags.impact', ['count' => $this->tagPreview['affected']]) }}</p>
        @if ($this->tagPreview['destination_usage'])
            <p>{{ __('tags.destination_impact', ['count' => $this->tagPreview['destination_usage']]) }}</p>
        @endif
        <p>{{ __('tags.sample') }}</p>
        <div class="overflow-auto">
            <table class="w-full text-sm">
                <thead><tr><th class="text-start">{{ __('catalog.resources.master_values.singular') }}</th><th class="text-start">{{ __('tags.before') }}</th><th class="text-start">{{ __('tags.after') }}</th></tr></thead>
                <tbody>
                    @foreach ($this->tagPreview['rows'] as $row)
                        <tr class="border-t"><td class="p-2">{{ $row['label'] }} #{{ $row['id'] }}</td><td class="p-2">{{ implode(', ', $row['before']) }}</td><td class="p-2">{{ $row['after'] === [] ? '—' : implode(', ', $row['after']) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p>{{ __('tags.preview_first') }}</p>
    @endif
</div>
