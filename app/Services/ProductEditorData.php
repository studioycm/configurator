<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class ProductEditorData
{
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
        return is_string($value) || $value === null ? ($value ?? '') : json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
                throw ValidationException::withMessages([$field => 'Keep the existing JSON keys. Fields cannot be added, removed or renamed here.']);
            }
            $submitted[$row['key']] = $row['value'] ?? '';
        }
        if (count($submitted) !== count($original)) {
            throw ValidationException::withMessages([$field => 'Keep every existing JSON field, including blank values.']);
        }
        $updated = $original;
        foreach ($original as $key => $value) {
            $input = $submitted[$key];
            if ($input === self::displayValue($value)) {
                continue;
            }
            if (! is_string($value) && $value !== null) {
                throw ValidationException::withMessages([$field => 'Structured and non-text source values are read-only until their editing semantics are defined.']);
            }
            $updated[$key] = $input;
        }

        return $updated;
    }
}
