<?php

namespace App\DTO;

use App\ConfiguratorIntentType;

final readonly class ConfiguratorInteraction
{
    public function __construct(
        public ConfiguratorIntentType $kind = ConfiguratorIntentType::Initialize,
        public ?string $attributeId = null,
        public ?string $optionId = null,
        public ?string $dimension = null,
        public ?string $choice = null,
        public bool $malformed = false,
    ) {}

    /** @param array<string, mixed> $input */
    public static function fromUntrusted(array $input): self
    {
        $kind = is_string($input['kind'] ?? null) ? ConfiguratorIntentType::tryFrom($input['kind']) : null;
        if ($kind === null || array_diff(array_keys($input), ['kind', 'attribute_id', 'option_id', 'dimension', 'choice']) !== []) {
            return new self(ConfiguratorIntentType::Reevaluate, malformed: true);
        }
        $values = [];
        foreach (['attribute_id', 'option_id', 'dimension', 'choice'] as $key) {
            $value = $input[$key] ?? null;
            if ($value !== null && ((! is_string($value) && ! is_int($value)) || strlen((string) $value) > 255)) {
                return new self(ConfiguratorIntentType::Reevaluate, malformed: true);
            }
            $values[$key] = $value === null ? null : (string) $value;
        }

        return new self($kind, $values['attribute_id'], $values['option_id'], $values['dimension'], $values['choice']);
    }
}
