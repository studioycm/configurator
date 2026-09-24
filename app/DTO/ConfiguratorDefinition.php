<?php

namespace App\DTO;

use App\Services\ConfiguratorPolicy;

final readonly class ConfiguratorDefinition
{
    /**
     * @param  array<string, ConfiguratorAttributeDTO>  $attributes
     * @param  list<ConfiguratorRuleDTO>  $rules
     * @param  list<string>  $evaluationOrder
     * @param  array{territory: list<array{value: string, label: string}>, application: list<array{value: string, label: string}>}  $contextSchema
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public array $attributes,
        public array $rules,
        public array $evaluationOrder,
        public array $contextSchema,
        public array $data,
        public ConfiguratorPolicy $policy,
    ) {}
}
