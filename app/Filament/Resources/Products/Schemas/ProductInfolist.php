<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Product;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class ProductInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('catalog.resources.products.singular'))->key(fn (Section $component): string => 'product::'.(filled($component->getStatePath()) ? $component->getStatePath().'::section' : 'section'), isInheritable: false)->schema([
                TextEntry::make('product_code')->label(__('catalog.products.labels.product_code'))->copyable(),
                TextEntry::make('product_name')->label(__('catalog.products.labels.product_name')),
                TextEntry::make('description')->label(__('catalog.fields.description'))->columnSpanFull(),
                TextEntry::make('group.name')->label(__('catalog.resources.groups.singular')),
                TextEntry::make('legacy_id')->label(__('catalog.fields.legacy_id')),
                TextEntry::make('legacy_group_id')->label(__('catalog.products.labels.legacy_group_id')),
            ])->columns(2)->columnSpanFull(),
            View::make('filament.resources.products.facts')->viewData(fn (Product $record): array => ['product' => $record])->columnSpanFull(),
        ]);
    }
}
