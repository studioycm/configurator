<?php

namespace App\Filament\Pages;

use App\Models\ConfigAttribute;
use App\Models\ConfigProfile;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class ConfiguratorDemo extends Page
{
//    protected string $view = 'filament.pages.configurator-demo';
    protected static ?string $slug = 'configurator-demo';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';
//    protected static string $view = 'filament.pages.configurator-demo';
    protected  string $view = 'filament.pages.configurator-demo';

    public function getHeading(): string
    {
        return __('Combination Air Valve, D-060 Series');
    }
    public static function getNavigationLabel(): string
    {
        return __('D60S Demo');
    }

    public function getTitle(): string | Htmlable
    {
        return __('D60S');
    }

    public ?ConfigProfile $configProfile = null;

    /** @var array<int, array> */
    public array $stages = [];   // instead of $attributes

    /** @var array<int, int> attribute_id => option_id */
    public array $selection = [];

    /** @var array<int, array<int>> attribute_id => [allowed_option_ids...] */
    public array $allowed = [];

    public function mount(): void
    {
        // Lock to the D60S profile created by the seeder
        $this->configProfile = ConfigProfile::query()
            ->with([
                'productProfile',
                'attributes' => fn ($q) => $q->orderBy('sort_order'),
                'attributes.options' => fn ($q) => $q->orderBy('sort_order'),
                'rules',
            ])
            ->where('slug', 'd60s-p16-03-configurator')
            ->firstOrFail();

        $this->buildStagesArray();
        $this->setDefaultsFromProfile();
        $this->recalculateAllowed();
    }

    protected function buildStagesArray(): void
    {
        $attributes = $this->configProfile->attributes;

        $this->stages = $attributes
            ->map(function (ConfigAttribute $attr) {
                return [
                    'id' => $attr->id,
                    'label' => $attr->label ?? $attr->name,
                    'segment_index' => $attr->segment_index ?? $attr->sort_order,
                    'options' => $attr->options
                        ->map(fn ($opt) => [
                            'id' => $opt->id,
                            'label' => $opt->label,
                            'code' => $opt->code,
                            'is_default' => (bool) $opt->is_default,
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    protected function setDefaultsFromProfile(): void
    {
        $this->selection = [];

        foreach ($this->stages as $stage) {
            $default = collect($stage['options'])->firstWhere('is_default', true);

            if ($default) {
                $this->selection[$stage['id']] = $default['id'];
            }
        }
    }

    protected function resetAllowed(): void
    {
        $this->allowed = [];

        foreach ($this->stages as $stage) {
            $this->allowed[$stage['id']] = collect($stage['options'])->pluck('id')->all();
        }
    }

    public function selectOption(int $attributeId, int $optionId): void
    {
        $this->selection[$attributeId] = $optionId;

        $this->recalculateAllowed();
    }

    protected function recalculateAllowed(): void
    {
        // Start with "everything allowed"
        $this->resetAllowed();

        if (empty($this->selection)) {
            return;
        }

        $rules = $this->configProfile->rules;

        // Apply forward-only rules based on currently selected options
        foreach ($this->selection as $selectedOptionId) {
            $affectedRules = $rules->where('config_option_id', $selectedOptionId);

            foreach ($affectedRules as $rule) {
                $targetAttrId = $rule->target_attribute_id;
                $allowedIds   = $rule->allowed_option_ids ?? [];

                if (! isset($this->allowed[$targetAttrId]) || empty($allowedIds)) {
                    continue;
                }

                $this->allowed[$targetAttrId] = array_values(
                    array_intersect($this->allowed[$targetAttrId], $allowedIds),
                );
            }
        }

        // Ensure every stage has a valid selection under the new allowed sets
        foreach ($this->stages as $stage) {
            $attrId     = $stage['id'];
            $allowedIds = $this->allowed[$attrId] ?? [];
            $current    = $this->selection[$attrId] ?? null;

            // No allowed options at all – clear selection for this attribute only
            if ($allowedIds === []) {
                unset($this->selection[$attrId]);
                continue;
            }

            // Current selection is still allowed – keep it
            if ($current && in_array($current, $allowedIds, true)) {
                continue;
            }

            // Otherwise choose a new valid selection:
            // 1) default if allowed, else 2) first allowed
            $default = collect($stage['options'])->firstWhere('is_default', true);

            if ($default && in_array($default['id'], $allowedIds, true)) {
                $this->selection[$attrId] = $default['id'];
            } else {
                $this->selection[$attrId] = $allowedIds[0];
            }
        }
    }


    public function getCurrentCodeProperty(): ?string
    {
        if (count($this->selection) !== count($this->stages)) {
            return null;
        }

        $attrs = collect($this->stages)
            ->sortBy('segment_index')
            ->values();

        $segments = [];

        foreach ($attrs as $attr) {
            $attrId = $attr['id'];
            $optionId = $this->selection[$attrId] ?? null;

            if (! $optionId) {
                return null;
            }

            $opt = collect($attr['options'])->firstWhere('id', $optionId);

            if (! $opt) {
                return null;
            }

            $segments[] = $opt['code'];
        }

        return implode('-', $segments);
    }
}
