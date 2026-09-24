<?php

namespace App\DTO;

final readonly class ConfiguratorEvaluationInput
{
    /**
     * @param  array<string, mixed>  $properties  Server-loaded Product facts.
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $selections
     * @param  array<string, mixed>  $remembered
     * @param  list<array<string, mixed>>  $diagnostics
     */
    public function __construct(
        public ?ConfiguratorDefinition $definition,
        public array $properties = [],
        public array $context = [],
        public array $selections = [],
        public array $remembered = [],
        public ConfiguratorInteraction $intent = new ConfiguratorInteraction,
        public ?int $configuratorId = null,
        public array $diagnostics = [],
    ) {}
}
