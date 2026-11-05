@php($guidedTours = app(\App\Services\GuidedTours::class)->forPage(auth()->user(), $tourPage))
@if ($guidedTours !== [])
    <div wire:ignore x-data="guidedTour({{ \Illuminate\Support\Js::from([
        'page' => $tourPage,
        'tours' => $guidedTours,
        'targets' => ['images.find' => '[data-tour="images.find"]', 'images.records' => '[data-tour="images.records"]', 'images.inspector' => '[data-tour="images.inspector"]'],
        'labels' => ['next' => __('tours.next'), 'previous' => __('tours.previous'), 'done' => __('tours.done'), 'close' => __('tours.close'), 'unavailable' => __('tours.unavailable'), 'changed' => __('tours.changed')],
    ]) }})" class="flex flex-wrap items-center gap-3">
        @foreach ($guidedTours as $guidedTour)
            <x-filament::button type="button" color="gray" size="sm" icon="heroicon-o-question-mark-circle"
                x-on:click="start({{ \Illuminate\Support\Js::from($guidedTour['key']) }}, $event)" :title="$guidedTour['title']">
                {{ count($guidedTours) === 1 ? __('tours.start') : $guidedTour['title'] }}
            </x-filament::button>
        @endforeach
        <p x-cloak x-show="notice" x-text="notice" role="status" class="text-sm text-gray-600 dark:text-gray-400"></p>
    </div>
@endif
