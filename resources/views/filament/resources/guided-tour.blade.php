@php($guidedTours = $tourPage === null ? [] : app(\App\Services\GuidedTours::class)->forPage(auth()->user(), $tourPage))
@if ($guidedTours !== [])
    <div wire:ignore wire:key="guided-tour-{{ $tourPage }}" data-tour-stage="{{ $tourPage }}" x-data="guidedTour({{ \Illuminate\Support\Js::from([
        'page' => $tourPage,
        'tours' => $guidedTours,
        'targets' => \App\Services\GuidedTours::selectors($tourPage),
        'labels' => ['next' => __('tours.next'), 'previous' => __('tours.previous'), 'done' => __('tours.done'), 'close' => __('tours.close'), 'unavailable' => __('tours.unavailable'), 'changed' => __('tours.changed')],
    ]) }})" class="flex flex-wrap items-center gap-3">
        @foreach ($guidedTours as $guidedTour)
            <button type="button" class="guided-tour-launch"
                x-on:click="start({{ \Illuminate\Support\Js::from($guidedTour['key']) }}, $event)" title="{{ $guidedTour['title'] }}">
                {{ count($guidedTours) === 1 ? __('tours.start') : $guidedTour['title'] }}
            </button>
        @endforeach
        <p x-cloak x-show="notice" x-text="notice" role="status" class="text-sm text-gray-600 dark:text-gray-400"></p>
    </div>
@endif
