<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CatalogAssetRecipe
{
    public function defaults(): array
    {
        return ['source_field' => 'profile_fields', 'field_key' => 'product_pic', 'extraction' => 'basename_stem', 'positions' => [], 'separator' => '', 'prefix' => '', 'suffix' => '', 'case_sensitive' => false, 'ignore_separators' => false, 'comparison' => 'exact', 'include_patterns' => '', 'exclude_patterns' => '', 'include_subfolders' => false];
    }

    public function validate(array $input, string $operation, string $entity): array
    {
        Validator::make(compact('operation', 'entity'), ['operation' => [Rule::in(['one_image', 'folder_matches'])], 'entity' => [Rule::in(['product', 'group'])]])->validate();
        if ($operation === 'folder_matches' && $entity !== 'product') {
            throw ValidationException::withMessages(['recipe' => 'Folder matching is available for Products only.']);
        }
        if ($operation === 'one_image') {
            Validator::make(['recipe' => $input], ['recipe' => 'array:'])->validate();

            return [];
        }
        $defaults = $this->defaults();
        $validated = Validator::make(['recipe' => $input], [
            'recipe' => ['array:'.implode(',', array_keys($defaults))],
            'recipe.source_field' => ['sometimes', Rule::in(['profile_fields', 'product_code'])],
            'recipe.field_key' => ['sometimes', ProductFields::referenceRule()],
            'recipe.extraction' => ['sometimes', Rule::in(['basename_stem'])],
            'recipe.positions' => ['sometimes', 'array', 'max:16'],
            'recipe.positions.*' => ['integer', 'min:1', 'max:16', 'distinct'],
            'recipe.separator' => ['sometimes', 'string', 'max:10'],
            'recipe.prefix' => ['sometimes', 'string', 'max:100'], 'recipe.suffix' => ['sometimes', 'string', 'max:100'],
            'recipe.comparison' => ['sometimes', Rule::in(['exact', 'contains'])],
            'recipe.case_sensitive' => ['sometimes', 'boolean'], 'recipe.ignore_separators' => ['sometimes', 'boolean'], 'recipe.include_subfolders' => ['sometimes', 'boolean'],
            'recipe.include_patterns' => ['sometimes', 'string', 'max:500'], 'recipe.exclude_patterns' => ['sometimes', 'string', 'max:500'],
        ])->validate()['recipe'];
        $recipe = array_replace($defaults, $validated);
        foreach ($recipe as $key => $value) {
            if (is_string($value) && (! mb_check_encoding($value, 'UTF-8') || str_contains($value, "\0"))) {
                throw ValidationException::withMessages(['recipe.'.$key => 'Use valid literal text.']);
            }
        }
        if ($recipe['positions'] !== [] && $recipe['source_field'] !== 'product_code') {
            throw ValidationException::withMessages(['recipe.positions' => 'Code positions require Product code.']);
        }
        $recipe['positions'] = array_map('intval', $recipe['positions']);

        return $recipe;
    }

    public function identifier(array $record, array $recipe): array
    {
        $exists = $recipe['source_field'] === 'product_code'
            ? array_key_exists('product_code', $record) : array_key_exists($recipe['field_key'], $record['profile_fields'] ?? []);
        $raw = $recipe['source_field'] === 'product_code' ? ($record['product_code'] ?? null) : ($record['profile_fields'][$recipe['field_key']] ?? null);
        $result = ['raw_value' => $raw, 'raw_display' => is_string($raw) ? mb_substr($raw, 0, 1024) : null, 'raw_truncated' => is_string($raw) && mb_strlen($raw) > 1024, 'raw_sha256' => hash('sha256', serialize($raw)), 'identifier' => null, 'reason' => null];
        if (! $exists) {
            return [...$result, 'reason' => 'reference_missing'];
        }
        if (! is_string($raw)) {
            return [...$result, 'reason' => 'non_text_reference'];
        }
        if (! mb_check_encoding($raw, 'UTF-8') || str_contains($raw, "\0")) {
            return [...$result, 'reason' => 'invalid_reference'];
        }
        if (trim($raw) === '') {
            return [...$result, 'reason' => 'identifier_empty'];
        }
        if ($recipe['source_field'] === 'product_code' && $recipe['positions'] !== []) {
            $parts = explode('-', $raw);
            $selected = [];
            foreach ($recipe['positions'] as $position) {
                if (! isset($parts[$position - 1]) || $parts[$position - 1] === '') {
                    return [...$result, 'reason' => 'missing_segment'];
                }
                $selected[] = $parts[$position - 1];
            }
            $identifier = implode($recipe['separator'], $selected);
        } else {
            $parts = explode('/', str_replace('\\', '/', $raw));
            $identifier = $this->stem(end($parts));
        }
        if ($identifier === '') {
            return [...$result, 'reason' => 'identifier_empty'];
        }

        return [...$result, 'identifier' => $recipe['prefix'].$identifier.$recipe['suffix']];
    }

    public function stem(string $filename): string
    {
        return preg_replace('/\.(jpe?g|png|webp)$/iu', '', $filename);
    }

    public function normalize(string $value, array $recipe): string
    {
        if ($recipe['ignore_separators']) {
            $value = str_replace([' ', '-', '_'], '', $value);
        }

        return $recipe['case_sensitive'] ? $value : mb_strtolower($value);
    }
}
