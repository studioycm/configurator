<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Normalize an array of integer IDs, ensuring unique, sorted integer values.
 */
class NormalizedIntArrayCast implements CastsAttributes
{
    /**
     * @param  Model  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array<string, mixed>  $attributes
     * @return array<int, int>
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [];
        }

        $decoded = is_string($value) ? json_decode($value, true) : $value;

        if (! is_array($decoded)) {
            return [];
        }

        $normalized = array_map(static fn ($item) => (int) $item, Arr::wrap($decoded));
        $normalized = array_values(array_unique($normalized));

        sort($normalized);

        return $normalized;
    }

    /**
     * @param  Model  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array<string, mixed>  $attributes
     * @return array<int, int>|null
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = array_map(static fn ($item) => (int) $item, Arr::wrap($value));
        $normalized = array_values(array_unique($normalized));

        sort($normalized);

        return json_encode($normalized);
    }
}
