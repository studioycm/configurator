@can('manage-catalog')
    @if ($tourPage !== null && !($tourPage === 'images' && !config('catalog-assets.enabled')) && !($tourPage === 'writing' && !config('configurator-writing.enabled')))
        <div wire:ignore wire:key="tour-author-page-{{ $tourPage }}" x-data="guidedTourAuthorPage(@js([
            'page' => $tourPage, 'catalog' => \App\Services\GuidedTours::targetCatalog($tourPage),
            'labels' => ['next' => __('tours.next'), 'previous' => __('tours.previous'), 'done' => __('tours.done'), 'close' => __('tours.close'), 'unavailable' => __('tours.unavailable'), 'changed' => __('tours.changed'), 'wrongPage' => __('tours.wrong_page'), 'notRegistered' => __('tours.not_registered'), 'returnEditor' => __('tours.return_editor')],
        ]))" class="guided-tour-controller">
            <template x-teleport="body">
                <div x-show="authoring" x-cloak data-tour-author-tools>
                    <div class="tour-author-toolbar" x-show="!active">
                        <strong>{{ __('tours.draft_mode') }}</strong>
                        <label>{{ __('tours.target') }}<select x-model="selected" @change="selectTarget()"><option value="">{{ __('tours.choose_target') }}</option><template x-for="item in targets" :key="item.key"><option :value="item.key" x-text="item.label + (item.available ? '' : @js(__('tours.not_visible')))"></option></template></select></label>
                        <button type="button" @click="pick()">{{ __('tours.pick') }}</button>
                        <button type="button" @click="locate()">{{ __('tours.locate') }}</button>
                        <button type="button" x-show="picking" @click="useTarget()">{{ __('tours.use_target') }}</button>
                        <button type="button" @click="preview()">{{ __('tours.preview') }}</button>
                        <button type="button" @click="exit()">{{ __('ui.actions.close') }}</button>
                        <span role="status" x-text="notice"></span>
                    </div>
                    <div x-show="rect && !active" class="tour-author-outline" :style="rect ? `left:${rect.left}px;top:${rect.top}px;width:${rect.width}px;height:${rect.height}px` : ''"></div>
                </div>
            </template>
        </div>
    @endif
@endcan
