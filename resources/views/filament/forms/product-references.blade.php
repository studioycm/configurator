<dl class="catalog-product-references">
    @foreach ([__('catalog.resources.groups.singular') => $getRecord()?->group?->name, __('catalog.fields.legacy_id') => $getRecord()?->legacy_id, __('catalog.products.labels.legacy_group_id') => $getRecord()?->legacy_group_id] as $label => $value)
        <div><dt>{{ $label }}</dt><dd>{{ filled($value) ? $value : '—' }}</dd></div>
    @endforeach
</dl>
