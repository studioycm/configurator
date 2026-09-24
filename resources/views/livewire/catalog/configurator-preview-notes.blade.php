<div class="space-y-2">
    @if (filled($state['hint']))<p>{{ $state['hint'] }}</p>@endif
    @if ($state['display_value'] !== null)<p>{{ $state['display_value'] }}</p>@endif
    @foreach ($attribute->options as $option)
        @if (! in_array($option->id, $state['hidden'], true) && ($attribute->inputType !== 'select' || $option->id === $selectedId))
            @php($presentation = $state['options'][$option->id])
            @if ($presentation['display_value'] !== $presentation['label'] || filled($presentation['hint']))
                <p><span class="font-medium">{{ $presentation['label'] }}:</span>
                    @if ($presentation['display_value'] !== $presentation['label']){{ $presentation['display_value'] }}@endif
                    @if (filled($presentation['hint']))<span>{{ $presentation['hint'] }}</span>@endif
                </p>
            @endif
        @endif
    @endforeach
</div>
