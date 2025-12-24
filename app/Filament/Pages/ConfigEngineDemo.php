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
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Components\Section as SchemaSection;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
//use Filament\Infolists\Infolist;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;

class ConfigEngineDemo extends Page implements HasInfolists, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithInfolists;

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
            'USA' => '🇺🇸 USA',
            'Germany' => '🇩🇪 Germany',
            'Europe' => '🇪🇺 Europe',
            'Russia' => '🇷🇺 Russia',
            'Australia' => '🇦🇺 Australia',
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
            ->modalWidth(Width::TwoExtraLarge)
            ->fillForm(fn (): array => [
                'territory' => $this->territory,
                'application' => $this->application,
            ])
            ->schema([
                ToggleButtons::make('territory')
                    ->label('Territory')
                    ->options($this->territoryOptions())
                    ->icons([
                        'Global' => 'heroicon-o-globe-alt',
                    ])
                    ->colors([
                        'Global' => 'gray',
                        'USA' => 'info',
                        'Germany' => 'warning',
                        'Europe' => 'primary',
                        'Russia' => 'danger',
                        'Australia' => 'success',
                    ])
                    ->inline()
                    ->grouped()
                    ->required(),
                ToggleButtons::make('application')
                    ->label('Application')
                    ->options($this->applicationOptions())
                    ->icons([
                        'Show All' => 'heroicon-o-squares-2x2',
                        'Industry' => 'heroicon-o-building-office-2',
                        'Water Supply' => 'heroicon-o-beaker',
                        'Agriculture' => 'heroicon-o-sun',
                        'Wastewater' => 'heroicon-o-arrow-path-rounded-square',
                    ])
                    ->colors([
                        'Show All' => 'gray',
                        'Industry' => 'primary',
                        'Water Supply' => 'info',
                        'Agriculture' => 'success',
                        'Wastewater' => 'warning',
                    ])
                    ->inline()
                    ->grouped()
                    ->required(),
            ])
            ->action(function (array $data): void {
                $this->territory = (string) ($data['territory'] ?? 'Global');
                $this->application = (string) ($data['application'] ?? 'Show All');

                session()->put(self::TERRITORY_SESSION_KEY, $this->territory);
                session()->put(self::APPLICATION_SESSION_KEY, $this->application);
            });
    }

    public function viewImageAction(): Action
    {
        return Action::make('viewImage')
            ->label('View image')
            ->modalHeading('Image preview')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalContent(fn (array $arguments): string => sprintf(
                '<div class="w-full"><img src="%s" class="w-full h-full object-contain" loading="lazy"  alt=""/></div>',
                e($arguments['url'] ?? '')
            ))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Close'));
    }

    public function getProductProperty(): ?\App\Models\ProductProfile
    {
        return $this->configProfile?->productProfile;
    }

    public function getGroupProperty(): ?\App\Models\CatalogGroup
    {
        return $this->product?->catalogGroup;
    }

    public function getGroupMainImageUrlProperty(): ?string
    {
        return $this->group?->mainImage?->file_path;
    }

    public function getGroupMainImagePathProperty(): ?string
    {
        return $this->groupMainImageUrl;
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

    /**
     * @return Collection<int, FileAttachment>
     */
    public function getConfigurationFilesProperty(): Collection
    {
        $configuration = $this->demoConfiguration;

        if ($configuration === null) {
            return collect();
        }

        $configuration->loadMissing('fileAttachments');

        return $this->nonImageFiles($configuration->fileAttachments);
    }

    public function form(Schema $schema): Schema
    {
        $components = [];

        foreach ($this->stages as $stage) {
            $components[] = ToggleButtons::make("selection.{$stage['id']}")
                ->label($stage['label'])
                ->options(collect($stage['options'])->mapWithKeys(function ($opt) {
                    return [$opt['id'] => "{$opt['label']}"];
                }))
                ->inlineLabel()
                ->grouped()
                ->columns(2)
                ->colors(function () use ($stage): array {
                    return collect($stage['options'])
                        ->mapWithKeys(fn ($opt) => [$opt['id'] => 'primary'])
                        ->all();
                })
                ->live()
                ->afterStateUpdated(function ($state) use ($stage) {
                    $this->selectOption($stage['id'], (int) $state);
                })
                ->disableOptionWhen(function (string $value) use ($stage) {
                    $allowedIds = $this->allowed[$stage['id']] ?? [];
                    return ! in_array((int) $value, $allowedIds);
                });
        }

        return $schema->components([
            SchemaSection::make('Configurator')
                ->components($components)
                ->columns(1),
        ]);
    }

    public function groupInfolist(Schema $schema): Schema
    {
        return $schema
            ->record($this->group)
            ->components([
                SchemaSection::make('Group Image')
                    ->components([
                        TextEntry::make('mainImageUrl')
                            ->hiddenLabel()
                            ->html()
                            ->state(function (?\App\Models\CatalogGroup $record): string {
                                $url = $record?->mainImageUrl;

                                if (! $url) {
                                    return '';
                                }

                                return sprintf(
                                    '<button type="button" wire:click="mountAction(\'viewImage\', { url: \"%s\" })" class="block w-full group">
                                        <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900">
                                            <img src="%s" class="w-full h-full object-cover" loading="lazy" />
                                        </div>
                                        <div class="mt-2 text-xs text-primary-600 group-hover:underline">Enlarge</div>
                                    </button>',
                                    e($url),
                                    e($url)
                                );
                            }),
                    ]),
                SchemaSection::make('Group Files')
                    ->components([
                        TextEntry::make('fileAttachments')
                            ->hiddenLabel()
                            ->html()
                            ->state(function (?\App\Models\CatalogGroup $record): string {
                                $files = $this->nonImageFiles($record?->fileAttachments);

                                if ($files->isEmpty()) {
                                    return '';
                                }

                                return $this->renderFileLinks($files, false);
                            }),
                    ]),
            ]);
    }

    public function productInfolist(Schema $schema): Schema
    {
        return $schema
            ->record($this->product)
            ->components([
                SchemaSection::make('Images')
                    ->components([
                        TextEntry::make('images')
                            ->hiddenLabel()
                            ->html()
                            ->state(function (?\App\Models\ProductProfile $record): string {
                                $group = $record?->catalogGroup;

                                $images = collect([
                                    $group?->mainImage,
                                    $record?->mainImage,
                                    $this->demoConfiguration?->mainImage,
                                ])->filter();

                                $gallery = $group?->galleryImages ?? collect();

                                $urls = $images->concat($gallery)
                                    ->map(fn (FileAttachment $file) => $file->public_url)
                                    ->filter()
                                    ->values();

                                if ($urls->isEmpty()) {
                                    return '';
                                }

                                $items = $urls->map(function (string $url): string {
                                    return sprintf(
                                        '<button type="button" wire:click="mountAction(\'viewImage\', { url: \"%s\" })" class="group block w-full">
                                            <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900">
                                                <img src="%s" class="w-full h-full object-cover" loading="lazy"  alt=""/>
                                            </div>
                                            <div class="mt-1 text-[11px] text-primary-600 group-hover:underline">Enlarge</div>
                                        </button>',
                                        e($url),
                                        e($url)
                                    );
                                })->implode('');

                                return sprintf('<div class="grid grid-cols-1 gap-2">%s</div>', $items);
                            }),
                    ]),
                SchemaSection::make('Documents')
                    ->components([
                        TextEntry::make('documents')
                            ->hiddenLabel()
                            ->html()
                            ->state(function (?\App\Models\ProductProfile $record): string {
                                $groupFiles = $this->nonImageFiles($record?->catalogGroup?->fileAttachments);
                                $productFiles = $this->nonImageFiles($record?->fileAttachments);
                                $configurationFiles = $this->nonImageFiles($this->demoConfiguration?->fileAttachments);

                                $sections = [];

                                if ($groupFiles->isNotEmpty()) {
                                    $sections[] = '<div class="mb-2 text-xs font-bold text-gray-500">GROUP</div>' . $this->renderFileLinks($groupFiles);
                                }

                                if ($productFiles->isNotEmpty()) {
                                    $sections[] = '<div class="mt-4 mb-2 text-xs font-bold text-gray-500">PRODUCT</div>' . $this->renderFileLinks($productFiles);
                                }

                                if ($configurationFiles->isNotEmpty()) {
                                    $sections[] = '<div class="mt-4 mb-2 text-xs font-bold text-gray-500">CONFIGURATION</div>' . $this->renderFileLinks($configurationFiles);
                                }

                                return implode('', $sections);
                            }),
                    ]),
            ]);
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

    /**
     * @param  Collection<int, FileAttachment>  $files
     */
    private function renderFileLinks(Collection $files, bool $withContainer = true): string
    {
        $links = $files->map(function (FileAttachment $file): string {
            return sprintf(
                '<a href="%s" target="_blank" class="flex items-center justify-between p-2 rounded hover:bg-gray-50 dark:hover:bg-gray-800 border border-gray-200 dark:border-gray-700 mb-2 transition"><span class="text-sm font-medium text-gray-700 dark:text-gray-200">%s</span> <span class="text-xs text-primary-600 dark:text-primary-400">Open</span></a>',
                $file->file_path,
                e($file->title)
            );
        })->implode('');

        if (! $withContainer) {
            return $links;
        }

        return sprintf('<div class="space-y-1">%s</div>', $links);
    }
}
