<?php

namespace App\Filament\Pages;

use App\DTO\ConfigOptionDTO;
use App\DTO\ConfigStageDTO;
use App\FileAttachmentType;
use App\Models\ConfigProfile;
use App\Models\ConfigurationSpecification;
use App\Models\FileAttachment;
use App\Models\ProductConfiguration;
use App\Services\ConfiguratorEngine;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Forms\Components\ToggleButtons;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;

class ConfigEngineDemo extends Page
{
    use InteractsWithActions;

    protected static ?string $slug = 'config-engine-demo';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-vertical';

    protected string $view = 'filament.pages.config-engine-demo';

    public ?ConfigProfile $configProfile = null;

    public ?ProductConfiguration $demoConfiguration = null;

    public string $territory = 'Global';

    public string $application = 'Show All';

    /** @var array<int, array<string, mixed>> */
    public array $stages = [];

    /** @var array<int, int> attribute_id => option_id */
    public array $selection = [];

    /** @var array<int, array<int>> attribute_id => [allowed_option_ids...] */
    public array $allowed = [];

    protected ?ConfiguratorEngine $engine = null;

    private const TERRITORY_SESSION_KEY = 'config_engine_demo.territory';

    private const APPLICATION_SESSION_KEY = 'config_engine_demo.application';

    /** @return array<string, string> */
    private function territoryOptions(): array
    {
        return [
            'Global' => 'Global',
            'USA' => 'USA',
            'Germany' => 'Germany',
            'Europe' => 'Europe',
            'Russia' => 'Russia',
            'Australia' => 'Australia',
        ];
    }

    /** @return array<string, string> */
    private function applicationOptions(): array
    {
        return [
            'Show All' => 'Show All',
            'Industry' => 'Industry',
            'Water Supply' => 'Water Supply',
            'Agriculture' => 'Agriculture',
            'Wastewater' => 'Wastewater',
        ];
    }

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

        $this->territory = (string) session()->get(self::TERRITORY_SESSION_KEY, 'Global');
        $this->application = (string) session()->get(self::APPLICATION_SESSION_KEY, 'Show All');

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

    public function editContextAction(): Action
    {
        return Action::make('editContext')
            ->label('Change')
            ->modalHeading('Territory & Application')
            ->modalSubmitActionLabel('Apply')
            ->modalWidth('lg')
            ->fillForm(fn (): array => [
                'territory' => $this->territory,
                'application' => $this->application,
            ])
            ->form([
                ToggleButtons::make('territory')
                    ->label('Territory')
                    ->options($this->territoryOptions())
                    ->inline()
                    ->required(),
                ToggleButtons::make('application')
                    ->label('Application')
                    ->options($this->applicationOptions())
                    ->inline()
                    ->required(),
            ])
            ->action(function (array $data): void {
                $this->territory = (string) ($data['territory'] ?? 'Global');
                $this->application = (string) ($data['application'] ?? 'Show All');

                session()->put(self::TERRITORY_SESSION_KEY, $this->territory);
                session()->put(self::APPLICATION_SESSION_KEY, $this->application);
            });
    }

    public function getProductProperty(): ?\App\Models\ProductProfile
    {
        return $this->configProfile?->productProfile;
    }

    public function getGroupProperty(): ?\App\Models\CatalogGroup
    {
        return $this->product?->catalogGroup;
    }

    public function getGroupMainImagePathProperty(): ?string
    {
        return $this->group?->mainImage?->file_path;
    }

    /**
     * @return Collection<int, ConfigurationSpecification>
     */
    public function getDimensionsProperty(): Collection
    {
        $configuration = $this->demoConfiguration;

        if ($configuration === null) {
            return collect();
        }

        $configuration->loadMissing('configurationSpecifications');

        return $configuration->configurationSpecifications
            ->filter(fn (ConfigurationSpecification $line): bool => $line->spec_group === 'Dimensions')
            ->sortBy('sort_order')
            ->values();
    }

    /**
     * @return Collection<int, ConfigurationSpecification>
     */
    public function getSpecificationsProperty(): Collection
    {
        $configuration = $this->demoConfiguration;

        if ($configuration === null) {
            return collect();
        }

        $configuration->loadMissing('configurationSpecifications');

        return $configuration->configurationSpecifications
            ->filter(fn (ConfigurationSpecification $line): bool => $line->spec_group === 'Specifications')
            ->sortBy('sort_order')
            ->values();
    }

    /**
     * @return Collection<int, FileAttachment>
     */
    public function getGroupFilesProperty(): Collection
    {
        $group = $this->group;

        if ($group === null) {
            return collect();
        }

        $group->loadMissing('fileAttachments');

        return $this->nonImageFiles($group->fileAttachments);
    }

    /**
     * @return Collection<int, FileAttachment>
     */
    public function getProductFilesProperty(): Collection
    {
        $product = $this->product;

        if ($product === null) {
            return collect();
        }

        $product->loadMissing('fileAttachments');

        return $this->nonImageFiles($product->fileAttachments);
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

    /**
     * @param  Collection<int, FileAttachment>|null  $attachments
     * @return Collection<int, FileAttachment>
     */
    protected function nonImageFiles(?Collection $attachments): Collection
    {
        return ($attachments ?? collect())
            ->filter(fn (FileAttachment $file): bool => ! in_array($file->file_type, [FileAttachmentType::MainImage, FileAttachmentType::GalleryImage], true))
            ->values();
    }
}
