<?php

namespace App\Services;

use App\Models\Product;

class CatalogCardDisplay
{
    /**
     * @param  iterable<Product>  $products
     * @param  list<string>  $propertyKeys
     * @param  array<string, string>  $propertyLabels
     * @return array{fields: list<array{key: string, label: string}>, noticeReason: 'single_match'|'shared_properties'|'no_populated_properties'|null}
     */
    public function compare(iterable $products, array $propertyKeys, array $propertyLabels, bool $onlyDifferences): array
    {
        $first = [];
        $populated = [];
        $different = [];
        $rows = 0;
        foreach ($products as $product) {
            $rows++;
            $properties = $product->properties ?? [];
            foreach ($propertyKeys as $key) {
                $raw = $properties[$key] ?? null;
                $cell = is_string($raw) && $raw !== '' ? $raw : null;
                $populated[$key] = ($populated[$key] ?? false) || $cell !== null;
                if (! array_key_exists($key, $first)) {
                    $first[$key] = $cell;
                } elseif ($first[$key] !== $cell) {
                    $different[$key] = true;
                }
            }
        }
        $fields = [];
        foreach ($propertyKeys as $key) {
            if (($populated[$key] ?? false) && (! $onlyDifferences || ($different[$key] ?? false))) {
                $fields[] = ['key' => $key, 'label' => $propertyLabels[$key] ?? str_replace('_', ' ', $key)];
            }
        }
        $reason = null;
        if ($rows > 0 && $fields === []) {
            $reason = ! in_array(true, $populated, true) ? 'no_populated_properties' : ($rows === 1 ? 'single_match' : 'shared_properties');
        }

        return ['fields' => $fields, 'noticeReason' => $reason];
    }
}
