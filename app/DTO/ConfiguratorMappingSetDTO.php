<?php

namespace App\DTO;

use App\MappingTargetBehavior;

final readonly class ConfiguratorMappingSetDTO
{
    /**
     * @param  list<string>  $sources
     * @param  list<string>  $targets
     */
    public function __construct(
        public string $id,
        public array $sources,
        public array $targets,
        public MappingTargetBehavior $disallowedTargetBehavior = MappingTargetBehavior::Disable,
    ) {}
}
