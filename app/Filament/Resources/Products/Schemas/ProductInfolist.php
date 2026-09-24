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
            Section::make('Product')->schema([
                TextEntry::make('product_code')->label('Product Code')->copyable(),
                TextEntry::make('product_name')->label('Product Name'),
                TextEntry::make('description')->columnSpanFull(),
                TextEntry::make('group.name')->label('Group'),
                TextEntry::make('legacy_id')->label('Legacy ID'),
                TextEntry::make('legacy_group_id')->label('Legacy Group ID'),
            ])->columns(2)->columnSpanFull(),
            View::make('filament.resources.products.facts')->viewData(fn (Product $record): array => ['product' => $record])->columnSpanFull(),
        ]);
    }
}
