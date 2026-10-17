<div class="space-y-4 text-sm">
    @if ($tested !== [])
        <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
            <p class="font-semibold">{{ __('configurator.preview.tested_product', ['code' => $tested['product']]) }}</p>
            <p class="mt-1 text-xs">{{ $tested['source'] }} · {{ __('configurator.copy.configuration_code') }}: <span class="font-mono">{{ $tested['code'] }}</span></p>
            <p class="mt-2 text-xs text-gray-500">{{ __('configurator.preview.tested_only_this_case') }}</p>
            <details class="mt-2">
                <summary class="cursor-pointer">{{ __('configurator.preview.tested_choices') }}</summary>
                <dl class="mt-2 grid gap-2 sm:grid-cols-2">
                    @foreach ($tested['values'] as $value)<div><dt class="text-xs">{{ $value['attribute'] }}</dt><dd>{{ $value['value'] }}</dd></div>@endforeach
                </dl>
            </details>
        </div>
    @endif
    @foreach ($changes as $index => $change)
        <section class="rounded-lg border border-gray-200 p-3 dark:border-gray-700" wire:key="review-change-{{ $index }}">
            <h3 class="font-semibold">{{ __('configurator.preview.types.'.$change['type']) }} · {{ $change['label'] }} @if ($change['new'])<span class="text-primary-600">({{ __('configurator.preview.new_record') }})</span>@endif</h3>
            <dl class="mt-2 space-y-3">
                @foreach ($change['fields'] as $field => $difference)
                    <div class="break-words" wire:key="review-change-{{ $index }}-{{ $field }}">
                        <dt class="font-medium">{{ __('configurator.preview.fields.'.$field) }}</dt>
                        <dd class="mt-1 grid gap-2 sm:grid-cols-2">
                            <div><span class="text-xs text-gray-500">{{ __('configurator.preview.before') }}</span><p class="whitespace-pre-line">{{ $difference['before_text'] ?? (is_scalar($difference['before']) ? (string) $difference['before'] : '—') }}</p></div>
                            <div><span class="text-xs text-gray-500">{{ __('configurator.preview.after') }}</span><p class="whitespace-pre-line">{{ $difference['after_text'] ?? (is_scalar($difference['after']) ? (string) $difference['after'] : '—') }}</p></div>
                        </dd>
                    </div>
                @endforeach
            </dl>
        </section>
    @endforeach
</div>
