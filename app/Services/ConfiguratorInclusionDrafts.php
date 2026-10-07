<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\Option;
use Illuminate\Support\Str;

class ConfiguratorInclusionDrafts
{
    /** @return array<string, mixed> */
    public function option(Option $option): array
    {
        return ['id' => 'new:'.Str::uuid(), 'option_id' => $option->id, 'label_override' => null, 'display_value_override' => null, 'hint' => null, 'hidden_by_default' => false, 'disabled_by_default' => false];
    }

    /** @return array<string, mixed> */
    public function attribute(int $attributeId): array
    {
        $attribute = Attribute::with(['options' => fn ($query) => $query->where('is_active', true)->where('is_hidden', false)])->findOrFail($attributeId);

        return ['id' => 'new:'.Str::uuid(), 'attribute_id' => $attribute->id, 'input_type' => 'toggle', 'label_override' => null, 'help_text' => null, 'default_configurator_option_id' => null,
            'options' => $attribute->options->sortBy('code')->values()->map(fn (Option $option): array => $this->option($option))->all()];
    }
}
