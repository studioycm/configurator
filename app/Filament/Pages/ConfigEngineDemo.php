<?php

namespace App\Filament\Pages;

use App\DTO\ConfigOptionDTO;
use App\DTO\ConfigStageDTO;
use App\Models\ConfigProfile;
use App\Models\ProductConfiguration;
use App\Services\ConfiguratorEngine;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class ConfigEngineDemo extends Page
{
    protected static ?string $slug = 'config-engine-demo';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-vertical';

    protected string $view = 'filament.pages.config-engine-demo';

    public ?ConfigProfile $configProfile = null;

    public ?ProductConfiguration $demoConfiguration = null;

    /** @var array<int, array<string, mixed>> */
    public array $stages = [];

    /** @var array<int, int> attribute_id => option_id */
    public array $selection = [];

    /** @var array<int, array<int>> attribute_id => [allowed_option_ids...] */
    public array $allowed = [];

    protected ?ConfiguratorEngine $engine = null;

    public function getHeading(): string
    {
        return __('Combination Air Valve, D-060 Series (Engine)');
    }

    public static function getNavigationLabel(): string
    {
        return __('D60S Engine Demo');
    }

    public function getTitle(): string|Htmlable
    {
        return __('D60S Engine');
    }

    public function boot(ConfiguratorEngine $engine): void
    {
        $this->engine = $engine;
    }

    public function mount(): void
    {
        $this->engine ??= app(ConfiguratorEngine::class);

        $this->configProfile = ConfigProfile::query()
            ->with([
                'productProfile.catalogGroup.mainImage',
                'productProfile.catalogGroup.fileAttachments',
                'productProfile.catalogGroup.galleryImages',
                'productProfile.fileAttachments',
                'productProfile.mainImage',
                'productProfile.galleryImages',
                'attributes' => fn ($q) => $q->orderBy('sort_order'),
                'attributes.options' => fn ($q) => $q->orderBy('sort_order'),
                'rules',
            ])
            ->where('slug', 'd60s-p16-03-configurator')
            ->firstOrFail();

        $this->demoConfiguration = ProductConfiguration::query()
            ->with([
                'configurationParts.part',
                'configurationSpecifications',
                'mainImage',
                'galleryImages',
                'fileAttachments',
            ])
            ->where('product_profile_id', $this->configProfile->product_profile_id)
            ->orderBy('id')
            ->first();

        $stageDTOs = $this->engine->buildStages($this->configProfile);

        $this->stages = array_map(fn (ConfigStageDTO $dto) => $dto->toArray(), $stageDTOs);

        $this->selection = $this->engine->defaultSelection($stageDTOs);

        $this->allowed = $this->engine->recalculateAllowed(
            $this->configProfile,
            $stageDTOs,
            $this->selection,
        );

        $this->selection = $this->engine->pruneInvalidSelections(
            $stageDTOs,
            $this->selection,
            $this->allowed,
        );

        $this->selection = $this->engine->fillMissingSelections(
            $stageDTOs,
            $this->allowed,
            $this->selection,
        );
    }

    public function selectOption(int $attributeId, int $optionId): void
    {
        $this->selection[$attributeId] = $optionId;

        $stageDTOs = $this->stageDTOsFromData();

        $this->allowed = $this->engine->recalculateAllowed(
            $this->configProfile,
            $stageDTOs,
            $this->selection,
        );

        $this->selection = $this->engine->pruneInvalidSelections(
            $stageDTOs,
            $this->selection,
            $this->allowed,
        );

        $this->selection = $this->engine->fillMissingSelections(
            $stageDTOs,
            $this->allowed,
            $this->selection,
        );
    }

    public function getCurrentCodeProperty(): ?string
    {
        $stageDTOs = $this->stageDTOsFromData();

        return $this->engine->buildConfigurationCode($stageDTOs, $this->selection);
    }

    /**
     * @return ConfigStageDTO[]
     */
    protected function stageDTOsFromData(): array
    {
        return array_map(function (array $stage) {
            $options = array_map(function (array $opt) {
                return new ConfigOptionDTO(
                    id: (int) $opt['id'],
                    label: $opt['label'],
                    code: $opt['code'],
                    sortOrder: (int) $opt['sort_order'],
                    isDefault: (bool) $opt['is_default'],
                    isActive: (bool) $opt['is_active'],
                );
            }, $stage['options']);

            return new ConfigStageDTO(
                id: (int) $stage['id'],
                slug: $stage['slug'] ?? null,
                label: $stage['label'],
                sortOrder: (int) $stage['sort_order'],
                segmentIndex: $stage['segment_index'] ?? null,
                isRequired: (bool) $stage['is_required'],
                options: $options,
            );
        }, $this->stages);
    }
}
