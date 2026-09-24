<?php

namespace Database\Factories;

use App\Models\Attribute;
use App\Models\Configurator;
use App\Models\ConfiguratorAttribute;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ConfiguratorAttribute> */
class ConfiguratorAttributeFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['configurator_id' => Configurator::factory(), 'attribute_id' => Attribute::factory(), 'display_order' => 0, 'code_order' => 0, 'input_type' => 'toggle'];
    }
}
