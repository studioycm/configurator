<?php

namespace App\Filament\Pages;

use App\Filament\Resources\CatalogImagesTable;
use App\Filament\Resources\HasWorkspaceTable;
use App\Filament\Resources\InteractsWithCatalogImageBrowsing;
use App\Filament\Resources\InteractsWithCatalogImageEditing;
use App\Filament\Resources\InteractsWithCatalogImageReview;
use App\Models\Group;
use App\Models\Product;
use App\Services\CatalogAssetDisplay;
use App\Services\CatalogAssetRecordSearch;
use App\Services\CatalogAssetRecordSequence;
use App\Services\CatalogAssetTargets;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;

class CatalogImages extends Page implements HasTable, HasWorkspaceTable
{
    use InteractsWithCatalogImageBrowsing, InteractsWithTable {
        InteractsWithCatalogImageBrowsing::getTableRecordsPerPage insteadof InteractsWithTable;
        InteractsWithCatalogImageBrowsing::getTablePerPageSessionKey insteadof InteractsWithTable;
        InteractsWithCatalogImageBrowsing::getAllSelectableTableRecordKeys insteadof InteractsWithTable;
        InteractsWithCatalogImageBrowsing::updatedTableSort insteadof InteractsWithTable;
        InteractsWithCatalogImageBrowsing::sortTable insteadof InteractsWithTable;
    }
    use InteractsWithCatalogImageEditing;
    use InteractsWithCatalogImageReview;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $slug = 'catalog-images';

    protected string $view = 'filament.pages.catalog-images';

    protected static ?int $navigationSort = 30;

    #[Locked]
    public string $imageTarget = 'product.main_image';

    #[Locked]
    public array $imageOwnerContext = [];

    #[Locked]
    public ?int $imageRecordId = null;

    #[Locked]
    public string $imageNavigationMode = 'results';

    #[Locked]
    public int $imageBrowserRevision = 0;

    #[Locked]
    public ?array $pendingCodePaste = null;

    #[Locked]
    public ?array $exactCodeSet = null;

    #[Locked]
    public int $codePasteRevision = 0;

    public array $imageSearch = [];

    public string $imageBrowserNotice = '';

    protected array $imageDisplayCache = [];

    public static function canAccess(): bool
    {
        return (bool) config('catalog-assets.enabled') && Gate::allows('manage-catalog');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.catalog');
    }

    public static function getNavigationLabel(): string
    {
        return __('catalog-images.title');
    }

    public function getTitle(): string
    {
        return __('catalog-images.title');
    }

    public function mount(): void
    {
        $this->authorizeImageBrowser();
        $this->imageSearch = $this->searchDefaults();
    }

    public function hydrate(): void
    {
        $this->authorizeImageBrowser();
    }

    protected function authorizeImageBrowser(): void
    {
        abort_unless((bool) config('catalog-assets.enabled'), 403);
        Gate::authorize('manage-catalog');
    }

    public function table(Table $table): Table
    {
        return CatalogImagesTable::configure($table, $this);
    }

    protected function getForms(): array
    {
        return ['imageFilters', 'form'];
    }

