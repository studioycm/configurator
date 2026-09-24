<?php

namespace App\DTO;

use App\ConditionOperator;
use App\ConditionSource;

final readonly class ConfiguratorConditionDTO
{
    /**
     * @param  list<string>  $optionIds
     * @param  string|list<string>|null  $operand
     */
    public function __construct(
        public string $id,
        public ConditionSource $source,
        public ?string $attributeId,
        public ?string $propertyKey,
        public ?string $contextDimension,
        public ConditionOperator $operator,
        public string|array|null $operand,
        public array $optionIds,
    ) {}
}
