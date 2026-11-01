<div x-cloak x-show="criteriaOpen" class="catalog-criteria-backdrop" :class="{'is-drawer': drawer}" @click.self="closeCriteria()">
    <section x-ref="criteriaDialog" id="catalog-group-criteria" class="catalog-criteria-panel" :class="{'is-drawer': drawer}" :role="drawer ? 'dialog' : 'region'" :aria-modal="drawer && criteriaOpen ? 'true' : null" aria-label="{{ __('catalog.browse.group_filters') }}" @keydown="trapCriteria($event)" @keydown.escape.stop="closeCriteria()" :data-columns="projection.presentation.criteria_columns" :data-choices="projection.presentation.criteria_choices">
        <div class="catalog-criteria-heading"><h2>{{ __('catalog.browse.group_filters') }}</h2><button type="button" @click="closeCriteria()">{{ __('catalog.browse.close') }} <span aria-hidden="true">×</span></button></div>
        <p class="catalog-browse-muted" x-show="!projection.attributes.length">{{ __('catalog.browse.no_attributes') }}</p>
        <div class="catalog-criteria-columns">
            <template x-for="category in projection.attributes" :key="category.key">
                <fieldset><legend x-text="category.label"></legend><div>
                    <template x-for="value in category.values" :key="JSON.stringify([category.key,value.value])">
                        <button type="button" role="checkbox" :aria-checked="(intendedCriteria.attributeValues[category.key] || []).includes(value.value)" @click="toggleAttribute(category.key,value.value)" class="catalog-criteria-choice" :title="value.available ? '' : @js(__('catalog.browse.attribute_unavailable'))"><span class="catalog-choice-mark" aria-hidden="true" x-text="(intendedCriteria.attributeValues[category.key] || []).includes(value.value) ? '✓' : ''"></span><span class="whitespace-pre-wrap" x-text="value.value"></span><small x-text="value.count" :title="@js(__('catalog.browse.model_count'))"></small></button>
                    </template>
                </div></fieldset>
            </template>
        </div>
        <div class="catalog-criteria-footer"><button type="button" @click="clearAttributes()">{{ __('catalog.browse.clear_attributes') }}</button><span>{{ __('catalog.browse.count_basis') }}</span></div>
    </section>
</div>