    public function imageFilters(Schema $schema): Schema
    {
        return $schema->statePath('imageSearch')->components([
            ToggleButtons::make('image_state')->label(__('catalog-images.filter_status'))
                ->options(['any' => __('catalog-images.any'), 'missing' => __('catalog-images.missing'), 'has' => __('catalog-images.has')])->inline()->live(),
            Select::make('group_ids')->label(__('catalog-images.groups'))->multiple()->searchable()->live()
                ->visible(fn (): bool => $this->imageTarget === 'product.main_image')
                ->getSearchResultsUsing(function (string $search): array {
                    $this->authorizeImageBrowser();
                    if (mb_strlen($search) > 100) {
                        return [];
                    }

                    return Group::query()->whereRaw('LOWER(name) LIKE ? ESCAPE \'!\'', ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($search)).'%'])
                        ->orderBy('name')->orderBy('id')->limit(30)->pluck('name', 'id')->all();
                })
                ->getOptionLabelsUsing(function (array $values): array {
                    $this->authorizeImageBrowser();

                    return Group::query()->whereIn('id', array_slice($values, 0, 5000))->pluck('name', 'id')->all();
                }),
        ]);
    }

    public function imageEntity(): string
    {
        return $this->imageTarget === 'product.main_image' ? 'product' : 'group';
    }

    public function imageEligibleQuery(): Builder
    {
        $this->authorizeImageBrowser();

        return app(CatalogAssetTargets::class)->eligibleQuery(auth()->user(), $this->imageTarget, $this->imageOwnerContext);
    }

    public function imageResultsQuery(): Builder
    {
        $recipe = app(CatalogAssetRecordSearch::class)->validate($this->imageSearch, $this->imageEntity());
        if ($this->exactCodeSet !== null) {
            $codes = $this->currentExactCodes();
            if ($codes === []) {
                return $this->imageEligibleQuery()->whereRaw('1 = 0');
            }
            $recipe['codes'] = $codes;
        }
        $query = app(CatalogAssetRecordSearch::class)->apply($this->imageEligibleQuery(), $recipe, $this->imageEntity());

        return $recipe['checked_only'] ? $query->whereKey($this->eligibleImageChecks()) : $query;
    }

    protected function applySortingToTableQuery(Builder $query): Builder
    {
        return app(CatalogAssetRecordSequence::class)->ordered($query, $this->imageSort());
    }

    public function imageSort(): array
    {
        return app(CatalogAssetRecordSearch::class)->sortDescriptor($this->imageEntity(), $this->tableSort);
    }

    public function imageInspector(): ?array
    {
        if ($this->imageRecordId === null) {
            return null;
        }
        $record = $this->imageEligibleQuery()->with($this->imageEntity() === 'product' ? 'group' : 'parent')->find($this->imageRecordId);
        if ($record === null) {
            return ['missing_record' => true];
        }
        $image = $record->main_image_id === null ? null : (app(CatalogAssetDisplay::class)->forIds([(int) $record->main_image_id])[(int) $record->main_image_id] ?? null);
        $sequence = $this->imageSequenceQuery();

        return ['missing_record' => false, 'id' => $record->id, 'title' => $record instanceof Product ? $record->product_code : $record->name,
            'description' => $record instanceof Product ? $record->product_name : $record->parent?->name,
            'reference' => $record instanceof Product ? (is_string($record->profile_fields['product_pic'] ?? null) ? $record->profile_fields['product_pic'] : null) : null,
            'image' => $image, 'unavailable' => $record->main_image_id !== null && $image === null,
            'position' => app(CatalogAssetRecordSequence::class)->position($sequence, $record->id, $this->imageSort(), (int) $this->getTableRecordsPerPage())];
    }

    public function imagePageDisplays(): array
    {
        if ($this->imageDisplayCache === []) {
            $ids = $this->getTableRecords()->pluck('main_image_id')->filter()->map(fn ($id): int => (int) $id)->unique()->values()->all();
            $this->imageDisplayCache = ['images' => app(CatalogAssetDisplay::class)->forIds($ids)];
        }

        return $this->imageDisplayCache['images'];
    }

    public function tableHostIdentity(): array
    {
        return ['owner' => 'catalog-images', 'widthVariant' => $this->imageEntity(), 'profileVariant' => $this->imageEntity().':selection:v1'];
    }

    public function tableProfileControlNames(): array
    {
        return [];
    }

    public function tableProfileColumnMigration(): ?array
    {
        return null;
    }

    public function tableQuickFilterNames(): array
    {
        return [];
    }

    public function scopedSearchColumns(): array
    {
        return app(CatalogAssetRecordSearch::class)->targets($this->imageEntity());
    }
}
