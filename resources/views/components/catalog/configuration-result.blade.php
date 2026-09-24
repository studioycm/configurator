@props(['result'])
<div class="space-y-3" aria-live="polite" aria-atomic="true">
    @if ($result->configurationCode !== null)
        <p class="text-sm font-medium">{{ __('Configuration Code') }}</p>
        <p class="break-all font-mono text-xl font-semibold" data-configuration-code>{{ $result->configurationCode }}</p>
    @elseif ($result->definition !== null)
        <p class="font-medium">{{ __('Configuration is incomplete. No configuration code is available.') }}</p>
    @endif
    @if ($result->diagnostics !== [])
        <ul class="space-y-1 text-sm">
            @foreach ($result->diagnostics as $diagnostic)<li>{{ $diagnostic['message'] }}</li>@endforeach
        </ul>
    @endif
</div>
