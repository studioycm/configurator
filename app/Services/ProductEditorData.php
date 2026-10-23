<?php

namespace App\Services;

use App\Models\GroupFilter;
use App\Models\Product;
use Illuminate\Validation\ValidationException;
use JsonException;

class ProductEditorData
{
    /** @return list<array{key: string, label: string, id: int, sort_order: int}> */
    public static function groupFields(Product $product): array
    {
        $product->loadMissing('group.filters');

        return $product->group->filters->sortBy([['sort_order', 'asc'], ['id', 'asc']])
            ->map(fn (GroupFilter $filter): array => ['key' => $filter->property_key, 'label' => $filter->label, 'id' => $filter->id, 'sort_order' => $filter->sort_order])
            ->values()->all();
    }

    /** @return list<array{key: string, label: string}> */
    public static function editorFields(Product $product): array
    {
        $filters = self::groupFields($product);
        $keys = CatalogPolicy::resultSettings($product->group->result_settings)['editor_properties'];
        if ($keys === []) {
            return array_map(fn (array $field): array => ['key' => $field['key'], 'label' => $field['label']], $filters);
        }
        $labels = array_column($filters, 'label', 'key');

        return array_map(fn (string $key): array => ['key' => $key, 'label' => $labels[$key] ?? str_replace('_', ' ', $key)], $keys);
    }

    public static function groupFingerprint(Product $product): string
    {
        return hash('sha256', json_encode([$product->group_id, self::editorFields($product)], JSON_THROW_ON_ERROR));
    }

    /** @return array{properties: list<array{key: string, value: string}>, others: list<array{key: string, value: string, original_key: string}>, parts: list<array{key: string, value: string}>} */
    public static function formData(Product $product): array
    {
        $profile = self::rawMap($product, 'profile_fields');
        $properties = [];
        $selected = [];
        foreach (self::editorFields($product) as $field) {
            $properties[] = ['key' => $field['key'], 'value' => self::displayValue($profile[$field['key']] ?? null)];
            $selected[$field['key']] = true;
        }
        $others = [];
        foreach ($profile as $key => $value) {
            if (! array_key_exists($key, $selected)) {
                $others[] = ['key' => (string) $key, 'value' => self::displayValue($value), 'original_key' => (string) $key];
            }
        }

        return ['properties' => $properties, 'others' => $others, 'parts' => self::rows(self::rawMap($product, 'parts'))];
    }

    /** @return array<int|string, mixed> */
    public static function rawMap(Product $product, string $field): array
    {
        $json = $product->getRawOriginal($field) ?? '{}';
        try {
            $decoded = json_decode($json, flags: JSON_THROW_ON_ERROR);
            $encoded = json_encode($decoded, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        } catch (JsonException) {
            throw ValidationException::withMessages(['product.'.$field => __('catalog.products.validation.numeric_json_cannot_be_preserved')]);
        }
        $pattern = '~"(?:[^"\\\\]|\\\\.)*"(*SKIP)(*F)|-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?~s';
        if (preg_match_all($pattern, $json, $originalNumbers) === false
            || preg_match_all($pattern, $encoded, $encodedNumbers) === false
            || $originalNumbers[0] !== $encodedNumbers[0]) {
            throw ValidationException::withMessages(['product.'.$field => __('catalog.products.validation.numeric_json_cannot_be_preserved')]);
        }

        return (array) $decoded;
    }

    public static function propertyLabel(Product $product, string $key): string
    {
        foreach (self::editorFields($product) as $field) {
            if ($field['key'] === $key) {
                return $field['label'] !== '' ? $field['label'] : $key;
            }
        }

        return $key;
    }

    /**
     * @param  list<array{key: string, value: ?string}>  $properties
     * @param  list<array{key: string, value: ?string, original_key?: ?string}>  $others
     * @return array<int|string, mixed>
     */
    public static function applyProfile(Product $product, array $properties, array $others): array
    {
        $original = self::rawMap($product, 'profile_fields');
        $selected = array_fill_keys(array_column(self::editorFields($product), 'key'), true);
        $values = [];
        $seen = [];
        foreach ($properties as $row) {
            $key = $row['key'];
            if (! array_key_exists($key, $selected) || array_key_exists($key, $seen)) {
                throw ValidationException::withMessages(['product.properties' => __('catalog.products.validation.keep_the_selected_group_fields')]);
            }
            $seen[$key] = true;
            $input = $row['value'] ?? '';
            if (array_key_exists($key, $original)) {
                $values[$key] = self::applyValue($original[$key], $input, 'product.properties');
            } elseif ($input !== '') {
                $values[$key] = $input;
            }
        }
        if (count($seen) !== count($selected)) {
            throw ValidationException::withMessages(['product.properties' => __('catalog.products.validation.keep_the_selected_group_fields')]);
        }
        $origins = [];
        foreach ($others as $row) {
            $key = $row['key'];
            if (! ProductFields::validKey($key) || array_key_exists($key, $seen)) {
                throw ValidationException::withMessages(['product.others' => __('catalog.products.validation.profile_field_keys_must_be_valid_and_unique')]);
            }
            $seen[$key] = true;
            $origin = $row['original_key'] ?? (array_key_exists($key, $original) ? $key : null);
            if ($origin !== null && (! array_key_exists($origin, $original) || array_key_exists($origin, $selected) || array_key_exists($origin, $origins))) {
                throw ValidationException::withMessages(['product.others' => __('catalog.products.validation.profile_field_keys_must_be_valid_and_unique')]);
            }
            if ($origin !== null) {
                $origins[$origin] = true;
            }
            $input = $row['value'] ?? '';
            $values[$key] = $origin === null ? $input : self::applyValue($original[$origin], $input, 'product.others');
        }
        $updated = array_intersect_key($original, $values);
        foreach ($values as $key => $value) {
            $updated[$key] = $value;
        }

        return $updated;
    }

    /** @param array<int|string, mixed> $values
     * @return list<array{key: string, value: string}>
     */
    public static function rows(array $values): array
    {
        $rows = [];
        foreach ($values as $key => $value) {
            $rows[] = ['key' => (string) $key, 'value' => self::displayValue($value)];
        }

        return $rows;
    }

    public static function displayValue(mixed $value): string
    {
        return is_string($value) || $value === null ? ($value ?? '') : json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }

    /** @param array<int|string, mixed> $original
     * @param  list<array{key: string, value: ?string}>  $rows
     * @return array<int|string, mixed>
     */
    public static function applyRows(array $original, array $rows, string $field): array
    {
        $submitted = [];
        foreach ($rows as $row) {
            if (! array_key_exists($row['key'], $original) || array_key_exists($row['key'], $submitted)) {
                throw ValidationException::withMessages([$field => __('catalog.products.validation.keep_the_existing_json_keys_fields_cannot')]);
            }
            $submitted[$row['key']] = $row['value'] ?? '';
        }
        if (count($submitted) !== count($original)) {
            throw ValidationException::withMessages([$field => __('catalog.products.validation.keep_every_existing_json_field_including_blank')]);
        }
        $updated = $original;
        foreach ($original as $key => $value) {
            $input = $submitted[$key];
            $updated[$key] = self::applyValue($value, $input, $field);
        }

        return $updated;
    }

    private static function applyValue(mixed $original, string $input, string $field): mixed
    {
        if ($input === self::displayValue($original)) {
            return $original;
        }
        if (! is_string($original) && $original !== null) {
            throw ValidationException::withMessages([$field => __('catalog.products.validation.structured_and_non_text_source_values_are_read_only')]);
        }

        return $input;
    }
}
