<?php

namespace App\Services;

use App\Models\CatalogPresentationSettings;
use App\Models\Group;
use App\Models\GroupTagCategory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use LogicException;

class CatalogPresentation
{
    /** @var array{version:string,settings:array<string,mixed>,recovery:?array<string,mixed>}|null */
    private ?array $resolved = null;

    private ?string $resolvedKey = null;

    private const array OVERRIDES = [
        'group_image_placement', 'group_image_scale', 'group_card_content',
        'group_card_tag_categories', 'product_image_placement', 'product_image_scale',
    ];

    /** @return array<string, bool|int|string|list<int>|list<string>> */
    public function defaults(): array
    {
        return [
            'sidebar_mode' => 'tree', 'group_image_placement' => 'left', 'group_image_scale' => 'medium',
            'group_card_density' => 'compact', 'group_cards_per_row' => 3,
            'group_card_content' => ['description', 'tags'], 'group_card_tag_categories' => [],
            'tag_presentation' => 'cards', 'tag_columns' => 2, 'tag_density' => 'compact', 'tag_counts' => true,
            'branch_tag_retention' => 'keep_available', 'criteria_container' => 'responsive',
            'criteria_columns' => 'divided', 'criteria_choices' => 'checkbox',
            'product_image_placement' => 'left', 'product_image_scale' => 'medium',
            'product_page_layout' => 'compact_header', 'product_page_image_scale' => 'medium',
        ];
    }

    /** @param array<string,mixed> $settings @return array<string,mixed> */
    public function validate(array $settings): array
    {
        $validated = Validator::make(['settings' => $settings], [
            'settings' => ['required', 'array:'.implode(',', array_keys($this->defaults()))],
            ...$this->fieldRules(),
        ])->validate()['settings'];

        return array_replace($this->defaults(), $validated);
    }

    /** @return list<string> */
    public function overrideKeys(): array
    {
        return self::OVERRIDES;
    }

    /** @param array<string,mixed> $overrides @return array<string,mixed> */
    public function validateOverrides(array $overrides): array
    {
        Validator::make(['settings' => $overrides], ['settings' => ['array:'.implode(',', self::OVERRIDES)]])->validate();
        $validated = $this->validate(array_replace($this->defaults(), $overrides));

        return array_intersect_key($validated, $overrides);
    }

    /** @return array{version:string,settings:array<string,mixed>,recovery:?array<string,mixed>} */
    public function current(bool $fresh = false): array
    {
        $record = CatalogPresentationSettings::query()->find(1, ['version', 'settings']);
        if ($record === null) {
            return ['version' => 'defaults:v1', 'settings' => $this->defaults(), 'recovery' => null];
        }

        $stored = $record->settings;
        $key = $this->cacheKey($record->version, $stored);
        $read = fn (): array => ['version' => $record->version, ...$this->normalizeStored($stored)];
        if (DB::transactionLevel() > 0) {
            $normalized = $read();
        } elseif (! $fresh && $this->resolved !== null && $this->resolvedKey === $key) {
            $normalized = $this->resolved;
        } else {
            $normalized = $fresh ? $read() : Cache::remember($key, 3600, $read);
            $this->resolved = $normalized;
            $this->resolvedKey = $key;
        }

        return ['version' => $record->version, ...$this->recoverReferences(
            ['settings' => $normalized['settings'], 'recovery' => $normalized['recovery']],
            $this->resolveCategoryIds($normalized['settings']),
        )];
    }

