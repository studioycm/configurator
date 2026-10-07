<?php

namespace App\Services;

use App\DTO\CatalogSnapshot;
use App\Models\Group;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BuildCatalogSnapshot
{
    public function build(int $groupId): CatalogSnapshot
    {
        $snapshot = DB::transaction(function () use ($groupId): CatalogSnapshot {
            app(CatalogAvailability::class)->assertGroup($groupId);
            $group = Group::query()->withExists('children')->findOrFail($groupId);
            abort_if($group->children_exists, 409, 'This Group is now a branch.');
            $registered = CatalogImportParser::propertyKeys();
            $filters = $group->filters()->orderBy('sort_order')->orderBy('id')->get()->filter(fn ($filter) => in_array($filter->property_key, $registered, true));
            $presets = $group->subGroups()->orderBy('sort_order')->orderBy('id')->get()->filter(fn ($preset) => in_array($preset->property_key, $registered, true));
            $keys = array_values(array_unique([...$filters->pluck('property_key')->all(), ...$presets->pluck('property_key')->all()]));
            $query = DB::table('products')->where('group_id', $groupId)->where('is_active', true)->orderBy('id')->select('id');
            foreach ($keys as $column => $key) {
                $expression = DB::getDriverName() === 'sqlite' ? 'json_quote(json_extract(properties, ?))' : 'json_extract(properties, ?)';
                $query->selectRaw($expression.' as cell_'.$column, ['$."'.$key.'"']);
            }
            $dictionaries = array_fill(0, count($keys), []);
            $indexes = array_fill(0, count($keys), []);
            $invalid = [];
            $rows = [];
            foreach ($query->get() as $product) {
                $row = [(string) $product->id];
                foreach ($keys as $column => $key) {
                    $raw = $product->{'cell_'.$column};
                    $value = $raw === null ? null : json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
                    if ($value === null || $value === '') {
                        $row[] = -1;
                    } elseif (! is_string($value)) {
                        $invalid[$key] = ($invalid[$key] ?? 0) + 1;
                        $row[] = -1;
                    } else {
                        $indexKey = 'value:'.$value;
                        if (! array_key_exists($indexKey, $indexes[$column])) {
                            $indexes[$column][$indexKey] = count($dictionaries[$column]);
                            $dictionaries[$column][] = $value;
                        }
                        $row[] = $indexes[$column][$indexKey];
                    }
                }
                $rows[] = $row;
            }
            $fields = [];
            foreach ($filters as $filter) {
                $column = array_search($filter->property_key, $keys, true);
                $ordered = array_values(array_unique([...($filter->value_order ?? []), ...$dictionaries[$column]], SORT_STRING));
                $options = [];
                foreach ($ordered as $value) {
                    if (is_string($value) && array_key_exists('value:'.$value, $indexes[$column])) {
                        $options[] = ['value' => $value, 'label' => $filter->value_labels[$value] ?? $value, 'code' => $indexes[$column]['value:'.$value]];
                    }
                }
                $fields[] = ['key' => $filter->property_key, 'label' => $filter->label, 'column' => $column, 'options' => $options];
            }
            $encodedPresets = [];
            foreach ($presets as $preset) {
                $column = array_search($preset->property_key, $keys, true);
                $values = array_values(array_unique(array_filter($preset->allowed_values ?? [], fn ($value) => is_string($value) && $value !== ''), SORT_STRING));
                $encodedPresets[] = ['id' => (string) $preset->id, 'label' => $preset->label, 'key' => $preset->property_key, 'column' => $column, 'values' => $values,
                    'codes' => array_values(array_filter(array_map(fn ($value) => $indexes[$column]['value:'.$value] ?? -1, $values), fn ($code) => $code >= 0))];
            }
            $settings = CatalogPolicy::resultSettings($group->result_settings);
            if ($settings['card_properties'] === []) {
                $settings['card_properties'] = $filters->pluck('property_key')->all();
            }
            $settings = array_intersect_key($settings, array_flip(['card_properties', 'cards_per_row', 'max_results', 'products_debounce_ms', 'card_only_differences', 'card_show_labels', 'card_property_layout', 'card_property_columns', 'card_padding_block', 'card_padding_inline']));
            $ancestors = $group->ancestorTrail();

            return new CatalogSnapshot([
                'schema' => CatalogSnapshot::SCHEMA, 'groupId' => (string) $group->id, 'revision' => (string) $group->catalog_revision,
                'fields' => $fields, 'presets' => $encodedPresets, 'settings' => $settings,
                'columns' => array_map(fn ($key, $values) => ['key' => $key, 'values' => $values], $keys, $dictionaries), 'rows' => $rows,
                'cardGroup' => ['name' => $group->name, 'mainName' => $ancestors->first()?->name],
                'diagnostics' => array_map(fn ($key, $count) => ['key' => $key, 'count' => $count], array_keys($invalid), array_values($invalid)),
            ]);
        });
        if ($snapshot->data['diagnostics'] !== []) {
            Log::warning('Catalog snapshot omitted malformed property cells.', ['group_id' => $groupId, 'revision' => $snapshot->revision(), 'fields' => $snapshot->data['diagnostics']]);
        }

        return $snapshot;
    }
}
