<?php

namespace App\Livewire\Catalog;

use App\DTO\ConfiguratorEvaluationInput;
use App\DTO\ConfiguratorEvaluationResult;
use App\Models\Product;
use App\Services\AdminAppearance;
use App\Services\CatalogAvailability;
use App\Services\CatalogJourney;
use App\Services\CatalogPresentation;
use App\Services\ConfigurationCodeRestorer;
use App\Services\ConfiguratorDefinitionLoader;
use App\Services\ConfiguratorEngine;
use App\Services\ProductEditorData;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
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
        if ($this->supportsSavedCodeRestoration() && array_key_exists('configuration', request()->query())) {
            $this->loadConfigurationCode(CatalogJourney::configurationCodeFromQuery(request()->query(), (string) request()->server('QUERY_STRING', '')));
        }
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

    protected function supportsSavedCodeRestoration(): bool
    {
        return true;
    }

    /** @return array{componentId: string, productId: int, accepted: bool, code: ?string} */
    public function loadConfigurationCode(mixed $code): array
    {
        $this->assertSavedCodeAccess();
        $this->resetErrorBag('configuration');
        try {
            if (! is_string($code) || strlen($code) > 32768) {
                throw ValidationException::withMessages(['configuration' => __('configurator.copy.invalid_configuration_code')]);
            }
            $candidate = app(ConfigurationCodeRestorer::class)->restore($this->input(['kind' => 'Reevaluate']), $code);
        } catch (ValidationException $exception) {
            $this->preparedResult = app(ConfiguratorEngine::class)->evaluate($this->input(['kind' => 'Reevaluate']));
            $this->addError('configuration', $exception->errors()['configuration'][0] ?? __('configurator.copy.invalid_configuration_code'));

            return $this->configurationReply(false);
        }
        $this->preparedResult = $candidate;
        $this->runtime = $candidate->state();
        $this->evaluated();

        return $this->configurationReply();
    }

    /** @return array{componentId: string, productId: int, accepted: bool, code: ?string} */
    public function restoreConfigurationHistory(mixed $code): array
    {
        $this->assertSavedCodeAccess();
        if ($code !== null) {
            return $this->loadConfigurationCode($code);
        }
        $this->resetErrorBag('configuration');
        $this->preparedResult = app(ConfiguratorEngine::class)->evaluate(app(ConfiguratorDefinitionLoader::class)->forDashboardProduct($this->productId, [], ['kind' => 'Initialize']));
        $this->runtime = $this->preparedResult->state();
        $this->evaluated();

        return $this->configurationReply();
    }

    /** @return array{componentId: string, productId: int, accepted: bool, code: ?string} */
    public function chooseConfigurationOption(string $attributeId, string $optionId): array
    {
        $this->assertSavedCodeAccess();
        $this->resetErrorBag('configuration');
        $this->selectOption($attributeId, $optionId);

        return $this->configurationReply();
    }

    /** @return array{componentId: string, productId: int, accepted: bool, code: ?string} */
    public function reevaluateConfiguration(): array
    {
        $this->assertSavedCodeAccess();
        $this->resetErrorBag('configuration');
        $this->refreshDefinition();

        return $this->configurationReply();
    }

    private function assertSavedCodeAccess(): void
    {
        abort_unless($this->supportsSavedCodeRestoration() && auth()->check(), 403);
        abort_if($this->productId === null, 404);
        app(CatalogAvailability::class)->assertProduct(Product::findOrFail($this->productId));
    }

    /** @return array{componentId: string, productId: int, accepted: bool, code: ?string} */
    private function configurationReply(bool $accepted = true): array
    {
        return ['componentId' => $this->getId(), 'productId' => $this->productId, 'accepted' => $accepted,
            'code' => $this->result()->isComplete ? $this->result()->configurationCode : null];
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

        return app(ConfiguratorDefinitionLoader::class)->forDashboardProduct($this->productId, $this->runtime, $intent);
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
        $product = Product::with('group.filters')->findOrFail($this->productId);
        app(CatalogAvailability::class)->assertProduct($product);
        $shared = app(CatalogPresentation::class)->current();

        return view('livewire.catalog.product-configurator', [
            'result' => $this->result(),
            'product' => $product,
            'propertyFields' => ProductEditorData::groupFields($product),
            'showLoadingIndicator' => app(AdminAppearance::class)->current()['show_configurator_loading_indicator'],
            'pagePresentation' => ['layout' => $shared['settings']['product_page_layout'], 'scale' => $shared['settings']['product_page_image_scale'], 'displayVersion' => $shared['version']],
        ]);
    }
}
