<?php

namespace App\Filament\Pages;

use App\DTO\ConfigOptionDTO;
use App\DTO\ConfigStageDTO;
use App\FileAttachmentType;
use App\Models\CatalogGroup;
use App\Models\ConfigProfile;
use App\Models\ConfigurationSpecification;
use App\Models\FileAttachment;
use App\Models\ProductConfiguration;
use App\Models\ProductProfile;
use App\Services\ConfiguratorEngine;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section as SchemaSection;
// use Filament\Infolists\Infolist;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

class ConfigEngineDemo extends Page implements HasInfolists, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithInfolists;
    use InteractsWithSchemas;

    protected static ?string $slug = 'config-engine-demo';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsVertical;

    protected string $view = 'filament.pages.config-engine-demo';

    public ?ConfigProfile $configProfile = null;

    public ?ProductConfiguration $demoConfiguration = null;

    public string $territory = 'Global';

    public string $application = 'Show All';

    /** @var array<string, mixed> */
    public array $context = [];

    /** @var array<int, array<string, mixed>> */
    public array $stages = [];

    /** @var array<int, int> attribute_id => option_id */
    public array $selection = [];

    /** @var array<int, array<int>> attribute_id => [allowed_option_ids...] */
    public array $allowed = [];

    /** @var array<int, array<int>> */
    public array $hiddenOptionsByAttribute = [];

    /** @var array<int, array<int>> */
    public array $disabledOptionsByAttribute = [];

    /** @var array<string, string> */
    public array $labelOverridesByOption = [];

    /** @var array<string, string> */
    public array $hintOverridesByOption = [];

    protected ?ConfiguratorEngine $engine = null;

    private const TERRITORY_SESSION_KEY = 'config_engine_demo.territory';

    private const APPLICATION_SESSION_KEY = 'config_engine_demo.application';

    private const CONTEXT_SESSION_PREFIX = 'config_engine_demo.context.';

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

        $this->seedContextFromSession();
        $this->stages = $this->buildStageData();

        $stageDTOs = $this->stageDTOsFromData();
        $this->selection = $this->engine->defaultSelection($stageDTOs);
        $this->refreshConfiguratorState();
    }

    public function editContextAction(): Action
    {
        return Action::make('editContext')
            ->label('Change')
            ->visible(true)
            ->modalHeading('Configurator Context')
            ->modalSubmitActionLabel('Apply')
            ->modalWidth(Width::TwoExtraLarge)
            ->fillForm(fn (): array => $this->context)
            ->schema($this->contextSchemaComponents())
            ->action(function (array $data): void {
                $this->context = $data;

                $this->persistContextToSession();
                $this->syncLegacyContextProperties();
                $this->refreshConfiguratorState();
            });
    }

    public function viewImageAction(): Action
    {
        return Action::make('viewImage')
            ->label('View image')
            ->visible(true)
            ->modalHeading('Image preview')
            ->modalWidth(Width::FiveExtraLarge)
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalContent(fn (array $arguments): View => view(
                'filament.pages.config-engine-demo.partials.modal-image',
                ['url' => (string) ($arguments['url'] ?? '')],
            ))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Close'));
    }

    public function getProductProperty(): ?ProductProfile
    {
        return $this->configProfile?->productProfile;
    }

    public function getGroupProperty(): ?CatalogGroup
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
            if (($stage['input_mode'] ?? 'toggle') === 'select') {
                $components[] = Select::make("selection.{$stage['id']}")
                    ->label($stage['label'])
                    ->options(fn (): array => $this->optionLabelsForStage((int) $stage['id']))
                    ->hintIconTooltip(fn (): ?string => $this->stageHelperTextById((int) $stage['id']))
                    ->hintIcon('heroicon-o-information-circle')
                    ->inlineLabel()
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(function ($state) use ($stage): void {
                        if ($state === null || $state === '') {
                            return;
                        }

                        $this->selectOption($stage['id'], (int) $state);
                    })
                    ->disableOptionWhen(fn (string $value): bool => $this->optionIsDisabled((int) $stage['id'], (int) $value));

                continue;
            }

            $components[] = ToggleButtons::make("selection.{$stage['id']}")
                ->label($stage['label'])
                ->options(fn (): array => $this->optionLabelsForStage((int) $stage['id']))
                ->hintIconTooltip(fn (): ?string => $this->stageHelperTextById((int) $stage['id']))
                ->hintIcon('heroicon-o-information-circle')
                ->inlineLabel()
                ->grouped()
                ->columns(2)
                ->colors(fn (): array => $this->optionColorsForStage((int) $stage['id']))
                ->extraFieldWrapperAttributes(['class' => 'configurator-toggle-buttons'])
                ->live()
                ->afterStateUpdated(function ($state) use ($stage): void {
                    if ($state === null || $state === '') {
                        return;
                    }

                    $this->selectOption($stage['id'], (int) $state);
                })
                ->disableOptionWhen(fn (string $value): bool => $this->optionIsDisabled((int) $stage['id'], (int) $value));
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
                            ->state(function (?CatalogGroup $record): Htmlable|string {
                                $url = $record?->mainImageUrl;

                                if (! $url) {
                                    return '';
                                }

                                return $this->renderHtmlView('filament.pages.config-engine-demo.partials.image-trigger', [
                                    'url' => $url,
                                    'alt' => 'Main Image',
                                    'captionClass' => 'mt-2 text-xs text-primary-600 group-hover:underline',
                                ]);
                            }),
                    ]),
                SchemaSection::make('Group Files')
                    ->components([
                        TextEntry::make('fileAttachments')
                            ->hiddenLabel()
                            ->html()
                            ->state(function (?CatalogGroup $record): string {
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
                SchemaSection::make('Documents')
                    ->components([
                        TextEntry::make('documents')
                            ->hiddenLabel()
                            ->html()
                            ->state(function (?ProductProfile $record): string {
                                $groupFiles = $this->nonImageFiles($record?->catalogGroup?->fileAttachments);
                                $productFiles = $this->nonImageFiles($record?->fileAttachments);
                                $configurationFiles = $this->nonImageFiles($this->demoConfiguration?->fileAttachments);

                                $sections = [];

                                if ($groupFiles->isNotEmpty()) {
                                    $sections[] = '<div class="mb-2 text-xs font-bold text-gray-500">GROUP</div>'.$this->renderFileLinks($groupFiles);
                                }

                                if ($productFiles->isNotEmpty()) {
                                    $sections[] = '<div class="mt-4 mb-2 text-xs font-bold text-gray-500">PRODUCT</div>'.$this->renderFileLinks($productFiles);
                                }

                                if ($configurationFiles->isNotEmpty()) {
                                    $sections[] = '<div class="mt-4 mb-2 text-xs font-bold text-gray-500">CONFIGURATION</div>'.$this->renderFileLinks($configurationFiles);
                                }

                                return implode('', $sections);
                            }),
                    ]),
                SchemaSection::make('Images')
                    ->components([
                        TextEntry::make('images')
                            ->hiddenLabel()
                            ->html()
                            ->state(function (?ProductProfile $record): Htmlable|string {
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

                                return $this->renderHtmlView('filament.pages.config-engine-demo.partials.image-grid', [
                                    'urls' => $urls,
                                ]);
                            }),
                    ]),
            ]);
    }

    public function selectOption(int $attributeId, int $optionId): void
    {
        $this->selection[$attributeId] = $optionId;

        $this->refreshConfiguratorState();
    }

    protected function refreshConfiguratorState(): void
    {
        $stageDTOs = $this->stageDTOsFromData();

        $evaluation = $this->engine->evaluateState(
            $this->configProfile,
            $stageDTOs,
            $this->selection,
            $this->context,
        );

        $this->allowed = $evaluation['allowed'];
        $this->hiddenOptionsByAttribute = $evaluation['hidden'];
        $this->disabledOptionsByAttribute = $evaluation['disabled'];
        $this->labelOverridesByOption = $evaluation['label_overrides'];
        $this->hintOverridesByOption = $evaluation['hints'];

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

        $evaluation = $this->engine->evaluateState(
            $this->configProfile,
            $stageDTOs,
            $this->selection,
            $this->context,
        );

        $this->allowed = $evaluation['allowed'];
        $this->hiddenOptionsByAttribute = $evaluation['hidden'];
        $this->disabledOptionsByAttribute = $evaluation['disabled'];
        $this->labelOverridesByOption = $evaluation['label_overrides'];
        $this->hintOverridesByOption = $evaluation['hints'];
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
     * @return array<int, array<string, mixed>>
     */
    protected function buildStageData(): array
    {
        return $this->configProfile?->attributes
            ->map(function ($attribute): array {
                return [
                    'id' => (int) $attribute->id,
                    'slug' => $attribute->slug,
                    'label' => (string) ($attribute->label ?? $attribute->name),
                    'sort_order' => (int) $attribute->sort_order,
                    'segment_index' => $attribute->segment_index,
                    'is_required' => (bool) $attribute->is_required,
                    'input_mode' => $attribute->presentationMode(),
                    'help_text' => $attribute->helpText(),
                    'options' => $attribute->options
                        ->map(fn ($option): array => [
                            'id' => (int) $option->id,
                            'label' => (string) $option->label,
                            'code' => $option->code,
                            'sort_order' => (int) $option->sort_order,
                            'is_default' => (bool) $option->is_default,
                            'is_active' => (bool) $option->is_active,
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all() ?? [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function contextSchemaDefinition(): array
    {
        $schema = $this->configProfile?->contextSchema() ?? [];

        return $schema !== [] ? $schema : [
            [
                'key' => 'territory',
                'label' => 'Territory',
                'type' => 'select',
                'required' => true,
                'default' => 'Global',
                'options' => $this->territoryOptions(),
            ],
            [
                'key' => 'application',
                'label' => 'Application',
                'type' => 'select',
                'required' => true,
                'default' => 'Show All',
                'options' => $this->applicationOptions(),
            ],
        ];
    }

    protected function seedContextFromSession(): void
    {
        $context = [];

        foreach ($this->contextSchemaDefinition() as $field) {
            $key = (string) ($field['key'] ?? '');

            if ($key === '') {
                continue;
            }

            $legacyValue = match ($key) {
                'territory' => session()->get(self::TERRITORY_SESSION_KEY),
                'application' => session()->get(self::APPLICATION_SESSION_KEY),
                default => null,
            };

            $context[$key] = session()->get(
                $this->contextSessionKey($key),
                $legacyValue ?? $field['default'] ?? null,
            );
        }

        $this->context = $context;
        $this->syncLegacyContextProperties();
    }

    protected function persistContextToSession(): void
    {
        foreach ($this->contextSchemaDefinition() as $field) {
            $key = (string) ($field['key'] ?? '');

            if ($key === '') {
                continue;
            }

            session()->put($this->contextSessionKey($key), $this->context[$key] ?? null);
        }

        session()->put(self::TERRITORY_SESSION_KEY, $this->territory);
        session()->put(self::APPLICATION_SESSION_KEY, $this->application);
    }

    protected function syncLegacyContextProperties(): void
    {
        $this->territory = (string) ($this->context['territory'] ?? 'Global');
        $this->application = (string) ($this->context['application'] ?? 'Show All');
    }

    protected function contextSessionKey(string $key): string
    {
        return self::CONTEXT_SESSION_PREFIX.$key;
    }

    protected function contextSchemaComponents(): array
    {
        return collect($this->contextSchemaDefinition())
            ->map(function (array $field) {
                $key = (string) ($field['key'] ?? '');
                $label = (string) ($field['label'] ?? str($key)->headline());
                $options = is_array($field['options'] ?? null) ? $field['options'] : [];
                $required = (bool) ($field['required'] ?? false);

                if ($options === []) {
                    return Select::make($key)
                        ->label($label)
                        ->native(false)
                        ->required($required);
                }

                return ToggleButtons::make($key)
                    ->label($label)
                    ->options($options)
                    ->icons($this->contextIconsFor($key))
                    ->colors($this->contextColorsFor($key))
                    ->inline()
                    ->grouped()
                    ->required($required);
            })
            ->values()
            ->all();
    }

    protected function contextIconsFor(string $key): array
    {
        return match ($key) {
            'territory' => [
                'Global' => Heroicon::OutlinedGlobeAlt,
            ],
            'application' => [
                'Show All' => Heroicon::OutlinedSquares2x2,
                'Industry' => Heroicon::OutlinedBuildingOffice2,
                'Water Supply' => Heroicon::OutlinedBeaker,
                'Agriculture' => Heroicon::OutlinedSun,
                'Wastewater' => Heroicon::OutlinedArrowPathRoundedSquare,
            ],
            default => [],
        };
    }

    protected function contextColorsFor(string $key): array
    {
        return match ($key) {
            'territory' => [
                'Global' => 'gray',
                'USA' => 'info',
                'Germany' => 'warning',
                'Europe' => 'primary',
                'Russia' => 'danger',
                'Australia' => 'success',
            ],
            'application' => [
                'Show All' => 'gray',
                'Industry' => 'primary',
                'Water Supply' => 'info',
                'Agriculture' => 'success',
                'Wastewater' => 'warning',
            ],
            default => [],
        };
    }

    /**
     * @return array<string, mixed>|null
     * @return Collection<int, array<string, mixed>>
     */
    protected function stageById(int $stageId): ?array
    {
        /** @var array<string, mixed>|null $stage */
        $stage = collect($this->stages)
            ->first(fn (array $candidate): bool => (int) ($candidate['id'] ?? 0) === $stageId);

        return $stage;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function visibleOptionsForStage(int $stageId): Collection
    {
        $stage = $this->stageById($stageId);

        if ($stage === null) {
            return collect();
        }

        $hiddenIds = $this->hiddenOptionsByAttribute[(int) $stage['id']] ?? [];

        return collect($stage['options'])
            ->reject(fn (array $option): bool => in_array((int) $option['id'], $hiddenIds, true))
            ->values();
    }

    /**
     * @return array<int, string>
     */
    protected function optionLabelsForStage(int $stageId): array
    {
        return $this->visibleOptionsForStage($stageId)
            ->mapWithKeys(fn (array $option): array => [(int) $option['id'] => $this->optionLabel($option)])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected function optionColorsForStage(int $stageId): array
    {
        return $this->visibleOptionsForStage($stageId)
            ->mapWithKeys(function (array $option) use ($stageId): array {
                $optionId = (int) $option['id'];

                return [$optionId => $this->optionIsDisabled($stageId, $optionId) ? 'danger' : 'primary'];
            })
            ->all();
    }

    protected function optionIsDisabled(int $stageId, int $optionId): bool
    {
        $allowedIds = $this->allowed[$stageId] ?? [];

        return ! in_array($optionId, $allowedIds, true);
    }

    /**
     * @param  array<string, mixed>  $option
     */
    protected function optionLabel(array $option): string
    {
        return $this->labelOverridesByOption[(string) $option['id']] ?? (string) $option['label'];
    }

    /**
     * @param  array<string, mixed>  $stage
     */
    protected function stageHelperText(array $stage): ?string
    {
        $selectedOptionId = $this->selection[(int) $stage['id']] ?? null;
        $selectedHint = $selectedOptionId !== null
            ? ($this->hintOverridesByOption[(string) $selectedOptionId] ?? null)
            : null;

        $parts = array_values(array_filter([
            $stage['help_text'] ?? null,
            $selectedHint,
        ]));

        return $parts === [] ? null : implode(' — ', $parts);
    }

    protected function stageHelperTextById(int $stageId): ?string
    {
        $stage = $this->stageById($stageId);

        if ($stage === null) {
            return null;
        }

        return $this->stageHelperText($stage);
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
        return view('filament.pages.config-engine-demo.partials.file-links', [
            'files' => $files,
            'withContainer' => $withContainer,
        ])->render();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function renderHtmlView(string $view, array $data = []): Htmlable
    {
        return new HtmlString(view($view, $data)->render());
    }
}
