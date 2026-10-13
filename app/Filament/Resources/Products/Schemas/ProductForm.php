<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Filament\Resources\FormHints;
use App\Models\Product;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Tabs::make('Product data')->columnSpanFull()->tabs([
                Tab::make('Details')->schema([
                    TextInput::make('product_code')->label('Product code')->required()->maxLength(64)->trim(false),
                    TextInput::make('product_name')->label('Product name')->maxLength(255)->trim(false),
                    Textarea::make('description')->rows(3)->autosize()->maxLength(5000)->columnSpanFull(),
                    TextEntry::make('group.name')->label('Group'),
                    TextEntry::make('legacy_id')->label('Legacy ID'),
                    TextEntry::make('legacy_group_id')->label('Legacy Group ID'),
                ]),
                Tab::make('Properties')->schema([self::jsonRepeater('properties', 'Properties')]),
                Tab::make('Parts')->schema([self::jsonRepeater('parts', 'Parts')]),
                Tab::make('Extra data')->schema([self::jsonRepeater('extra_data', 'Extra data')]),
            ]),
        ]);
    }

    private static function jsonRepeater(string $bucket, string $label): Repeater
    {
        return Repeater::make($bucket)->label($label)->columnSpanFull()->defaultItems(0)
            ->addable(false)->deletable(false)->reorderable(false)->compact()
            ->extraAttributes(['class' => 'catalog-product-json'])
            ->hintAction(FormHints::make('Edit values of the fields saved on this Product. Keys stay fixed; clearing a text value keeps its key. Non-text source values retain their original type and are read-only.'))
            ->table([TableColumn::make('Field')->width('32%'), TableColumn::make('Value')])
            ->schema([
                Hidden::make('key')->dehydrateStateUsing(fn (mixed $state): string => (string) $state),
                Text::make(fn (Get $get): string => (string) $get('key')),
                TextInput::make('value')->hiddenLabel()->trim(false)
                    ->extraInputAttributes(fn (Get $get): array => ['aria-label' => (string) $get('key')])
                    ->disabled(fn (Get $get, ?Product $record): bool => $record !== null && ! is_string($record->{$bucket}[$get('key')] ?? null) && ($record->{$bucket}[$get('key')] ?? null) !== null)
                    ->dehydrated(),
            ]);
    }
}
