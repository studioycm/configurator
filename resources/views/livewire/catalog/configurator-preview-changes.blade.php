@php
    $isRecovery = $recovery ?? false;
    $reviewStale = ! $isRecovery && (($definitionStale ?? false) || ($editorPreviewStale ?? false)
        || (($tested['source'] ?? null) === 'unsaved' && ($tested['inventory_hash'] ?? null) !== ($inventoryHash ?? null)));
    $reviewNotValidated = ! $isRecovery && $changes !== [] && (($tested['source'] ?? null) !== 'unsaved' || ($tested['status'] ?? 'ready') !== 'ready');
@endphp
<div class="space-y-4 text-sm">
    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __($isRecovery ? 'configurator.preview.outcome_review_help' : 'configurator.preview.review_help') }}</p>
    @if (($inventoryErrors ?? []) !== [] || ($previewErrors ?? []) !== [])<ul class="space-y-1 text-danger-600 dark:text-danger-400">@foreach (array_unique([...($inventoryErrors ?? []), ...($previewErrors ?? [])]) as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
    @if ($changes === [])<p>{{ __($isRecovery ? 'configurator.preview.outcome_no_differences' : 'configurator.preview.no_unsaved') }}</p>@endif
    @if ($reviewStale)<p role="status" class="text-warning-600 dark:text-warning-400">{{ __('configurator.preview.preview_stale') }}</p>
    @elseif ($reviewNotValidated)<p role="status" class="text-warning-600 dark:text-warning-400">{{ __('configurator.preview.review_not_validated') }}</p>@endif
    @if (($tested ?? []) !== [])
        <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
            @if (($productCode ?? '') !== '')<p class="font-semibold">{{ __('configurator.preview.tested_product', ['code' => $productCode]) }}</p>@endif
            <p class="mt-1 text-xs">{{ __($tested['source'] === 'saved' ? 'configurator.preview.saved_source' : 'configurator.preview.unsaved_source') }} · {{ __('configurator.copy.configuration_code') }}: <span class="font-mono">{{ $tested['configuration_code'] ?? '—' }}</span></p>
            <p class="mt-2 text-xs text-gray-500">{{ __('configurator.preview.tested_only_this_case') }}</p>
            <details class="mt-2">
                <summary class="cursor-pointer">{{ __('configurator.preview.tested_choices') }}</summary>
                <dl class="mt-2 grid gap-2 sm:grid-cols-2">
                    @foreach ($tested['notes']['selected_values'] ?? [] as $value)<div><dt class="text-xs">{{ $value['attribute'] }}</dt><dd>{{ $value['value'] }}</dd></div>@endforeach
                </dl>
            </details>
        </div>
    @endif
    @foreach ($changes as $index => $change)
        <section class="rounded-lg border border-gray-200 p-3 dark:border-gray-700" wire:key="review-change-{{ $index }}">
            <h3 class="font-semibold">{{ __('configurator.preview.types.'.$change['type']) }} · {{ $change['label'] }} @if ($change['new'])<span class="text-primary-600">({{ __('configurator.preview.new_record') }})</span>@endif</h3>
            @if ($change['invalid'] ?? false)<p class="mt-1 text-xs text-danger-600 dark:text-danger-400">{{ __('configurator.preview.invalid_change') }}</p>@endif
            @if ($change['stale'] ?? false)<p class="mt-1 text-xs text-warning-600 dark:text-warning-400">{{ __('configurator.preview.stale_change') }}</p>@endif
            @foreach ($change['errors'] ?? [] as $error)<p class="mt-1 text-xs text-danger-600 dark:text-danger-400">{{ $error }}</p>@endforeach
            <dl class="mt-2 space-y-3">
                @foreach ($change['fields'] as $field => $difference)
                    <div class="break-words" wire:key="review-change-{{ $index }}-{{ $field }}">
                        <dt class="font-medium">{{ __('configurator.preview.fields.'.$field) }}</dt>
                        <dd class="mt-1 grid gap-2 sm:grid-cols-2">
                            <div><span class="text-xs text-gray-500">{{ __($isRecovery ? 'configurator.preview.current_saved' : 'configurator.preview.before') }}</span><p class="whitespace-pre-line">{{ $difference['before_text'] ?? (is_scalar($difference['before']) ? (string) $difference['before'] : '—') }}</p></div>
                            <div><span class="text-xs text-gray-500">{{ __($isRecovery ? 'configurator.preview.submitted_values' : 'configurator.preview.after') }}</span><p class="whitespace-pre-line">{{ $difference['after_text'] ?? (is_scalar($difference['after']) ? (string) $difference['after'] : '—') }}</p></div>
                        </dd>
                    </div>
                @endforeach
            </dl>
        </section>
    @endforeach
</div>
