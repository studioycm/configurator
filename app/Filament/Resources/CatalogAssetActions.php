<?php

namespace App\Filament\Resources;

use App\Filament\Pages\CatalogImages;
use App\Livewire\Catalog\AssetAssignment;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Illuminate\Support\Js;

class CatalogAssetActions
{
    public static function command(AssetAssignment $page, string $name, string $label, string $handler, string $disabled = 'running || unknown'): Action
    {
        return Action::make($name)->label($label)->authorize(fn (): bool => $page->canReview())
            ->alpineClickHandler($handler)->extraAttributes(['x-bind:disabled' => $disabled]);
    }

    public static function bulk(CatalogImages $page): array
    {
        return array_map(fn (string $operation): BulkAction => BulkAction::make($operation)
            ->label($operation === 'one_image' ? __('catalog-images.assign_one') : __('catalog-images.match_folder'))
            ->authorize(fn (): bool => CatalogImages::canAccess())
            ->visible(fn (): bool => $operation === 'one_image' || $page->imageTarget === 'product.main_image')
            ->fetchSelectedRecords(false)
            ->alpineClickHandler('$dispatch(\'catalog-images-review\', '.Js::from(['operation' => $operation])->toHtml().')'), ['one_image', 'folder_matches']);
    }
}
