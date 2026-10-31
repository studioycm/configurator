<div class="catalog-sidebar-tags" :data-style="projection.presentation.tag_presentation" :data-density="projection.presentation.tag_density" :style="'--tag-columns:' + projection.presentation.tag_columns">
    <p class="catalog-browse-muted" x-show="!projection.tags.length">{{ __('catalog.browse.no_tags') }}</p>
    <template x-for="category in projection.tags" :key="category.id">
        <fieldset>
            <legend x-text="category.label"></legend>
            <div class="catalog-tag-options">
                <template x-for="value in category.values" :key="value.id">
                    <button type="button" :data-catalog-focus="'sidebar-tag-' + value.id" @click="toggleTag(value.id)" :aria-pressed="intendedCriteria.tagValueIds.includes(value.id)" :title="category.label + ' · ' + value.label" class="catalog-tag-choice">
                        <svg x-show="projection.presentation.tag_presentation === 'cards'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M12 3c-2 4-6 7-6 11a6 6 0 0 0 12 0c0-4-4-7-6-11Z"/><path d="M9 15c0 2 1 3 3 3"/></svg>
                        <span x-text="value.label"></span>
                        <small x-show="projection.presentation.tag_counts" :title="@js(__('catalog.browse.model_count'))" :aria-label="value.count + ' ' + @js(__('catalog.browse.models'))" x-text="value.count"></small>
                    </button>
                </template>
            </div>
        </fieldset>
    </template>
    <p class="catalog-browse-muted" x-show="projection.presentation.tag_counts && projection.tags.length">{{ __('catalog.browse.count_basis') }}</p>
</div>
