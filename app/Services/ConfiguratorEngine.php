<?php

namespace App\Services;

use App\DTO\ConfigOptionDTO;
use App\DTO\ConfigStageDTO;
use App\Models\ConfigAttribute;
use App\Models\ConfigProfile;
use App\Models\ConfigOption;
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

    public function recalculateAllowed(ConfigProfile $profile, array $stages, array $selection): array
    {
        $allowed = $this->baseAllowed($stages);

        if ($selection === []) {
            return $allowed;
        }

        /** @var Collection<int, OptionRule> $rules */
        $rules = $profile->rules()->get();

        foreach ($selection as $selectedOptionId) {
            $affectedRules = $rules->where('config_option_id', $selectedOptionId);

            foreach ($affectedRules as $rule) {
                $targetAttrId = $rule->target_attribute_id;
                $allowedIds = $rule->allowed_option_ids ?? [];

                if ($allowedIds === [] || ! isset($allowed[$targetAttrId])) {
                    continue;
                }

                $allowed[$targetAttrId] = array_values(array_intersect($allowed[$targetAttrId], $allowedIds));
            }
        }

        return $allowed;
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
        array $allowed
    ): array {
        // Placeholder for future UI actions; returns empty per current stage scope.
        return [];
    }
}
