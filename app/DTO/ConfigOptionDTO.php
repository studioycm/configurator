<?php

namespace App\DTO;

final class ConfigOptionDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $label,
        public readonly string $code,
        public readonly int $sortOrder,
        public readonly bool $isDefault,
        public readonly bool $isActive,
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'code' => $this->code,
            'sort_order' => $this->sortOrder,
            'is_default' => $this->isDefault,
            'is_active' => $this->isActive,
        ];
    }
}
