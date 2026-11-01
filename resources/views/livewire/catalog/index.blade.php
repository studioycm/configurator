<div>
    @if ($invalidState)
        <section data-catalog-invalid-state role="alert" class="catalog-browse-state"><h2>{{ __('catalog.browse.invalid') }}</h2><a href="{{ route('catalog.index') }}">{{ __('catalog.browse.return_all') }}</a></section>
    @else
        <div wire:ignore x-data="catalogNavigation" data-catalog-navigation data-catalog-index-url="{{ route('catalog.index') }}" data-journey-return="{{ \Illuminate\Support\Js::encode($journeyReturnForClient) }}" data-journey-overflow="{{ __('catalog.journey.overflow') }}" data-labels="{{ \Illuminate\Support\Js::encode(__('catalog.browse.messages')) }}" :aria-busy="loading" @keydown.escape.window="if (criteriaOpen) closeCriteria()">
            <script type="application/json" data-navigation-projection>{!! \Illuminate\Support\Js::encode($navigation) !!}</script>
            <div class="catalog-browse-toolbar" :inert="criteriaOpen && drawer">
                <form class="catalog-group-search" role="search" @submit.prevent="searchNow()"><label for="catalog-group-search" class="sr-only">{{ __('catalog.browse.search') }}</label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="10" cy="10" r="6"/><path d="m15 15 5 5"/></svg><input id="catalog-group-search" data-catalog-focus="search" type="search" placeholder="{{ __('catalog.browse.search') }}" x-model="searchText" @input="searchLater($event.target.value)" /><button type="submit">{{ __('catalog.browse.search_action') }}</button></form>
                <button type="button" x-ref="criteriaTrigger" @click="openCriteria()" :aria-expanded="criteriaOpen" aria-controls="catalog-group-criteria" class="catalog-browse-button">{{ __('catalog.browse.group_filters') }} <span x-show="selectedAttributes.length" x-text="selectedAttributes.length" class="catalog-browse-badge"></span><span aria-hidden="true">⌄</span></button>
                <button type="button" class="catalog-browse-button" @click="refresh()" :disabled="loading" title="{{ __('catalog.browse.refresh') }}">↻ <span class="sr-only">{{ __('catalog.browse.refresh') }}</span></button>
            </div>
            <x-catalog.criteria-panel />
            <div :inert="criteriaOpen && drawer">
                @if ($journeyNotice)<p class="catalog-browse-notice" role="status">{{ $journeyNotice }}</p>@endif
                <p x-cloak x-show="journeyError" class="catalog-browse-notice" role="status" x-text="journeyError"></p>
                <div x-cloak x-show="error" role="alert" class="catalog-browse-state"><span x-text="error"></span><button type="button" @click="retry()">{{ __('catalog.browse.retry') }}</button><button type="button" @click="reset()">{{ __('catalog.browse.clear_choices') }}</button></div>
                <p x-cloak x-show="notice" role="status" class="catalog-browse-notice" x-text="notice"></p>
                <p x-cloak x-show="projection.presentationRecovery" role="status" class="catalog-browse-notice">{{ __('catalog.browse.recovery') }}</p>
                <div class="catalog-browse-context"><nav aria-label="{{ __('catalog.browse.branch_path') }}"><button type="button" @click="chooseBranch(null)">{{ __('catalog.browse.all_models') }}</button><template x-for="ancestor in projection.ancestors" :key="ancestor.id"><span><span aria-hidden="true"> / </span><button type="button" @click="chooseBranch(ancestor.id)" x-text="ancestor.name"></button></span></template><span x-show="projection.branch"><span aria-hidden="true"> / </span><strong x-text="projection.branch?.name"></strong></span></nav><span x-show="loading" role="status">{{ __('catalog.browse.loading') }}</span></div>
                <div class="catalog-active-choices" x-cloak x-show="criteria.tagValueIds.length || selectedAttributes.length || criteria.search"><span>{{ __('catalog.browse.active') }}</span><template x-for="tag in selectedTags" :key="tag.id"><button type="button" @click="toggleTag(tag.id)"><span x-text="tag.label"></span><span aria-hidden="true"> ×</span></button></template><template x-for="attribute in selectedAttributes" :key="JSON.stringify([attribute.key,attribute.value])"><button type="button" @click="toggleAttribute(attribute.key,attribute.value)"><span class="whitespace-pre-wrap" x-text="attribute.label + ' · ' + attribute.value"></span><span aria-hidden="true"> ×</span></button></template><span x-show="criteria.search" x-text="criteria.search"></span><button type="button" @click="reset()">{{ __('catalog.browse.reset') }}</button></div>
                <div class="catalog-browse-layout">
                    <aside class="catalog-group-sidebar" aria-label="{{ __('catalog.browse.group_navigation') }}">
                        <div class="catalog-sidebar-heading"><h2>{{ __('catalog.browse.explore') }}</h2><button type="button" @click="sidebarOpen=!sidebarOpen" :aria-expanded="sidebarOpen" aria-controls="catalog-group-navigation">{{ __('catalog.browse.show_hide') }}</button></div>
                        <div id="catalog-group-navigation" x-show="sidebarOpen">
                            <div class="catalog-navigation-modes" aria-label="{{ __('catalog.browse.navigation_mode') }}"><button type="button" @click="chooseMode('tree')" :aria-pressed="criteria.mode === 'tree'">{{ __('catalog.browse.group_tree') }}</button><button type="button" @click="chooseMode('tags')" :aria-pressed="criteria.mode === 'tags'">{{ __('catalog.browse.tags') }}</button></div>
                            <div x-cloak x-show="criteria.mode === 'tree'"><x-catalog.group-tree /></div>
                            <div x-cloak x-show="criteria.mode === 'tags'"><x-catalog.group-tags /></div>
                        </div>
                    </aside>
                    <section class="catalog-model-results" aria-label="{{ __('catalog.browse.select_group') }}">
                        <div class="catalog-model-results-heading"><div><h2 x-text="familyHeading(@js(__('catalog.browse.branch_families', ['name' => ':name'])), @js(__('catalog.browse.select_group')))">{{ $navigation['branch'] ? __('catalog.browse.branch_families', ['name' => $navigation['branch']['name']]) : __('catalog.browse.select_group') }}</h2><p class="catalog-model-reference" x-show="projection.ancestors.length" x-text="projection.ancestors.map(ancestor => ancestor.name).join(' / ')">{{ implode(' / ', array_column($navigation['ancestors'], 'name')) }}</p></div><p class="catalog-model-count" aria-live="polite"><span x-text="projection.total">{{ $navigation['total'] }}</span> {{ __('catalog.browse.matches') }}</p></div>
                        <div x-show="!hydrated" class="catalog-model-grid" style="--model-columns:{{ $navigation['presentation']['group_cards_per_row'] }}">
                            @foreach ($navigation['cards'] as $card)<x-catalog.group-card :card="$card" />@endforeach
                        </div>
                        <div x-cloak x-show="hydrated" class="catalog-model-grid" :data-density="projection.presentation.group_card_density" :style="'--model-columns:' + projection.presentation.group_cards_per_row"><template x-for="card in projection.cards" :key="card.id"><x-catalog.group-card :dynamic="true" /></template></div>
                        <section x-show="!projection.total" class="catalog-browse-state"><h2 x-text="criteria.branchId === null && !criteria.search && !criteria.tagValueIds.length && !selectedAttributes.length ? @js(__('catalog.frontend.copy.the_catalog_is_being_prepared')) : @js(__('catalog.browse.no_matches'))">{{ __('catalog.frontend.copy.the_catalog_is_being_prepared') }}</h2><p>{{ __('catalog.browse.empty') }}</p><button type="button" @click="reset()" x-show="criteria.search || criteria.tagValueIds.length || selectedAttributes.length">{{ __('catalog.browse.reset') }}</button></section>
                        <section x-cloak x-show="projection.total && !projection.cards.length" class="catalog-browse-state"><p>{{ __('catalog.browse.page_empty') }}</p><button type="button" @click="page(1)">{{ __('catalog.browse.first_page') }}</button></section>
                        <nav x-cloak x-show="criteria.page > 1 || projection.hasMore" class="catalog-model-pagination" aria-label="{{ __('catalog.browse.pagination') }}"><button type="button" @click="page(criteria.page - 1)" :disabled="loading || criteria.page <= 1">{{ __('catalog.browse.previous') }}</button><span>{{ __('catalog.browse.page') }} <span x-text="criteria.page"></span></span><button type="button" @click="page(criteria.page + 1)" :disabled="loading || !projection.hasMore">{{ __('catalog.browse.next') }}</button></nav>
                    </section>
                </div>
                <noscript><p class="catalog-browse-state">{{ __('catalog.browse.enable_js') }}</p></noscript>
            </div>
        </div>
    @endif
</div>