    /**
     * Resolve once for a bounded server-owned batch inside its coherent read boundary.
     *
     * @param  array<string,mixed>  $shared
     * @param  iterable<Group>  $groups
     * @return list<int>
     */
    public function resolveCategoryIds(array $shared, iterable $groups = []): array
    {
        $ids = $this->normalizeStored($shared)['settings']['group_card_tag_categories'];
        foreach ($groups as $group) {
            $raw = $group->result_settings;
            $own = is_array($raw) ? ($raw['catalog_presentation'] ?? []) : [];
            $ids = [...$ids, ...$this->normalizeStored($own)['settings']['group_card_tag_categories']];
        }
        $ids = array_values(array_unique($ids));

        return $ids === [] ? [] : GroupTagCategory::query()->whereKey($ids)->orderBy('id')->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)->all();
    }

    /**
     * Reference context must come from the server batch resolver, never hydrated client state.
     *
     * @param  array<string,mixed>  $shared
     * @param  list<int>|null  $availableCategoryIds
     * @return array<string,mixed>
     */
    public function effective(Group $group, array $shared, ?array $availableCategoryIds = null): array
    {
        if ($availableCategoryIds !== null && (! array_is_list($availableCategoryIds)
            || array_filter($availableCategoryIds, fn (mixed $id): bool => ! is_int($id) || $id < 1) !== [])) {
            throw new InvalidArgumentException('Use server-resolved category IDs.');
        }
        $normalizedShared = $this->normalizeStored($shared);
        $raw = $group->result_settings;
        $overrides = is_array($raw) ? ($raw['catalog_presentation'] ?? null) : null;
        $overrides = is_array($overrides) ? array_intersect_key($overrides, array_flip(self::OVERRIDES)) : [];
        if ($availableCategoryIds === null && ($normalizedShared['settings']['group_card_tag_categories'] !== []
            || $this->normalizeStored($overrides)['settings']['group_card_tag_categories'] !== [])) {
            throw new LogicException('Resolve category IDs before projecting nonempty category selections.');
        }
        $shared = $this->recoverReferences($normalizedShared, $availableCategoryIds ?? [])['settings'];
        $candidate = array_replace($shared, $overrides);
        $recovered = $this->recoverReferences($this->normalizeStored($candidate), $availableCategoryIds ?? []);
        foreach ($recovered['recovery']['defaulted_fields'] ?? [] as $key) {
            $recovered['settings'][$key] = $shared[$key];
        }

        return $recovered['settings'];
    }

    public function groupFingerprint(Group $group): string
    {
        return hash('sha256', json_encode([
            'group_id' => (int) $group->id, 'revision' => $group->catalog_revision,
            'result_settings' => $group->getAttributes()['result_settings'] ?? null,
        ], JSON_THROW_ON_ERROR));
    }

    /** @param array<string,mixed> $settings */
    public function rememberSaved(array $settings, string $version): void
    {
        DB::afterCommit(function () use ($settings, $version): void {
            $this->resolved = null;
            $this->resolvedKey = null;
            Cache::put($this->cacheKey($version, $settings), ['version' => $version, 'settings' => $settings, 'recovery' => null], 3600);
        });
    }

    private function cacheKey(string $version, mixed $stored): string
    {
        if (is_array($stored)) {
            ksort($stored);
        }

        return 'catalog-presentation:payload:v1:'.$version.':'.hash('sha256', serialize($stored));
    }

    /** @return array<string,list<mixed>> */
    private function fieldRules(): array
    {
        $enums = [
            'sidebar_mode' => ['tree', 'tags'], 'group_image_placement' => ['above', 'below', 'left', 'right'],
            'group_image_scale' => ['small', 'medium', 'large'], 'group_card_density' => ['compact', 'comfortable'],
            'tag_presentation' => ['cards', 'chip_grid', 'chips', 'rows'], 'tag_density' => ['compact', 'comfortable'],
            'branch_tag_retention' => ['keep_available', 'clear'], 'criteria_container' => ['responsive', 'drawer'],
            'criteria_columns' => ['plain', 'divided'], 'criteria_choices' => ['checkbox', 'buttons'],
            'product_image_placement' => ['above', 'below', 'left', 'right'], 'product_image_scale' => ['small', 'medium', 'large'],
            'product_page_layout' => ['compact_header', 'image_column'], 'product_page_image_scale' => ['small', 'medium', 'large'],
        ];
        $rules = [];
        foreach ($enums as $key => $values) {
            $rules['settings.'.$key] = ['required', 'string', Rule::in($values)];
        }

        return [
            ...$rules,
            'settings.group_cards_per_row' => ['required', 'integer:strict', Rule::in([2, 3, 4])],
            'settings.tag_columns' => ['required', 'integer:strict', Rule::in([1, 2, 3])],
            'settings.tag_counts' => ['present', 'boolean:strict'],
            'settings.group_card_content' => ['present', 'array', 'list', 'max:4'],
            'settings.group_card_content.*' => ['required', 'string', 'distinct:strict', Rule::in(['description', 'tags', 'product_count', 'legacy_id'])],
            'settings.group_card_tag_categories' => ['present', 'array', 'list'],
            'settings.group_card_tag_categories.*' => ['required', 'integer:strict', 'min:1', 'distinct:strict'],
        ];
    }

    /** @return array{settings:array<string,mixed>,recovery:?array{defaulted_fields:list<string>,ignored_unknown_count:int,invalid_payload:bool}} */
    private function normalizeStored(mixed $stored): array
    {
        $defaults = $this->defaults();
        $report = ['defaulted_fields' => [], 'ignored_unknown_count' => 0, 'invalid_payload' => ! is_array($stored)];
        $known = is_array($stored) ? array_intersect_key($stored, $defaults) : [];
        $report['ignored_unknown_count'] = is_array($stored) ? count(array_diff_key($stored, $defaults)) : 0;
        $validator = Validator::make(['settings' => $known], $this->fieldRules());
        $validator->fails();
        $candidate = array_replace($defaults, $known);
        foreach (array_keys($validator->failed()) as $path) {
            $key = explode('.', $path)[1];
            $candidate[$key] = $defaults[$key];
            $report['defaulted_fields'][] = $key;
        }
        $report['defaulted_fields'] = array_values(array_unique($report['defaulted_fields']));
        $recovery = $report['defaulted_fields'] !== [] || $report['ignored_unknown_count'] > 0 || $report['invalid_payload'] ? $report : null;

        return ['settings' => $candidate, 'recovery' => $recovery];
    }

    /**
     * @param  array{settings:array<string,mixed>,recovery:?array{defaulted_fields:list<string>,ignored_unknown_count:int,invalid_payload:bool}}  $normalized
     * @param  list<int>  $availableCategoryIds
     * @return array{settings:array<string,mixed>,recovery:?array{defaulted_fields:list<string>,ignored_unknown_count:int,invalid_payload:bool}}
     */
    private function recoverReferences(array $normalized, array $availableCategoryIds): array
    {
        foreach ($normalized['settings']['group_card_tag_categories'] as $id) {
            if (! in_array($id, $availableCategoryIds, true)) {
                $normalized['settings']['group_card_tag_categories'] = [];
                $normalized['recovery'] ??= ['defaulted_fields' => [], 'ignored_unknown_count' => 0, 'invalid_payload' => false];
                $normalized['recovery']['defaulted_fields'][] = 'group_card_tag_categories';
                break;
            }
        }

        return $normalized;
    }
}
