<?php

namespace App\DTO;

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
    ) {}
}
