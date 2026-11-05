@if ($present && is_array($value) && array_is_list($value) && $value !== [])
    <ol class="list-decimal space-y-1 ps-5">
        @foreach ($value as $item)<li class="whitespace-pre-wrap break-words">{{ \App\Filament\Resources\BatchPreviewPresentation::value($item) }}</li>@endforeach
    </ol>
@else
    <span class="whitespace-pre-wrap">{{ \App\Filament\Resources\BatchPreviewPresentation::value($value, $present) }}</span>
@endif
