<?php

namespace App\Filament\Resources;

use App\Filament\Pages\CatalogImages;
use App\Models\Group;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Js;

class CatalogImagesTable
{
    public static function configure(Table $table, CatalogImages $page): Table
    {
        $product = $page->imageTarget === 'product.main_image';
        $method = $product ? 'inspectProductImage' : 'inspectGroupImage';
        $inspect = fn (): Action => Action::make($method)->label(__('catalog-images.inspect'))->icon(Heroicon::OutlinedPhoto)
            ->authorize(fn (): bool => CatalogImages::canAccess())
            ->alpineClickHandler(fn (Product|Group $record): string => '$dispatch(\'catalog-images-inspect\', '.Js::from(['method' => $method, 'record' => $record->id, 'context' => $page->imageBrowserContext()])->toHtml().')');
        $columns = $product ? [
            TextColumn::make('product_code')->label(__('catalog-images.code'))->sortable()
                ->formatStateUsing(fn (string $state, Product $record): Action => $inspect()->name($method.'Title')->label($state)->icon(null)->link()->record($record)->livewire($page)),
            TextColumn::make('product_name')->label(__('catalog-images.name'))->toggleable(),
            TextColumn::make('group.name')->label(__('catalog-images.group'))->toggleable(),
            TextColumn::make('image_reference')->label(__('catalog-images.reference'))
                ->state(fn (Product $record): ?string => is_string($record->profile_fields['product_pic'] ?? null) ? $record->profile_fields['product_pic'] : null)->toggleable(isToggledHiddenByDefault: true),
        ] : [
            TextColumn::make('name')->label(__('catalog-images.name'))->sortable()
                ->formatStateUsing(fn (string $state, Group $record): Action => $inspect()->name($method.'Title')->label($state)->icon(null)->link()->record($record)->livewire($page)),
            TextColumn::make('parent.name')->label(__('catalog-images.parent'))->toggleable(),
            TextColumn::make('legacy_id')->label(__('catalog-images.legacy'))->sortable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('sort_order')->label(__('catalog-images.order'))->sortable()->toggleable(isToggledHiddenByDefault: true),
        ];

        return $table->query(fn () => $page->imageResultsQuery()->with($product ? 'group' : 'parent'))
            ->columns([
                ImageColumn::make('saved_image')->label(__('catalog-images.saved_image'))
                    ->state(fn (Product|Group $record): ?string => $page->imagePageDisplays()[(int) $record->main_image_id]['thumbnail_url'] ?? null)
                    ->extraImgAttributes(['style' => 'object-fit:contain', 'alt' => ''])->imageSize(44),
                ...$columns,
                TextColumn::make('image_status')->label(__('catalog-images.status'))
                    ->state(fn (Product|Group $record): string => $record->main_image_id === null ? __('catalog-images.missing') : (isset($page->imagePageDisplays()[(int) $record->main_image_id]) ? __('catalog-images.has') : __('catalog-images.unavailable'))),
            ])->recordActions([$inspect()])->recordAction(null)->recordUrl(null)
            ->selectable()->selectCurrentPageOnly()->trackDeselectedRecords(false)->maxSelectableRecords(5000)
            ->currentSelectionLivewireProperty('selectedTableRecords')
            ->deselectAllRecordsWhenFiltered(false)->searchable(false)
            ->paginated(fn (): array => $page->imagePageOptions())->defaultPaginationPageOption(10)
            ->persistRecordsPerPageInSession(false)->persistColumnsInSession(false)->persistSortInSession(false)
            ->defaultSort($product ? 'product_code' : 'sort_order')->emptyStateHeading(__('catalog-images.empty'));
    }
}
