<?php

namespace App\Services;

use App\DTO\CatalogDiscoveryResult;
use App\DTO\CatalogDiscoveryState;
use App\Models\Group;
use App\Models\GroupFilter;
use App\Models\Product;
use App\Models\SubGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CatalogDiscovery
{
    public function __construct(private CatalogFilterReconciler $reconciler) {}

    /** @param array<string, mixed> $raw */
    public function prepare(int $groupId, array $raw, ?string $action = null, mixed $argument = null): CatalogDiscoveryResult
    {
        return DB::transaction(function () use ($groupId, $raw, $action, $argument): CatalogDiscoveryResult {
            $group = Group::with(['filters' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'), 'subGroups' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')])->findOrFail($groupId);
            $registry = CatalogImportParser::propertyKeys();
            $filters = $group->filters->filter(fn (GroupFilter $filter): bool => in_array($filter->property_key, $registry, true))->keyBy('property_key');
            $presets = $group->subGroups->filter(fn (SubGroup $preset): bool => in_array($preset->property_key, $registry, true))->keyBy('id');
            $keys = array_values(array_unique([...$filters->keys()->all(), ...$presets->pluck('property_key')->all()]));
            $vocabulary = [];
            $baseCounts = [];
            foreach ($keys as $key) {
                $baseCounts[$key] = $this->aggregate($this->predicate($groupId, []), $key);
                $vocabulary[$key] = array_column($baseCounts[$key], 'value');
            }
            $this->diagnoseTypes($groupId, $keys);
            $presets = $presets->filter(function (SubGroup $preset) use ($vocabulary): bool {
                $preset->allowed_values = array_values(array_filter($preset->allowed_values ?? [], fn (mixed $value): bool => is_string($value) && in_array($value, $vocabulary[$preset->property_key], true)));

                return $preset->allowed_values !== [];
            });
            $settings = CatalogPolicy::resultSettings($group->result_settings);
            $notices = [];
            $adjusted = array_diff(array_keys($raw), ['version', 'filters', 'precedence', 'subGroupId', 'page', 'perPage']) !== [];
            if (isset($raw['version']) && ! in_array($raw['version'], [1, '1'], true)) {
                $raw = [];
                $adjusted = true;
                $notices[] = 'This catalog link used an unsupported version and was reset.';
            }
            $selected = [];
            if (is_array($raw['filters'] ?? [])) {
                $adjusted = $adjusted || count($raw['filters'] ?? []) > $filters->count();
                foreach (array_slice($raw['filters'] ?? [], 0, $filters->count() + 1, true) as $key => $value) {
                    if ($filters->has($key) && is_string($value) && in_array($value, $vocabulary[$key], true)) {
                        $selected[$key] = $value;
                    } else {
                        $adjusted = true;
                    }
                }
            } else {
                $adjusted = true;
            }
            $presetId = $this->positiveInteger($raw['subGroupId'] ?? null);
            if ($presetId !== null && ! $presets->has($presetId)) {
                $presetId = null;
            }
            if (($raw['subGroupId'] ?? null) !== null && $presetId === null) {
                $adjusted = true;
            }
            $fallback = $presetId ? ['subgroup:'.$presetId] : [];
            foreach ($filters->keys() as $key) {
                if (array_key_exists($key, $selected)) {
                    $fallback[] = 'filter:'.$key;
                }
            }
            $precedence = $raw['precedence'] ?? [];
            if (! is_array($precedence) || ! array_is_list($precedence) || count($precedence) !== count($fallback) || count(array_unique(array_filter($precedence, 'is_string'))) !== count($fallback) || array_diff($fallback, array_filter($precedence, 'is_string')) !== []) {
                $precedence = $fallback;
                $adjusted = $adjusted || $raw !== [];
            }
            $page = $this->positiveInteger($raw['page'] ?? 1) ?? 1;
            $perPage = $this->positiveInteger($raw['perPage'] ?? $settings['default_page_size']) ?? $settings['default_page_size'];
            if (! $settings['allow_page_size_change'] || ! in_array($perPage, $settings['page_size_options'], true)) {
                $perPage = $settings['default_page_size'];
            }
            if ((isset($raw['page']) && $this->positiveInteger($raw['page']) === null) || (isset($raw['perPage']) && $this->positiveInteger($raw['perPage']) !== $perPage)) {
                $adjusted = true;
            }
            if ($action === 'filter') {
                [$key, $value] = is_array($argument) && count($argument) === 2 ? $argument : [null, null];
                if (is_string($key) && $filters->has($key) && is_string($value) && in_array($value, $vocabulary[$key], true)) {
                    $precedence = array_values(array_diff($precedence, ['filter:'.$key]));
                    if (($selected[$key] ?? null) === $value) {
                        unset($selected[$key]);
                    } else {
                        $selected[$key] = $value;
                        $precedence[] = 'filter:'.$key;
                    }
                    $page = 1;
                } else {
                    $notices[] = 'That filter choice is no longer available.';
                }
            } elseif ($action === 'subgroup') {
                if ($argument === null || (is_int($argument) && $presets->has($argument))) {
                    if ($presetId !== $argument) {
                        $precedence = array_values(array_filter($precedence, fn (string $token): bool => ! str_starts_with($token, 'subgroup:')));
                        $presetId = $argument;
                        if ($presetId !== null) {
                            $precedence[] = 'subgroup:'.$presetId;
                        }
                        $page = 1;
                    }
                } else {
                    $notices[] = 'That preset is no longer available.';
                }
            } elseif (in_array($action, ['clear', 'reset'], true)) {
                $selected = [];
                $presetId = $action === 'reset' ? null : $presetId;
                $precedence = $presetId ? ['subgroup:'.$presetId] : [];
                $page = 1;
            } elseif ($action === 'page') {
                $page = $this->positiveInteger($argument) ?? 1;
            } elseif ($action === 'size') {
                if ($settings['allow_page_size_change'] && in_array($argument, $settings['page_size_options'], true)) {
                    $perPage = $argument;
                    $page = 1;
                } else {
                    $notices[] = 'That page size is not available.';
                }
            }
            $preset = $presets->get($presetId);
            if ($preset && ($preset->force_hide || count($preset->allowed_values) === 1) && array_key_exists($preset->property_key, $selected)) {
                unset($selected[$preset->property_key]);
                $precedence = array_values(array_diff($precedence, ['filter:'.$preset->property_key]));
                $notices[] = 'The preset now controls '.$filters->get($preset->property_key)?->label.'.';
                $page = 1;
            }
            $constraints = [];
            foreach ($selected as $key => $value) {
                $constraints['filter:'.$key] = ['property' => $key, 'values' => [$value]];
            }
            if ($preset) {
                $constraints['subgroup:'.$preset->id] = ['property' => $preset->property_key, 'values' => $preset->allowed_values];
            }
            $replayed = $this->reconciler->reconcile($constraints, $precedence, fn (array $accepted): bool => $this->predicate($groupId, $accepted)->exists());
            foreach ($replayed['removed'] as $token) {
                if (str_starts_with($token, 'filter:')) {
                    $key = substr($token, 7);
                    unset($selected[$key]);
                    $notices[] = 'Cleared '.$filters[$key]->label.' to keep your newer choice.';
                } else {
                    $notices[] = 'Cleared preset '.$preset->label.' to keep your newer choice.';
                    $presetId = null;
                    $preset = null;
                }
                unset($constraints[$token]);
                $page = 1;
            }
            if ($adjusted) {
                $page = 1;
                $notices[] = 'Some catalog choices were adjusted to the current group settings.';
            }
            $fields = [];
            foreach ($filters as $key => $filter) {
                if ($vocabulary[$key] === [] || ($preset?->property_key === $key && ($preset->force_hide || count($preset->allowed_values) === 1))) {
                    continue;
                }
                $others = $constraints;
                unset($others['filter:'.$key]);
                $counts = array_column($others === [] ? $baseCounts[$key] : $this->aggregate($this->predicate($groupId, $others), $key), 'count', 'value');
                $order = array_values(array_unique([...array_values(array_filter($filter->value_order ?? [], fn (mixed $value): bool => is_string($value) && in_array($value, $vocabulary[$key], true))), ...$vocabulary[$key]]));
                $values = [];
                foreach ($order as $value) {
                    $values[] = ['value' => $value, 'label' => $filter->value_labels[$value] ?? $value, 'count' => $counts[$value] ?? 0, 'selected' => ($selected[$key] ?? null) === $value];
                }
                $fields[] = ['key' => $key, 'label' => $filter->label, 'values' => $values];
            }
            $query = $this->predicate($groupId, $constraints);
            $total = (clone $query)->count();
            if ($page > max(1, (int) ceil($total / $perPage))) {
                $page = 1;
                $notices[] = 'The requested page is no longer available. Showing page 1.';
                $adjusted = true;
            }
            $products = $query->orderBy('product_code')->orderBy('id')->paginate($perPage, ['id', 'product_code', 'product_name', 'properties->Working_Pressure as pressure', 'properties->Connection_Type as connection'], page: $page, total: $total);
            $state = new CatalogDiscoveryState($selected, $replayed['kept'], $presetId, $page, $perPage);

            return new CatalogDiscoveryResult($state, $fields, $presets->map(fn (SubGroup $item): array => ['id' => $item->id, 'label' => $item->label])->values()->all(), $products, array_values(array_unique($notices)), $settings, $adjusted || ($action === null && $raw !== [] && $raw != $state->toArray()));
        });
    }

    /** @param array<string, array{property: string, values: list<string>}> $constraints */
    public function predicate(int $groupId, array $constraints): Builder
    {
        $query = Product::query()->where('group_id', $groupId);
        foreach ($constraints as $constraint) {
            [$value, $type] = $this->expressions($constraint['property']);
            $query->whereRaw($type)->whereIn(DB::raw($value), $constraint['values']);
        }

        return $query;
    }

    /** @return list<array{value: string, count: int}> */
    public function vocabulary(int $groupId, string $key): array
    {
        return $this->aggregate($this->predicate($groupId, []), $key);
    }

    /** @return list<array{value: string, count: int}> */
    private function aggregate(Builder $query, string $key): array
    {
        [$value, $type] = $this->expressions($key);

        return $query->whereRaw($type)->whereRaw($value.' <> ?', [''])->selectRaw($value.' as facet_value, count(*) as aggregate, min(id) as first_id')
            ->groupByRaw($value)->orderBy('first_id')->toBase()->get()
            ->map(fn (object $row): array => ['value' => $row->facet_value, 'count' => (int) $row->aggregate])->all();
    }

    /** @return array{string, string} */
    private function expressions(string $key): array
    {
        if (! in_array($key, CatalogImportParser::propertyKeys(), true)) {
            throw new \InvalidArgumentException('Unregistered catalog property.');
        }
        $path = '$."'.$key.'"';
        if (DB::getDriverName() === 'mysql') {
            $extract = "JSON_EXTRACT(properties, '".$path."')";

            return ['JSON_UNQUOTE('.$extract.') COLLATE utf8mb4_0900_bin', 'JSON_TYPE('.$extract.") = 'STRING'"];
        }

        return ["json_extract(properties, '".$path."') COLLATE BINARY", "json_type(properties, '".$path."') = 'text'"];
    }

    /** @param list<string> $keys */
    private function diagnoseTypes(int $groupId, array $keys): void
    {
        if ($keys === []) {
            return;
        }
        $expressions = [];
        foreach ($keys as $index => $key) {
            $path = '$."'.$key.'"';
            $type = DB::getDriverName() === 'mysql' ? "JSON_TYPE(JSON_EXTRACT(properties, '".$path."'))" : "json_type(properties, '".$path."')";
            $accepted = DB::getDriverName() === 'mysql' ? "'STRING', 'NULL'" : "'text', 'null'";
            $expressions[] = 'sum(case when '.$type.' not in ('.$accepted.') then 1 else 0 end) as invalid_'.$index;
        }
        $counts = $this->predicate($groupId, [])->selectRaw(implode(', ', $expressions))->toBase()->first();
        $invalid = [];
        foreach ($keys as $index => $key) {
            if (($counts->{'invalid_'.$index} ?? 0) > 0) {
                $invalid[$key] = (int) $counts->{'invalid_'.$index};
            }
        }
        if ($invalid !== []) {
            Log::warning('Catalog facets omitted non-string property values.', ['group_id' => $groupId, 'invalid_counts' => $invalid]);
        }
    }

    private function positiveInteger(mixed $value): ?int
    {
        if (! is_int($value) && (! is_string($value) || ! preg_match('/^[1-9][0-9]{0,8}$/D', $value))) {
            return null;
        }

        return $value > 0 && $value <= 999999999 ? (int) $value : null;
    }
}
