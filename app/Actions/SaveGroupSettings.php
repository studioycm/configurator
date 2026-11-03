<?php

namespace App\Actions;

use App\Models\Group;
use App\Models\GroupFilter;
use App\Models\GroupTagCategory;
use App\Models\SubGroup;
use App\Models\User;
use App\Services\CatalogDiscovery;
use App\Services\CatalogIntegrity;
use App\Services\CatalogPolicy;
use App\Services\CatalogPresentation;
use App\Services\CatalogRevisions;
use App\Services\ProductFields;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaveGroupSettings
{
    public function __construct(private CatalogIntegrity $integrity, private CatalogDiscovery $discovery, private CatalogRevisions $revisions, private CatalogPresentation $presentation) {}

    /** @param array<string,mixed> $changes */
    public function patchPresentation(User $actor, Group $group, array $changes, array $removeOverrides = [], ?string $expectedPresentationFingerprint = null): Group
    {
        Gate::forUser($actor)->authorize('manage-catalog');

        return DB::transaction(function () use ($group, $changes, $removeOverrides, $expectedPresentationFingerprint): Group {
            $record = $this->integrity->lockGroups()->get($group->id);
            abort_if($record === null, 404);
            if ($changes === [] && $removeOverrides === []) {
                return $record;
            }
            $validated = $changes === [] ? [] : Validator::make(['settings' => ['result_settings' => $changes]], $this->resultRules())->validate()['settings']['result_settings'];
            $normalized = $this->normalizeResults($validated);
            $results = $this->mergeResults($record, $normalized, false, $removeOverrides, $expectedPresentationFingerprint);
            if (array_intersect(array_keys($changes), ['default_page_size', 'allow_page_size_change', 'page_size_options']) !== []) {
                $effective = CatalogPolicy::resultSettings($results);
                if (($results['allow_page_size_change'] ?? false) === true && ! in_array($effective['default_page_size'], $effective['page_size_options'], true)) {
                    throw ValidationException::withMessages(['settings.result_settings.page_size_options' => __('catalog.groups.validation.include_default_page_size')]);
                }
            }
            if (! $this->sameMap($record->result_settings ?? [], $results)) {
                $settingsChanged = $this->effectiveSettingsChanged($record, $results);
                $record->result_settings = $results;
                $record->save();
                if ($settingsChanged) {
                    $this->revisions->advance([$record->id]);
                }
            }

            return $record;
        }, attempts: 1);
    }

    /** @param array<string, mixed> $data */
    public function handle(User $actor, Group $group, array $data, ?string $expectedPresentationFingerprint = null): Group
    {
        Gate::forUser($actor)->authorize('manage-catalog');

        return DB::transaction(function () use ($group, $data, $expectedPresentationFingerprint): Group {
            $record = $this->integrity->lockGroups()->get($group->id);
            abort_if($record === null, 404);
            $filters = $record->filters()->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $presets = $record->subGroups()->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $validated = Validator::make(['settings' => $data], [
                'settings' => ['required', 'array:filters,sub_groups,result_settings'],
                'settings.filters' => ['present', 'array', 'list'],
                'settings.filters.*' => ['array:id,property_key,label,values'],
                'settings.filters.*.id' => ['nullable', 'integer', 'distinct'],
                'settings.filters.*.property_key' => ['required', 'string', 'max:100', 'distinct:strict', ProductFields::referenceRule()],
                'settings.filters.*.label' => ['required', 'string', 'max:255'],
                'settings.filters.*.values' => ['present', 'array', 'list'],
                'settings.filters.*.values.*' => ['array:value,label'],
                'settings.filters.*.values.*.value' => ['required', 'string'],
                'settings.filters.*.values.*.label' => ['nullable', 'string', 'max:255'],
                'settings.sub_groups' => ['present', 'array', 'list'],
                'settings.sub_groups.*' => ['array:id,label,property_key,allowed_values'],
                'settings.sub_groups.*.id' => ['nullable', 'integer', 'distinct'],
                'settings.sub_groups.*.label' => ['required', 'string', 'max:255'],
                'settings.sub_groups.*.property_key' => ['required', 'string', 'max:100', ProductFields::referenceRule()],
                'settings.sub_groups.*.allowed_values' => ['required', 'array', 'list', 'min:1'],
                'settings.sub_groups.*.allowed_values.*' => ['required', 'string'],
                ...$this->resultRules(),
            ])->validate()['settings'];
            if ($validated['filters'] !== [] || $validated['sub_groups'] !== []) {
                $this->integrity->assertLeaf($record);
            }
            $vocabulary = [];
            $errors = [];
            foreach (['filters', 'sub_groups'] as $bucket) {
                $owned = $bucket === 'filters' ? $filters : $presets;
                foreach ($validated[$bucket] as $index => $row) {
                    if (isset($row['id']) && ! $owned->has($row['id'])) {
                        $errors['settings.'.$bucket.'.'.$index.'.id'] = __('catalog.groups.validation.row_not_owned');
                    }
                    $key = $row['property_key'];
                    $vocabulary[$key] ??= array_column($this->discovery->vocabulary($record->id, $key), 'value');
                    $values = $bucket === 'filters' ? array_column($row['values'], 'value') : $row['allowed_values'];
                    $retained = [];
                    $original = isset($row['id']) ? $owned->get($row['id']) : null;
                    if ($original !== null && $original->property_key === $key) {
                        $retained = $bucket === 'filters'
                            ? [...($original->value_order ?? []), ...array_map(strval(...), array_keys($original->value_labels ?? []))]
                            : ($original->allowed_values ?? []);
                    }
                    if (count(array_unique($values, SORT_STRING)) !== count($values) || array_diff($values, [...$vocabulary[$key], ...$retained]) !== []) {
                        $field = $bucket === 'filters' ? 'values' : 'allowed_values';
                        $errors['settings.'.$bucket.'.'.$index.'.'.$field] = __('catalog.groups.validation.choose_current_distinct_values');
                    }
                }
            }
            $results = $this->mergeResults($record, $this->normalizeResults($validated['result_settings']), true, [], $expectedPresentationFingerprint);
            if ($results['allow_page_size_change'] && ! in_array($results['default_page_size'], $results['page_size_options'], true)) {
                $errors['settings.result_settings.page_size_options'] = __('catalog.groups.validation.include_default_page_size');
            }
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
            $retainedFilters = array_filter(array_column($validated['filters'], 'id'));
            $retainedPresets = array_filter(array_column($validated['sub_groups'], 'id'));
            $removedFilters = $filters->keys()->diff($retainedFilters)->all();
            $removedPresets = $presets->keys()->diff($retainedPresets)->all();
            $changed = $removedFilters !== [] && $record->filters()->whereKey($removedFilters)->delete() > 0;
            $changed = ($removedPresets !== [] && $record->subGroups()->whereKey($removedPresets)->delete() > 0) || $changed;
            $reservedKeys = array_fill_keys([...$filters->pluck('property_key')->all(), ...array_column($validated['filters'], 'property_key')], true);
            foreach ($validated['filters'] as $row) {
                if (isset($row['id']) && $filters[$row['id']]->property_key !== $row['property_key']) {
                    do {
                        $temporaryKey = '__moving_'.Str::uuid();
                    } while (isset($reservedKeys[$temporaryKey]));
                    $reservedKeys[$temporaryKey] = true;
                    GroupFilter::whereKey($row['id'])->update(['property_key' => $temporaryKey]);
                }
            }
            foreach ($validated['filters'] as $order => $row) {
                $filter = isset($row['id']) ? $filters[$row['id']] : new GroupFilter(['group_id' => $record->id]);
                $labels = [];
                foreach ($row['values'] as $value) {
                    if (($value['label'] ?? '') !== '' && $value['label'] !== null) {
                        $labels[$value['value']] = $value['label'];
                    }
                }
                $orderValues = array_column($row['values'], 'value');
                if ($filter->exists && $filter->property_key === $row['property_key']) {
                    $effectiveOrder = array_values(array_unique([...($filter->value_order ?? []), ...$vocabulary[$row['property_key']]], SORT_STRING));
                    if ($orderValues === $effectiveOrder) {
                        $orderValues = $filter->value_order ?? [];
                    }
                }
                $filter->fill(['property_key' => $row['property_key'], 'label' => $row['label'], 'sort_order' => $order, 'value_order' => $orderValues, 'value_labels' => $this->sameMap($filter->value_labels ?? [], $labels) ? ($filter->value_labels ?? []) : $labels]);
                if ($filter->isDirty()) {
                    $filter->save();
                    $changed = true;
                }
            }
            foreach ($validated['sub_groups'] as $order => $row) {
                $preset = isset($row['id']) ? $presets[$row['id']] : new SubGroup(['group_id' => $record->id]);
                $preset->fill(['label' => $row['label'], 'property_key' => $row['property_key'], 'allowed_values' => $row['allowed_values'], 'sort_order' => $order]);
                if ($preset->isDirty()) {
                    $preset->save();
                    $changed = true;
                }
            }
            $settingsChanged = $this->effectiveSettingsChanged($record, $results);
            if (! $this->sameMap($record->result_settings ?? [], $results)) {
                $record->result_settings = $results;
            }
            if ($record->isDirty()) {
                $record->save();
                $changed = $changed || $settingsChanged;
            }

            if ($changed) {
                $this->revisions->advance([$record->id]);
            }

            return $record;
        }, attempts: 3);
    }

    /** @return array<string,list<mixed>> */
    private function resultRules(): array
    {
        return [
            'settings.result_settings' => ['required', 'array:default_page_size,allow_page_size_change,page_size_options,card_properties,editor_properties,cards_per_row,max_results,products_debounce_ms,card_only_differences,card_show_labels,card_property_layout,card_property_columns,card_padding_block,card_padding_inline,catalog_presentation'],
            'settings.result_settings.catalog_presentation' => ['sometimes', 'array'],
            'settings.result_settings.default_page_size' => ['sometimes', 'required', 'integer', 'min:1', 'max:'.CatalogPolicy::MAX_PAGE_SIZE],
            'settings.result_settings.allow_page_size_change' => ['sometimes', 'required', 'boolean'],
            'settings.result_settings.page_size_options' => ['sometimes', 'array', 'list'],
            'settings.result_settings.page_size_options.*' => ['required', 'integer', 'distinct', 'min:1', 'max:'.CatalogPolicy::MAX_PAGE_SIZE],
            'settings.result_settings.products_debounce_ms' => ['sometimes', 'required', 'integer', 'min:0', 'multiple_of:100', 'max:2147483600'],
            'settings.result_settings.card_properties' => ['sometimes', 'array', 'list'],
            'settings.result_settings.card_properties.*' => ['required', 'string', 'max:100', 'distinct:strict', ProductFields::referenceRule()],
            'settings.result_settings.editor_properties' => ['sometimes', 'array', 'list'],
            'settings.result_settings.editor_properties.*' => ['required', 'string', 'max:100', 'distinct:strict', ProductFields::referenceRule()],
            'settings.result_settings.cards_per_row' => ['sometimes', 'required', 'integer', 'min:1', 'max:'.CatalogPolicy::MAX_CARDS_PER_ROW],
            'settings.result_settings.max_results' => ['sometimes', 'required', Rule::in(['all', ...range(1, CatalogPolicy::MAX_RESULT_THRESHOLD)])],
            'settings.result_settings.card_only_differences' => ['sometimes', 'required', 'boolean'],
            'settings.result_settings.card_show_labels' => ['sometimes', 'required', 'boolean'],
            'settings.result_settings.card_property_layout' => ['sometimes', 'required', Rule::in(array_keys(CatalogPolicy::CARD_LAYOUTS))],
            'settings.result_settings.card_property_columns' => ['sometimes', 'required', 'integer', Rule::in([1, 2, 3])],
            'settings.result_settings.card_padding_block' => ['sometimes', 'required', 'integer', 'between:0,16'],
            'settings.result_settings.card_padding_inline' => ['sometimes', 'required', 'integer', 'between:0,20'],
        ];
    }

    /** @param array<string,mixed> $changes @return array<string,mixed> */
    private function normalizeResults(array $changes): array
    {
        foreach ($changes as $key => $value) {
            $changes[$key] = match ($key) {
                'default_page_size', 'products_debounce_ms', 'cards_per_row', 'card_property_columns', 'card_padding_block', 'card_padding_inline' => (int) $value,
                'allow_page_size_change', 'card_only_differences', 'card_show_labels' => (bool) $value,
                'page_size_options' => array_map('intval', $value),
                'max_results' => $value === 'all' ? 'all' : (int) $value,
                default => $value,
            };
        }

        return $changes;
    }

    /**
     * @param  array<string,mixed>  $changes
     * @param  list<string>  $removeOverrides
     * @return array<string,mixed>
     */
    private function mergeResults(Group $record, array $changes, bool $full, array $removeOverrides, ?string $expectedFingerprint): array
    {
        $raw = $record->result_settings ?? [];
        $results = array_replace($full ? array_replace($raw, CatalogPolicy::resultSettings($raw)) : $raw, $changes);
        $ownsOverrides = array_key_exists('catalog_presentation', $changes);
        if (! $ownsOverrides && $removeOverrides === []) {
            return $results;
        }
        $path = 'settings.result_settings.catalog_presentation';
        if ($expectedFingerprint === null || ! hash_equals($this->presentation->groupFingerprint($record), $expectedFingerprint)) {
            throw ValidationException::withMessages([$path => 'Group presentation changed. Reload it before saving.']);
        }
        $keys = $this->presentation->overrideKeys();
        if (! array_is_list($removeOverrides) || count(array_unique($removeOverrides, SORT_REGULAR)) !== count($removeOverrides)
            || array_filter($removeOverrides, fn (mixed $key): bool => ! is_string($key) || ! in_array($key, $keys, true)) !== []) {
            throw ValidationException::withMessages([$path => 'Reset only known Group presentation options.']);
        }
        try {
            $submitted = $this->presentation->validateOverrides($changes['catalog_presentation'] ?? []);
        } catch (ValidationException $exception) {
            $errors = [];
            foreach ($exception->errors() as $key => $messages) {
                $errors[$path.substr($key, strlen('settings'))] = $messages;
            }
            throw ValidationException::withMessages($errors);
        }
        if (array_intersect(array_keys($submitted), $removeOverrides) !== []) {
            throw ValidationException::withMessages([$path => 'Choose either an override or reset for each option.']);
        }
        $own = is_array($raw['catalog_presentation'] ?? null) ? $raw['catalog_presentation'] : [];
        foreach (['product_image_placement', 'product_image_scale'] as $key) {
            if (array_key_exists($key, $submitted) && (! array_key_exists($key, $own) || $own[$key] !== $submitted[$key])) {
                $this->integrity->assertLeaf($record);
            }
        }
        if (array_key_exists('group_card_tag_categories', $submitted)) {
            $ids = $submitted['group_card_tag_categories'];
            $current = GroupTagCategory::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get(['id']);
            if ($current->count() !== count($ids)) {
                throw ValidationException::withMessages([$path.'.group_card_tag_categories' => 'Choose current tag categories.']);
            }
        }
        $retained = $full && $ownsOverrides ? array_diff_key($own, array_flip($keys)) : $own;
        $results['catalog_presentation'] = array_replace(array_diff_key($retained, array_flip($removeOverrides)), $submitted);

        return $results;
    }

    /** @param array<string,mixed> $results */
    private function effectiveSettingsChanged(Group $record, array $results): bool
    {
        return ! $this->sameMap(CatalogPolicy::resultSettings($record->result_settings), CatalogPolicy::resultSettings($results))
            || ! $this->sameMap($record->result_settings['catalog_presentation'] ?? [], $results['catalog_presentation'] ?? []);
    }

    /** @param array<string|int, mixed> $first @param array<string|int, mixed> $second */
    private function sameMap(mixed $first, mixed $second): bool
    {
        if (! is_array($first) || ! is_array($second)) {
            return $first === $second;
        }
        foreach (['catalog_presentation'] as $key) {
            if (isset($first[$key], $second[$key]) && is_array($first[$key]) && is_array($second[$key])) {
                ksort($first[$key]);
                ksort($second[$key]);
            }
        }
        ksort($first);
        ksort($second);

        return $first === $second;
    }
}
