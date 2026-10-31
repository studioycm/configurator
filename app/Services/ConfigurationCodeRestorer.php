<?php

namespace App\Services;

use App\ConfiguratorIntentType;
use App\DTO\ConfiguratorEvaluationInput;
use App\DTO\ConfiguratorEvaluationResult;
use App\DTO\ConfiguratorInteraction;
use Illuminate\Validation\ValidationException;

final class ConfigurationCodeRestorer
{
    public function __construct(private ConfiguratorEngine $engine) {}

    public function restore(ConfiguratorEvaluationInput $input, string $code): ConfiguratorEvaluationResult
    {
        $definition = $input->definition;
        $separator = $definition?->policy->codeSeparator ?? '-';
        $count = count($definition?->attributes ?? []);
        $maximum = min(32768, $count * 2 + max(0, $count - 1) * strlen($separator));
        if ($definition === null || $code === '' || strlen($code) > $maximum) {
            $this->reject();
        }

        $optionsByCode = [];
        foreach ($definition->attributes as $attribute) {
            foreach ($attribute->options as $option) {
                $optionsByCode[$option->code] = [$attribute->id, $option->id];
            }
        }
        $selections = [];
        foreach (explode($separator, $code) as $token) {
            if (preg_match('/\A[A-Za-z0-9]{2}\z/D', $token) !== 1 || ! isset($optionsByCode[$token])) {
                $this->reject();
            }
            [$attributeId, $optionId] = $optionsByCode[$token];
            if (array_key_exists($attributeId, $selections)) {
                $this->reject();
            }
            $selections[$attributeId] = $optionId;
        }

        $result = $this->engine->evaluate(new ConfiguratorEvaluationInput(
            $definition, $input->properties, $input->context, $selections, [],
            new ConfiguratorInteraction(ConfiguratorIntentType::Reevaluate),
            $input->configuratorId, $input->diagnostics, $input->trace,
        ));
        if (! $result->isComplete || $result->configurationCode !== $code) {
            $this->reject();
        }
        foreach ($selections as $attributeId => $optionId) {
            if (! ($result->attributes[$attributeId]['applicable'] ?? false)
                || ($result->selections[$attributeId] ?? null) !== $optionId) {
                $this->reject();
            }
        }

        return $result;
    }

    private function reject(): never
    {
        throw ValidationException::withMessages(['configuration' => __('configurator.copy.invalid_configuration_code')]);
    }
}
