<?php

namespace Database\Factories;

use App\Models\Configurator;
use App\Models\ConfiguratorRuleWritingDraft;
use App\RuleKind;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ConfiguratorRuleWritingDraft> */
class ConfiguratorRuleWritingDraftFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['configurator_id' => Configurator::factory(), 'kind' => RuleKind::Advanced, 'source' => '', 'label' => null, 'description' => null, 'format_version' => 1, 'revision' => 1];
    }
}
