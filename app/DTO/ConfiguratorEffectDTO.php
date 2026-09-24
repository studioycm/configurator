<?php

namespace App\DTO;

use App\RuleEffectKind;
use App\RuleTargetScope;

final readonly class ConfiguratorEffectDTO
{
    /**
     * @param  list<string>  $optionIds
     */
    public function __construct(
        public string $id,
        public string $attributeId,
        public RuleEffectKind $kind,
        public RuleTargetScope $scope,
        public array $optionIds,
        public ?string $value,
    ) {}
}
