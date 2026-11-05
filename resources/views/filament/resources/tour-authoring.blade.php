<div wire:key="tour-authoring-{{ $getContainer()->getStatePath() }}" x-data="tourAuthor(@js([
    'urls' => \App\Services\GuidedTours::pageUrls(),
    'targets' => array_map(fn ($page) => \App\Services\GuidedTours::targets($page), array_combine(array_keys(\App\Services\GuidedTours::pages()), array_keys(\App\Services\GuidedTours::pages()))),
    'labels' => ['newStep' => __('tours.new_step'), 'draft' => __('tours.draft'), 'picked' => __('tours.picked'), 'chooseStep' => __('tours.choose_step'), 'addressRequired' => __('tours.address_required'), 'badAddress' => __('tours.bad_address'), 'popup' => __('tours.popup'), 'opened' => __('tours.opened')],
]), $wire, @js($getContainer()->getStatePath()))" class="tour-authoring-panel">
    <h3>{{ __('tours.try_on_page') }}</h3>
    <p>{{ __('tours.author_help') }}</p>
    <div class="tour-authoring-fields">
        <label>{{ __('tours.workspace_address') }}<input type="url" x-model="address" :placeholder="@js(__('tours.address_placeholder'))" /></label>
        <label>{{ __('tours.step_to_locate') }}<select x-model="step"><option value="">{{ __('tours.first_step') }}</option><template x-for="item in steps" :key="item.id"><option :value="item.id" x-text="item.title"></option></template></select></label>
    </div>
    <div class="tour-authoring-actions">
        <x-filament::button type="button" color="gray" icon="heroicon-o-viewfinder-circle" x-on:click="open('locate')">{{ __('tours.locate') }}</x-filament::button>
        <x-filament::button type="button" color="gray" icon="heroicon-o-cursor-arrow-rays" x-on:click="open('pick')">{{ __('tours.pick') }}</x-filament::button>
        <x-filament::button type="button" color="gray" icon="heroicon-o-play" x-on:click="open('preview')">{{ __('tours.preview') }}</x-filament::button>
    </div>
    <p role="status" x-text="notice"></p>
</div>
