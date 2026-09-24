<?php

namespace App\DTO;

use App\ConditionJunction;

final readonly class ConfiguratorConditionGroupDTO
{
    /**
     * @param  list<ConfiguratorConditionDTO|ConfiguratorConditionGroupDTO>  $conditions
     */
    public function __construct(
        public string $id,
        public ConditionJunction $operator,
        public array $conditions,
    ) {}
}
