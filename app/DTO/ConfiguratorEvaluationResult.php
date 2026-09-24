<?php

namespace App\DTO;

final readonly class ConfiguratorEvaluationResult
{
    /**
     * @param  array<string, array{applicable: bool, legal: list<string>, hidden: list<string>, disabled: list<string>, label: string, display_value: ?string, hint: ?string, options: array<string, array{label: string, display_value: string, hint: ?string}>}>  $attributes
     * @param  array<string, string>  $selections
     * @param  array<string, string>  $remembered
     * @param  array{territory: string, application: string}  $context
     * @param  list<array<string, mixed>>  $diagnostics
     */
    public function __construct(
        public ?ConfiguratorDefinition $definition,
        public array $attributes,
        public array $selections,
        public array $remembered,
        public array $context,
        public array $diagnostics,
        public bool $isComplete,
        public ?string $configurationCode,
        public ?int $configuratorId = null,
    ) {}

    /** @return array{version: int, configurator_id: ?int, context: array<string, string>, selections: array<string, string>, remembered: array<string, string>} */
    public function state(): array
    {
        return ['version' => 1, 'configurator_id' => $this->configuratorId, 'context' => $this->context, 'selections' => $this->selections, 'remembered' => $this->remembered];
    }
}
