<?php

namespace App\Livewire\Catalog;

use App\DTO\ConfiguratorEvaluationInput;
use App\DTO\ConfiguratorEvaluationResult;
use App\Services\ConfiguratorDefinitionLoader;
use App\Services\ConfiguratorEngine;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ProductConfigurator extends Component
{
    #[Locked]
    public ?int $productId = null;

    /** @var array<string, mixed> */
    #[Locked]
    public array $runtime = [];

    protected ?ConfiguratorEvaluationResult $preparedResult = null;

    public function mount(int $productId): void
    {
        $this->productId = $productId;
        $this->evaluate(['kind' => 'Initialize']);
    }

    public function selectOption(string $attributeId, string $optionId): void
    {
        $this->evaluate(['kind' => 'SelectOption', 'attribute_id' => $attributeId, 'option_id' => $optionId]);
    }

    public function changeContext(string $dimension, string $choice): void
    {
        $this->evaluate(['kind' => 'ChangeContext', 'dimension' => $dimension, 'choice' => $choice]);
    }

    public function refreshDefinition(): void
    {
        $this->evaluate(['kind' => 'Reevaluate']);
    }

    /** @param array<string, mixed> $intent */
    protected function evaluate(array $intent): void
    {
        $this->preparedResult = app(ConfiguratorEngine::class)->evaluate($this->input($intent));
        $this->runtime = $this->preparedResult->state();
        $this->evaluated();
    }

    /** @param array<string, mixed> $intent */
    protected function input(array $intent): ConfiguratorEvaluationInput
    {
        abort_if($this->productId === null, 404);

        return app(ConfiguratorDefinitionLoader::class)->forProduct($this->productId, $this->runtime, $intent);
    }

    protected function result(): ConfiguratorEvaluationResult
    {
        if ($this->preparedResult === null) {
            $this->evaluate(['kind' => 'Reevaluate']);
        }

        return $this->preparedResult;
    }

    protected function evaluated(): void {}

    public function render(): View
    {
        return view('livewire.catalog.product-configurator', ['result' => $this->result()]);
    }
}
