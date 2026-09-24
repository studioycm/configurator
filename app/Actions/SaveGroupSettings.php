<?php

namespace App\Actions;

use App\Models\Group;
use App\Models\GroupFilter;
use App\Models\SubGroup;
use App\Models\User;
use App\Services\CatalogDiscovery;
use App\Services\CatalogImportParser;
use App\Services\CatalogIntegrity;
use App\Services\CatalogPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaveGroupSettings
{
    public function __construct(private CatalogIntegrity $integrity, private CatalogDiscovery $discovery) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, Group $group, array $data): Group
    {
        Gate::forUser($actor)->authorize('manage-catalog');

        return DB::transaction(function () use ($group, $data): Group {
            $record = $this->integrity->lockGroups()->get($group->id);
            abort_if($record === null, 404);
            $filters = $record->filters()->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $presets = $record->subGroups()->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $keys = CatalogImportParser::propertyKeys();
            $validated = Validator::make(['settings' => $data], [
                'settings' => ['required', 'array:filters,sub_groups,result_settings'],
                'settings.filters' => ['present', 'array', 'list', 'max:18'],
                'settings.filters.*' => ['array:id,property_key,label,values'],
                'settings.filters.*.id' => ['nullable', 'integer', 'distinct'],
                'settings.filters.*.property_key' => ['required', Rule::in($keys), 'distinct'],
                'settings.filters.*.label' => ['required', 'string', 'max:255'],
                'settings.filters.*.values' => ['present', 'array', 'list'],
                'settings.filters.*.values.*' => ['array:value,label'],
                'settings.filters.*.values.*.value' => ['required', 'string'],
                'settings.filters.*.values.*.label' => ['nullable', 'string', 'max:255'],
                'settings.sub_groups' => ['present', 'array', 'list'],
                'settings.sub_groups.*' => ['array:id,label,property_key,allowed_values,force_hide'],
                'settings.sub_groups.*.id' => ['nullable', 'integer', 'distinct'],
                'settings.sub_groups.*.label' => ['required', 'string', 'max:255'],
                'settings.sub_groups.*.property_key' => ['required', Rule::in($keys)],
                'settings.sub_groups.*.allowed_values' => ['required', 'array', 'list', 'min:1'],
                'settings.sub_groups.*.allowed_values.*' => ['required', 'string'],
                'settings.sub_groups.*.force_hide' => ['required', 'boolean'],
                'settings.result_settings' => ['required', 'array:default_page_size,allow_page_size_change,page_size_options'],
                'settings.result_settings.default_page_size' => ['required', 'integer', 'min:1', 'max:'.CatalogPolicy::MAX_PAGE_SIZE],
                'settings.result_settings.allow_page_size_change' => ['required', 'boolean'],
                'settings.result_settings.page_size_options' => ['present', 'array', 'list'],
                'settings.result_settings.page_size_options.*' => ['required', 'integer', 'distinct', 'min:1', 'max:'.CatalogPolicy::MAX_PAGE_SIZE],
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
                        $errors['settings.'.$bucket.'.'.$index.'.id'] = 'This row does not belong to the current Group. Reload the editor.';
                    }
                    $key = $row['property_key'];
                    $vocabulary[$key] ??= array_column($this->discovery->vocabulary($record->id, $key), 'value');
                    $values = $bucket === 'filters' ? array_column($row['values'], 'value') : $row['allowed_values'];
                    if (count(array_unique($values, SORT_STRING)) !== count($values) || array_diff($values, $vocabulary[$key]) !== []) {
                        $field = $bucket === 'filters' ? 'values' : 'allowed_values';
                        $errors['settings.'.$bucket.'.'.$index.'.'.$field] = 'Choose distinct values that currently exist in this Group. Remove stale values or reload the editor.';
                    }
                }
            }
            $results = $validated['result_settings'];
            $results['default_page_size'] = (int) $results['default_page_size'];
            $results['allow_page_size_change'] = (bool) $results['allow_page_size_change'];
            $results['page_size_options'] = array_map('intval', $results['page_size_options']);
            if ($results['allow_page_size_change'] && ! in_array($results['default_page_size'], $results['page_size_options'], true)) {
                $errors['settings.result_settings.page_size_options'] = 'Include the default page size in the available choices.';
            }
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
            $retainedFilters = array_filter(array_column($validated['filters'], 'id'));
            $retainedPresets = array_filter(array_column($validated['sub_groups'], 'id'));
            $record->filters()->whereNotIn('id', $retainedFilters)->delete();
            $record->subGroups()->whereNotIn('id', $retainedPresets)->delete();
            foreach ($validated['filters'] as $row) {
                if (isset($row['id']) && $filters[$row['id']]->property_key !== $row['property_key']) {
                    GroupFilter::whereKey($row['id'])->update(['property_key' => '__moving_'.$row['id']]);
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
                }
            }
            foreach ($validated['sub_groups'] as $order => $row) {
                $preset = isset($row['id']) ? $presets[$row['id']] : new SubGroup(['group_id' => $record->id]);
                $preset->fill(['label' => $row['label'], 'property_key' => $row['property_key'], 'allowed_values' => $row['allowed_values'], 'force_hide' => (bool) $row['force_hide'], 'sort_order' => $order]);
                if ($preset->isDirty()) {
                    $preset->save();
                }
            }
            if (! $this->sameMap($record->result_settings ?? [], $results)) {
                $record->result_settings = $results;
            }
            if ($record->isDirty()) {
                $record->save();
            }

            return $record;
        }, attempts: 3);
    }

    /** @param array<string|int, mixed> $first @param array<string|int, mixed> $second */
    private function sameMap(array $first, array $second): bool
    {
        ksort($first);
        ksort($second);

        return $first === $second;
    }
}
