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
            Tabs::make(__('catalog.products.headings.product_data'))->key(fn (Tabs $component): string => 'product-data::'.(filled($component->getStatePath()) ? $component->getStatePath().'::tabs' : 'tabs'), isInheritable: false)->columnSpanFull()->tabs([
                Tab::make(__('ui.tabs.details'))->key(fn (Tab $component): string => 'details::'.(filled($component->getStatePath()) ? $component->getStatePath().'::tab' : 'tab'), isInheritable: false)->schema([
                    TextInput::make('product_code')->label(__('catalog.products.labels.product_code_2'))->required()->maxLength(64)->trim(false),
                    TextInput::make('product_name')->label(__('catalog.products.labels.product_name_2'))->maxLength(255)->trim(false),
                    Textarea::make('description')->label(__('catalog.fields.description'))->rows(3)->autosize()->maxLength(5000)->columnSpanFull(),
                    TextEntry::make('group.name')->label(__('catalog.resources.groups.singular')),
                    TextEntry::make('legacy_id')->label(__('catalog.fields.legacy_id')),
                    TextEntry::make('legacy_group_id')->label(__('catalog.products.labels.legacy_group_id')),
                ]),
                Tab::make(__('catalog.products.buckets.properties'))->key(fn (Tab $component): string => 'properties::'.(filled($component->getStatePath()) ? $component->getStatePath().'::tab' : 'tab'), isInheritable: false)->schema([self::jsonRepeater('properties', __('catalog.products.buckets.properties'))]),
                Tab::make(__('catalog.products.buckets.parts'))->key(fn (Tab $component): string => 'parts::'.(filled($component->getStatePath()) ? $component->getStatePath().'::tab' : 'tab'), isInheritable: false)->schema([self::jsonRepeater('parts', __('catalog.products.buckets.parts'))]),
                Tab::make(__('catalog.products.buckets.extra_data'))->key(fn (Tab $component): string => 'extra-data::'.(filled($component->getStatePath()) ? $component->getStatePath().'::tab' : 'tab'), isInheritable: false)->schema([self::jsonRepeater('extra_data', __('catalog.products.buckets.extra_data'))]),
            ]),
        ]);
    }

    private static function jsonRepeater(string $bucket, string $label): Repeater
    {
        return Repeater::make($bucket)->label($label)->columnSpanFull()->defaultItems(0)
            ->addable(false)->deletable(false)->reorderable(false)->compact()
            ->extraAttributes(['class' => 'catalog-product-json'])
            ->hintAction(FormHints::make(__('catalog.products.hints.edit_values_of_the_fields_saved_on')))
            ->table([TableColumn::make(__('ui.fields.field'))->width('32%'), TableColumn::make(__('ui.fields.value'))])
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
