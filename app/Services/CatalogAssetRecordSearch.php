<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CatalogAssetRecordSearch
{
    /** @return array<string, string> */
    public function targets(string $context): array
    {
        return match ($context) {
            'product' => ['code' => __('catalog-images.code'), 'name' => __('catalog-images.name'), 'reference' => __('catalog-images.reference'), 'group' => __('catalog-images.group')],
            'group' => ['name' => __('catalog-images.name'), 'legacy' => __('catalog-images.legacy')],
            default => throw ValidationException::withMessages(['imageSearch' => 'Choose a valid image record type.']),
        };
    }

    /** @return array<string, mixed> */
    public function defaults(string $context): array
    {
        return ['query' => '', 'targets' => [$context === 'product' ? 'code' : 'name'], 'combine' => 'all', 'match' => 'contains',
            'case_sensitive' => false, 'literal_separators' => false, 'positions' => [], 'exclude' => '', 'exact_codes' => '',
            'image_state' => 'any', 'group_ids' => [], 'checked_only' => false];
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function validate(array $input, string $context): array
    {
        $defaults = $this->defaults($context);
        $input = Validator::make(['imageSearch' => $input], [
            'imageSearch' => ['array:'.implode(',', array_keys($defaults))],
            'imageSearch.query' => ['sometimes', 'string', 'max:500'],
            'imageSearch.exclude' => ['sometimes', 'string', 'max:500'],
            'imageSearch.targets' => ['sometimes', 'array', 'min:1', 'max:5'],
            'imageSearch.targets.*' => ['string', 'distinct', Rule::in(array_keys($this->targets($context)))],
            'imageSearch.combine' => ['sometimes', Rule::in(['all', 'any'])],
            'imageSearch.match' => ['sometimes', Rule::in(['contains', 'starts', 'ends', 'whole'])],
            'imageSearch.case_sensitive' => ['sometimes', 'boolean'], 'imageSearch.literal_separators' => ['sometimes', 'boolean'],
            'imageSearch.checked_only' => ['sometimes', 'boolean'],
            'imageSearch.positions' => ['sometimes', 'array', 'max:16'],
            'imageSearch.positions.*' => ['integer', 'min:1', 'max:16', 'distinct'],
            'imageSearch.group_ids' => ['sometimes', 'array', 'max:5000'],
            'imageSearch.group_ids.*' => ['integer', 'min:1', 'distinct'],
            'imageSearch.image_state' => ['sometimes', Rule::in(['any', 'missing', 'has'])],
            'imageSearch.exact_codes' => ['sometimes', 'string'],
        ])->validate()['imageSearch'];
        $recipe = array_replace($defaults, $input);
        foreach (['query', 'exclude', 'exact_codes'] as $key) {
            if (! mb_check_encoding($recipe[$key], 'UTF-8')) {
                throw ValidationException::withMessages(['imageSearch.'.$key => 'Use valid text.']);
            }
        }
        if (strlen($recipe['exact_codes']) > 524288 || ($context !== 'product' && ($recipe['exact_codes'] !== '' || $recipe['positions'] !== [] || $recipe['group_ids'] !== []))) {
            throw ValidationException::withMessages(['imageSearch' => 'These search controls are unavailable or exceed their limit.']);
        }
        $terms = $this->fragments($recipe['query']);
        $excluded = $this->fragments($recipe['exclude']);
        if (count($terms) > 20 || count($excluded) > 20) {
            throw ValidationException::withMessages(['imageSearch' => 'Use at most 20 fragments.']);
        }
        $codes = array_values(array_unique(array_filter(array_map('trim', preg_split('/\R/u', $recipe['exact_codes']) ?: []), fn (string $code): bool => $code !== ''), SORT_STRING));
        if (count($codes) > 5000) {
            throw ValidationException::withMessages(['imageSearch.exact_codes' => 'Use at most 5000 distinct exact codes.']);
        }

        return [...$recipe, 'terms' => $terms, 'excluded' => $excluded, 'codes' => $codes];
    }

    /** @param array<string, mixed> $recipe */
    public function apply(Builder $eligibleQuery, array $recipe, string $context): Builder
    {
        $query = clone $eligibleQuery;
        $table = $query->getModel()->getTable();
        if ($recipe['image_state'] !== 'any') {
            $recipe['image_state'] === 'has' ? $query->whereNotNull($table.'.main_image_id') : $query->whereNull($table.'.main_image_id');
        }
        if ($context === 'product' && $recipe['group_ids'] !== []) {
            $query->whereIn('products.group_id', $recipe['group_ids']);
        }
        if ($recipe['codes'] !== []) {
            $collation = $query->getConnection()->getDriverName() === 'mysql' ? 'utf8mb4_0900_bin' : 'BINARY';
            $query->whereIn(DB::raw('products.product_code COLLATE '.$collation), $recipe['codes']);

            return $query;
        }
        if (trim($recipe['query']) !== '' && $this->normalize($recipe['query'], $recipe) === '') {
            return $query->whereRaw('1 = 0');
        }
        $terms = $recipe['match'] === 'whole' && trim($recipe['query']) !== '' ? [trim($recipe['query'])] : $recipe['terms'];
        if ($terms !== []) {
            $query->where(function (Builder $combined) use ($terms, $recipe, $context): void {
                foreach ($terms as $term) {
                    $combined->where(function (Builder $targets) use ($term, $recipe, $context): void {
                        foreach ($recipe['targets'] as $target) {
                            [$sql, $bindings] = $this->condition($target, $term, $recipe, $context);
                            $targets->orWhereRaw($sql, $bindings);
                        }
                    }, boolean: $recipe['combine'] === 'any' ? 'or' : 'and');
                }
            });
        }
        foreach ($recipe['excluded'] as $term) {
            if ($this->normalize($term, $recipe) === '') {
                continue;
            }
            foreach ($recipe['targets'] as $target) {
                [$sql, $bindings] = $this->condition($target, $term, [...$recipe, 'match' => 'contains'], $context);
                $query->whereRaw('COALESCE(('.$sql.'), 0) = 0', $bindings);
            }
        }

        return $query;
    }

    /** @return list<array{column: string, direction: string}> */
    public function sortDescriptor(string $context, ?string $sort): array
    {
        $this->targets($context);
        $table = $context === 'product' ? 'products' : 'groups';
        $default = $context === 'product' ? 'product_code' : 'sort_order';
        [$column, $direction] = explode(':', $sort ?? $default.':asc', 2) + [null, null];
        $allowed = $context === 'product' ? ['id', 'product_code', 'product_name', 'legacy_id'] : ['id', 'name', 'legacy_id', 'sort_order'];
        if (! in_array($column, $allowed, true) || ! in_array($direction, ['asc', 'desc'], true)) {
            throw ValidationException::withMessages(['tableSort' => 'Choose an available sort.']);
        }

        return [['column' => $table.'.'.$column, 'direction' => $direction], ...($column === 'id' ? [] : [['column' => $table.'.id', 'direction' => 'asc']])];
    }

    /** @return list<string> */
    private function fragments(string $value): array
    {
        return preg_split('/\s+/u', trim($value), flags: PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /** @param array<string, mixed> $recipe */
    private function normalize(string $value, array $recipe): string
    {
        $value = $recipe['literal_separators'] ? $value : str_replace(['-', '_', ' '], '', $value);

        return $recipe['case_sensitive'] ? $value : mb_strtolower($value);
    }

    /** @param array<string, mixed> $recipe
     * @return array{0: string, 1: list<mixed>}
     */
    private function condition(string $target, string $term, array $recipe, string $context): array
    {
        $mysql = DB::getDriverName() === 'mysql';
        $bindings = [];
        $expression = match ($context.':'.$target) {
            'product:code' => 'products.product_code', 'product:name' => 'products.product_name',
            'product:group' => '(SELECT groups.name FROM groups WHERE groups.id = products.group_id)',
            'group:name' => 'groups.name', 'group:legacy' => 'groups.legacy_id',
            'product:reference' => '',
            default => throw ValidationException::withMessages(['imageSearch.targets' => 'Choose available search fields.']),
        };
        if ($target === 'reference') {
            $field = ProductFieldQuery::expressions('product_pic');
            $expression = '(CASE WHEN '.$field['type'].' = ? THEN '.$field['value'].' ELSE NULL END)';
            $bindings = [$field['binding'], $field['stringType'], $field['binding']];
        }
        if ($target === 'code' && $recipe['positions'] !== []) {
            $parts = [];
            foreach ($recipe['positions'] as $position) {
                $position = (int) $position;
                $parts[] = $mysql
                    ? "(CASE WHEN LENGTH(products.product_code) - LENGTH(REPLACE(products.product_code, '-', '')) + 1 >= {$position} THEN SUBSTRING_INDEX(SUBSTRING_INDEX(products.product_code, '-', {$position}), '-', -1) ELSE NULL END)"
                    : "json_extract('[' || REPLACE(json_quote(products.product_code), '-', '\",\"') || ']', '$[".($position - 1)."]')";
            }
            $expression = $mysql ? 'CONCAT('.implode(", '-', ", $parts).')' : '('.implode(" || '-' || ", $parts).')';
        }
        if (! $recipe['literal_separators']) {
            $expression = "REPLACE(REPLACE(REPLACE({$expression}, '-', ''), '_', ''), ' ', '')";
        }
        if (! $recipe['case_sensitive']) {
            $expression = 'LOWER('.$expression.')';
        }
        $expression .= $mysql ? ' COLLATE utf8mb4_0900_bin' : ' COLLATE BINARY';
        $value = $this->normalize($term, $recipe);
        $length = mb_strlen($value);
        $sql = match ($recipe['match']) {
            'whole' => $expression.' = ?',
            'starts' => 'SUBSTR('.$expression.', 1, '.$length.') = ?',
            'ends' => 'SUBSTR('.$expression.', -'.$length.') = ?',
            default => $mysql ? 'LOCATE(?, '.$expression.') > 0' : 'INSTR('.$expression.', ?) > 0',
        };
        $bindings = $mysql && $recipe['match'] === 'contains' ? [$value, ...$bindings] : [...$bindings, $value];

        return [$sql, $bindings];
    }
}
