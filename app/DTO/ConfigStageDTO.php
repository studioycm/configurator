<?php

namespace App\DTO;

/**
 * Represents a single configuration stage (attribute) with its available options.
 */
final class ConfigStageDTO
{
    /**
     * @param  ConfigOptionDTO[]  $options
     */
    public function __construct(
        public readonly int $id,
        public readonly ?string $slug,
        public readonly string $label,
        public readonly int $sortOrder,
        public readonly ?int $segmentIndex,
        public readonly bool $isRequired,
        public readonly array $options,
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'label' => $this->label,
            'sort_order' => $this->sortOrder,
            'segment_index' => $this->segmentIndex,
            'is_required' => $this->isRequired,
            'options' => array_map(fn (ConfigOptionDTO $o) => $o->toArray(), $this->options),
        ];
    }
}
