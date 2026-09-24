<?php

namespace App\DTO;

use App\RuleKind;

final readonly class ConfiguratorRuleDTO
{
    /**
     * @param  list<ConfiguratorMappingSetDTO>  $sets
     * @param  list<ConfiguratorEffectDTO>  $effects
     */
    public function __construct(
        public string $id,
        public string $label,
        public RuleKind $kind,
        public bool $active,
        public int $priority,
        public ConfiguratorConditionGroupDTO $conditions,
        public ?string $driverId,
        public ?string $targetId,
        public array $sets,
        public array $effects,
    ) {}
}
