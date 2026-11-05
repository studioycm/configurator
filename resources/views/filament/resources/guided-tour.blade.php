@php($guidedTours = $tourPage === null ? [] : app(\App\Services\GuidedTours::class)->forPage(auth()->user(), $tourPage))
@if ($guidedTours !== [])
    <div wire:ignore wire:key="guided-tour-{{ $tourPage }}" data-tour-stage="{{ $tourPage }}" x-data="guidedTour({{ \Illuminate\Support\Js::from([
        'page' => $tourPage, 'userId' => (string) auth()->id(),
        'tours' => $guidedTours,
        'targets' => \App\Services\GuidedTours::selectors($tourPage),
        'labels' => ['next' => __('tours.next'), 'previous' => __('tours.previous'), 'done' => __('tours.done'), 'close' => __('tours.close'), 'unavailable' => __('tours.unavailable'), 'changed' => __('tours.changed')],
    ]) }})" class="guided-tour-controller">
        <template x-teleport="body">
            <div data-tour-launchers class="guided-tour-launchers" x-cloak x-show="$store.guidedTourHelp.enabled && !active">
                <template x-for="item in launchers" :key="item.key">
                    <button type="button" class="guided-tour-section" :style="`left:${item.x}px;top:${item.y}px`" :aria-label="item.title" :title="item.title" @click="start(item.key, $event)">
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="9" stroke-width="1.5"/><path d="M9.5 9a2.5 2.5 0 1 1 4 2c-1 .6-1.5 1-1.5 2.5M12 17h.01" stroke-width="1.5" stroke-linecap="round"/></svg>
                    </button>
                </template>
                <span class="sr-only" role="status" x-text="notice"></span>
            </div>
        </template>
    </div>
@endif

@include('filament.resources.tour-author-page')
