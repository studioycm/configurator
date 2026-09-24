<?php

namespace App\DTO;

final readonly class ConfiguratorOptionDTO
{
    public function __construct(
        public string $id,
        public int $canonicalId,
        public string $code,
        public int $displayOrder,
        public string $label,
        public string $displayValue,
        public ?string $hint,
        public bool $hidden,
        public bool $disabled,
    ) {}
}
