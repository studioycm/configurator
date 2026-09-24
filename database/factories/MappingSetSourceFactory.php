<?php

namespace Database\Factories;

use App\Models\ConfiguratorOption;
use App\Models\MappingSet;
use App\Models\MappingSetSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MappingSetSource> */
class MappingSetSourceFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['mapping_set_id' => MappingSet::factory(), 'rule_id' => fn (array $attributes): int => MappingSet::findOrFail($attributes['mapping_set_id'])->rule_id, 'configurator_option_id' => ConfiguratorOption::factory()];
    }
}
