<?php

namespace App\Services;

use App\DTO\ConfigOptionDTO;
use App\DTO\ConfigStageDTO;
use App\Models\ConfigAttribute;
use App\Models\ConfigOption;
use App\Models\ConfigProfile;
use App\Models\OptionRule;
use Illuminate\Support\Collection;

final class ConfiguratorEngine
{
    /**
     * @return ConfigStageDTO[]
     */
    public function buildStages(ConfigProfile $profile): array
    {
        $attributes = $profile->attributes()
            ->with(['options' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        return $attributes
            ->map(fn (ConfigAttribute $attr) => new ConfigStageDTO(
                id: $attr->id,
                slug: $attr->slug,
                label: $attr->label ?? $attr->name,
                sortOrder: (int) $attr->sort_order,
                segmentIndex: $attr->segment_index,
                isRequired: (bool) $attr->is_required,
                options: $attr->options
                    ->sortBy('sort_order')
                    ->map(fn (ConfigOption $opt) => new ConfigOptionDTO(
                        id: $opt->id,
                        label: $opt->label,
                        code: $opt->code,
                        sortOrder: (int) $opt->sort_order,
                        isDefault: (bool) $opt->is_default,
                        isActive: (bool) $opt->is_active,
                    ))
                    ->values()
                    ->all(),
            ))
            ->values()
            ->all();
    }

    /**
     * Build a JSON-friendly manifest that can be reused by a client-side engine.
     *
     * @return array<string, mixed>
     */
    public function buildManifest(ConfigProfile $profile): array
    {
        $attributes = $profile->attributes()
            ->with(['options' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        $rules = $profile->rules()->get([
            'id',
            'config_option_id',
            'target_attribute_id',
            'allowed_option_ids',
            'rule_payload',
            'is_active',
            'priority',
        ]);

        return [
            'profile_id' => (int) $profile->id,
            'stages' => $attributes
                ->map(fn (ConfigAttribute $attr) => [
                    'id' => (int) $attr->id,
                    'slug' => $attr->slug,
                    'name' => $attr->name,
                    'label' => (string) ($attr->label ?? $attr->name),
                    'input_type' => $attr->input_type?->value,
                    'sort_order' => (int) $attr->sort_order,
                    'segment_index' => $attr->segment_index,
                    'is_required' => (bool) $attr->is_required,
                    'options' => $attr->options
                        ->sortBy('sort_order')
                        ->map(fn (ConfigOption $opt) => [
                            'id' => (int) $opt->id,
                            'label' => (string) $opt->label,
                            'code' => $opt->code,
                            'sort_order' => (int) $opt->sort_order,
                            'is_default' => (bool) $opt->is_default,
                            'is_active' => (bool) $opt->is_active,
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
            'rules' => $rules
                ->map(fn (OptionRule $rule) => [
                    'id' => (int) $rule->id,
                    'type' => $rule->effectType(),
                    'trigger_option_id' => (int) $rule->config_option_id,
                    'target_attribute_id' => (int) $rule->target_attribute_id,
                    'allowed_option_ids' => $rule->allowed_option_ids ?? [],
                    'rule_payload' => $rule->rule_payload ?? [],
                    'is_active' => (bool) $rule->is_active,
                    'priority' => (int) $rule->priority,
                ])
                ->values()
                ->all(),
        ];
    }

    public function defaultSelection(array $stages): array
    {
        $selection = [];

        foreach ($stages as $stage) {
            $default = collect($stage->options)
                ->first(fn (ConfigOptionDTO $o) => $o->isDefault && $o->isActive);

            if ($default instanceof ConfigOptionDTO) {
                $selection[$stage->id] = $default->id;

                continue;
            }

            $firstActive = collect($stage->options)
                ->first(fn (ConfigOptionDTO $o) => $o->isActive);

            if ($firstActive instanceof ConfigOptionDTO) {
                $selection[$stage->id] = $firstActive->id;
            }
        }

        return $selection;
    }

    public function baseAllowed(array $stages): array
    {
        $allowed = [];

        foreach ($stages as $stage) {
            $allowed[$stage->id] = collect($stage->options)
                ->filter(fn (ConfigOptionDTO $o) => $o->isActive)
                ->map(fn (ConfigOptionDTO $o) => $o->id)
                ->values()
                ->all();
        }

        return $allowed;
    }

    /**
     * @param  array<int, int>  $selection
     * @param  array<string, mixed>  $context
     * @return array{
     *     allowed: array<int, int[]>,
     *     hidden: array<int, int[]>,
     *     disabled: array<int, int[]>,
     *     label_overrides: array<string, string>,
     *     hints: array<string, string>
     * }
     */
    public function evaluateState(ConfigProfile $profile, array $stages, array $selection, array $context = []): array
    {
        $allowed = $this->baseAllowed($stages);
        $presentationState = $this->basePresentationState($profile);
        $targetOptionIds = $this->targetOptionIdsByAttribute($profile);

        foreach ($this->activeRulesForEvaluation($profile, $selection, $stages, $context) as $rule) {
            $targetAttrId = (int) $rule->target_attribute_id;
            $allowedIds = $rule->allowed_option_ids ?? [];

            if ($allowedIds !== [] && isset($allowed[$targetAttrId])) {
                $allowed[$targetAttrId] = array_values(array_intersect($allowed[$targetAttrId], $allowedIds));

                $disallowedIds = array_values(array_diff($targetOptionIds[$targetAttrId] ?? [], $allowedIds));

                if ($disallowedIds !== []) {
                    if ($rule->uiMode() === 'hidden') {
                        $presentationState['hidden'][$targetAttrId] = $this->mergeOptionIds(
                            $presentationState['hidden'][$targetAttrId] ?? [],
                            $disallowedIds,
                        );
                    } else {
                        $presentationState['disabled'][$targetAttrId] = $this->mergeOptionIds(
                            $presentationState['disabled'][$targetAttrId] ?? [],
                            $disallowedIds,
                        );
                    }
                }
            }

            if (($hiddenIds = $rule->hiddenOptionIds()) !== []) {
                $presentationState['hidden'][$targetAttrId] = $this->mergeOptionIds(
                    $presentationState['hidden'][$targetAttrId] ?? [],
                    $hiddenIds,
                );
            }

            if (($disabledIds = $rule->disabledOptionIds()) !== []) {
                $presentationState['disabled'][$targetAttrId] = $this->mergeOptionIds(
                    $presentationState['disabled'][$targetAttrId] ?? [],
                    $disabledIds,
                );
            }

            $presentationState['label_overrides'] = array_replace(
                $presentationState['label_overrides'],
                $rule->labelOverrides(),
            );

            $presentationState['hints'] = array_replace(
                $presentationState['hints'],
                $rule->hintOverrides(),
            );
        }

        foreach ($allowed as $attributeId => $allowedOptionIds) {
            $blockedOptionIds = $this->mergeOptionIds(
                $presentationState['hidden'][$attributeId] ?? [],
                $presentationState['disabled'][$attributeId] ?? [],
            );

            if ($blockedOptionIds !== []) {
                $allowed[$attributeId] = array_values(array_diff($allowedOptionIds, $blockedOptionIds));
            }
        }

        return [
            'allowed' => $this->normalizePresentationBucket($allowed),
            'hidden' => $this->normalizePresentationBucket($presentationState['hidden']),
            'disabled' => $this->normalizePresentationBucket($presentationState['disabled']),
            'label_overrides' => $presentationState['label_overrides'],
            'hints' => $presentationState['hints'],
        ];
    }

    /**
     * @param  array{stages: array<int, array{id:int, options: array<int, array{id:int, is_active:bool}>}>}  $manifest
     * @return array<int, int[]>
     */
    public function baseAllowedFromManifest(array $manifest): array
    {
        $allowed = [];

        foreach (($manifest['stages'] ?? []) as $stage) {
            $stageId = (int) ($stage['id'] ?? 0);
            if (! $stageId) {
                continue;
            }

            $allowed[$stageId] = collect($stage['options'] ?? [])
                ->filter(fn (array $o): bool => (bool) ($o['is_active'] ?? false))
                ->map(fn (array $o): int => (int) $o['id'])
                ->values()
                ->all();
        }

        return $allowed;
    }

    /**
     * Client-side friendly rule evaluation.
     *
     * @param  array{stages: array, rules: array}  $manifest
     * @param  array<int, int>  $selection  attribute_id => option_id
     * @return array<int, int[]> allowed options per attribute
     */
    public function recalculateAllowedFromManifest(array $manifest, array $selection): array
    {
        $allowed = $this->baseAllowedFromManifest($manifest);

        if ($selection === []) {
            return $allowed;
        }

        $rulesByTrigger = [];
        foreach (($manifest['rules'] ?? []) as $rule) {
            $triggerOptionId = (int) ($rule['trigger_option_id'] ?? 0);
            if (! $triggerOptionId) {
                continue;
            }

            $rulesByTrigger[$triggerOptionId][] = $rule;
        }

        foreach (array_values($selection) as $selectedOptionId) {
            foreach (($rulesByTrigger[(int) $selectedOptionId] ?? []) as $rule) {
                $targetAttrId = (int) ($rule['target_attribute_id'] ?? 0);
                $allowedIds = $rule['allowed_option_ids'] ?? [];

                if ($targetAttrId === 0 || $allowedIds === [] || ! isset($allowed[$targetAttrId])) {
                    continue;
                }

                $allowed[$targetAttrId] = array_values(array_intersect($allowed[$targetAttrId], $allowedIds));
            }
        }

        return $allowed;
    }

    public function recalculateAllowed(ConfigProfile $profile, array $stages, array $selection, array $context = []): array
    {
        return $this->evaluateState($profile, $stages, $selection, $context)['allowed'];
    }

    public function fillMissingSelections(array $stages, array $allowed, array $selection): array
    {
        foreach ($stages as $stage) {
            $attrId = $stage->id;
            $current = $selection[$attrId] ?? null;
            $allowedIds = $allowed[$attrId] ?? [];

            if ($allowedIds === []) {
                unset($selection[$attrId]);

                continue;
            }

            if ($current && in_array($current, $allowedIds, true)) {
                continue;
            }

            $default = collect($stage->options)
                ->first(fn (ConfigOptionDTO $o) => $o->isDefault && $o->isActive && in_array($o->id, $allowedIds, true));

            if ($default instanceof ConfigOptionDTO) {
                $selection[$attrId] = $default->id;

                continue;
            }

            $selection[$attrId] = $allowedIds[0];
        }

        return $selection;
    }

    public function pruneInvalidSelections(array $stages, array $selection, array $allowed): array
    {
        $stageIds = collect($stages)->map(fn (ConfigStageDTO $s) => $s->id)->all();

        foreach ($selection as $attrId => $optionId) {
            if (! in_array($attrId, $stageIds, true)) {
                unset($selection[$attrId]);

                continue;
            }

            $allowedIds = $allowed[$attrId] ?? [];
            if (! in_array($optionId, $allowedIds, true)) {
                unset($selection[$attrId]);
            }
        }

        return $selection;
    }

    public function isComplete(array $stages, array $selection): bool
    {
        $stageCount = count($stages);

        if ($stageCount === 0 || count($selection) < $stageCount) {
            return false;
        }

        foreach ($stages as $stage) {
            if ($stage->isRequired && ! isset($selection[$stage->id])) {
                return false;
            }
        }

        return true;
    }

    public function buildConfigurationCode(array $stages, array $selection): ?string
    {
        if (! $this->isComplete($stages, $selection)) {
            return null;
        }

        $sorted = collect($stages)
            ->sortBy(fn (ConfigStageDTO $s) => [$s->segmentIndex ?? $s->sortOrder, $s->sortOrder])
            ->values();

        $segments = [];

        foreach ($sorted as $stage) {
            $optionId = $selection[$stage->id] ?? null;
            if (! $optionId) {
                return null;
            }

            /** @var ConfigOptionDTO|null $opt */
            $opt = collect($stage->options)->firstWhere('id', $optionId);

            if (! $opt) {
                return null;
            }

            $segments[] = $opt->code;
        }

        return implode('-', $segments);
    }

    public function collectUiActions(
        ConfigProfile $profile,
        array $stages,
        array $selection,
        array $allowed,
        array $context = [],
    ): array {
        $evaluatedState = $this->evaluateState($profile, $stages, $selection, $context);

        return [
            'hidden' => $evaluatedState['hidden'],
            'disabled' => $evaluatedState['disabled'],
            'label_overrides' => $evaluatedState['label_overrides'],
            'hints' => $evaluatedState['hints'],
        ];
    }

    /**
     * @param  array<int, int>  $selection
     * @param  ConfigStageDTO[]  $stages
     * @param  array<string, mixed>  $context
     * @return Collection<int, OptionRule>
     */
    protected function activeRulesForEvaluation(ConfigProfile $profile, array $selection, array $stages, array $context = []): Collection
    {
        /** @var Collection<int, OptionRule> $rules */
        $rules = $profile->relationLoaded('rules')
            ? $profile->rules
            : $profile->rules()->get();

        return $rules
            ->filter(fn (OptionRule $rule): bool => (bool) $rule->is_active)
            ->filter(fn (OptionRule $rule): bool => in_array((int) $rule->config_option_id, $selection, true))
            ->filter(fn (OptionRule $rule): bool => $this->ruleMatchesContext($rule, $selection, $stages, $context))
            ->sortBy([
                ['priority', 'asc'],
                ['id', 'asc'],
            ])
            ->values();
    }

    /**
     * @param  array<int, int>  $selection
     * @param  ConfigStageDTO[]  $stages
     * @param  array<string, mixed>  $context
     */
    protected function ruleMatchesContext(OptionRule $rule, array $selection, array $stages, array $context = []): bool
    {
        $conditions = $rule->activationConditions();

        if ($conditions === []) {
            return true;
        }

        foreach ($conditions as $condition) {
            $actual = $this->resolveConditionValue((string) ($condition['source'] ?? ''), $selection, $stages, $context);
            $expected = $condition['value'] ?? null;
            $operator = (string) ($condition['operator'] ?? '=');

            if (! $this->matchesCondition($actual, $operator, $expected)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, int>  $selection
     * @param  ConfigStageDTO[]  $stages
     * @param  array<string, mixed>  $context
     */
    protected function resolveConditionValue(string $source, array $selection, array $stages, array $context): mixed
    {
        if ($source === 'configuration_code') {
            return $this->buildConfigurationCode($stages, $selection);
        }

        if (str_starts_with($source, 'context.')) {
            return data_get($context, substr($source, 8));
        }

        if (str_starts_with($source, 'selection_code.')) {
            return $this->resolveSelectionValue(substr($source, 15), $selection, $stages, true);
        }

        if (str_starts_with($source, 'selection.')) {
            return $this->resolveSelectionValue(substr($source, 10), $selection, $stages);
        }

        return null;
    }

    /**
     * @param  array<int, int>  $selection
     * @param  ConfigStageDTO[]  $stages
     */
    protected function resolveSelectionValue(string $stageKey, array $selection, array $stages, bool $returnCode = false): mixed
    {
        if ($stageKey === '') {
            return null;
        }

        $stage = collect($stages)->first(function (ConfigStageDTO $candidate) use ($stageKey): bool {
            if ((string) $candidate->id === $stageKey) {
                return true;
            }

            return $candidate->slug === $stageKey;
        });

        if (! $stage instanceof ConfigStageDTO) {
            return null;
        }

        $selectedOptionId = $selection[$stage->id] ?? null;

        if (! $returnCode || ! $selectedOptionId) {
            return $selectedOptionId;
        }

        /** @var ConfigOptionDTO|null $selectedOption */
        $selectedOption = collect($stage->options)->firstWhere('id', $selectedOptionId);

        return $selectedOption?->code;
    }

    protected function matchesCondition(mixed $actual, string $operator, mixed $expected): bool
    {
        return match ($operator) {
            '=', '==' => $actual == $expected,
            '!=', '<>' => $actual != $expected,
            'in' => in_array($actual, (array) $expected, true),
            'not_in' => ! in_array($actual, (array) $expected, true),
            'contains' => is_array($actual)
                ? in_array($expected, $actual, true)
                : str_contains((string) $actual, (string) $expected),
            default => false,
        };
    }

    /**
     * @return array{
     *     hidden: array<int, int[]>,
     *     disabled: array<int, int[]>,
     *     label_overrides: array<string, string>,
     *     hints: array<string, string>
     * }
     */
    protected function basePresentationState(ConfigProfile $profile): array
    {
        $attributes = $profile->relationLoaded('attributes')
            ? $profile->attributes
            : $profile->attributes()->with('options')->get();

        $hidden = [];
        $disabled = [];
        $labelOverrides = [];
        $hints = [];

        foreach ($attributes as $attribute) {
            $options = $attribute->relationLoaded('options')
                ? $attribute->options
                : $attribute->options()->orderBy('sort_order')->get();

            $hidden[$attribute->id] = $options
                ->filter(fn (ConfigOption $option): bool => $option->isHiddenByDefault())
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->values()
                ->all();

            $disabled[$attribute->id] = $options
                ->filter(fn (ConfigOption $option): bool => $option->isDisabledByDefault())
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->values()
                ->all();

            foreach ($options as $option) {
                if (($shortLabel = $option->shortLabel()) !== null) {
                    $labelOverrides[(string) $option->id] = $shortLabel;
                }

                if (($hint = $option->hintText()) !== null) {
                    $hints[(string) $option->id] = $hint;
                }
            }
        }

        return [
            'hidden' => $hidden,
            'disabled' => $disabled,
            'label_overrides' => $labelOverrides,
            'hints' => $hints,
        ];
    }

    /**
     * @return array<int, int[]>
     */
    protected function targetOptionIdsByAttribute(ConfigProfile $profile): array
    {
        $attributes = $profile->relationLoaded('attributes')
            ? $profile->attributes
            : $profile->attributes()->with('options')->get();

        return $attributes
            ->mapWithKeys(function (ConfigAttribute $attribute): array {
                $options = $attribute->relationLoaded('options')
                    ? $attribute->options
                    : $attribute->options()->orderBy('sort_order')->get();

                return [
                    (int) $attribute->id => $options
                        ->pluck('id')
                        ->map(fn (mixed $id): int => (int) $id)
                        ->values()
                        ->all(),
                ];
            })
            ->all();
    }

    /**
     * @param  array<int, int[]>  $bucket
     * @return array<int, int[]>
     */
    protected function normalizePresentationBucket(array $bucket): array
    {
        foreach ($bucket as $attributeId => $optionIds) {
            $bucket[$attributeId] = $this->mergeOptionIds($optionIds);
        }

        return $bucket;
    }

    /**
     * @param  int[]  ...$optionGroups
     * @return int[]
     */
    protected function mergeOptionIds(array ...$optionGroups): array
    {
        return collect($optionGroups)
            ->flatten()
            ->map(fn (mixed $value): int => (int) $value)
            ->filter(fn (int $value): bool => $value > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
