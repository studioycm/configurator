<?php

namespace App\DTO;

final readonly class ConfiguratorAttributeDTO
{
    /**
     * @param  array<string, ConfiguratorOptionDTO>  $options
     */
    public function __construct(
        public string $id,
        public int $canonicalId,
        public string $key,
        public string $label,
        public string $inputType,
        public ?string $help,
        public int $displayOrder,
        public int $codeOrder,
        public string $defaultOptionId,
        public array $options,
    ) {}
}
