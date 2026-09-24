<?php

namespace Database\Factories;

use App\Models\ConfiguratorAttribute;
use App\Models\ConfiguratorOption;
use App\Models\Option;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ConfiguratorOption> */
class ConfiguratorOptionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['configurator_attribute_id' => ConfiguratorAttribute::factory(), 'option_id' => fn (array $attributes): int => Option::factory()->create(['attribute_id' => ConfiguratorAttribute::findOrFail($attributes['configurator_attribute_id'])->attribute_id])->id, 'display_order' => 0, 'hidden_by_default' => false, 'disabled_by_default' => false];
    }
}
