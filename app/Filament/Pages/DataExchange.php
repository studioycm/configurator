<?php

namespace App\Filament\Pages;

use App\Filament\CatalogDataActions;
use App\Filament\Exports\AttributeExporter;
use App\Filament\Exports\AttributeValueExporter;
use App\Filament\Exports\ValueExporter;
use App\Filament\Imports\AttributeImporter;
use App\Filament\Imports\AttributeValueImporter;
use App\Filament\Imports\ValueImporter;
use App\Filament\Resources\Attributes\AttributeResource;
use App\Filament\Resources\Options\OptionResource;
use App\Filament\Resources\Values\ValueResource;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

class DataExchange extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPathRoundedSquare;

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.data-exchange';

    public function getTitle(): string
    {
        return __('data_exchange.title');
    }

    public function getSubheading(): ?string
    {
        return __('data_exchange.help.order');
    }

    public static function getNavigationLabel(): string
    {
        return __('data_exchange.title');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.product_configuration');
    }

    public static function canAccess(): bool
    {
        return Gate::allows('manage-catalog');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('catalog.resources.master_values.plural'))->description(__('data_exchange.help.values'))->schema([
                Actions::make([
                    CatalogDataActions::import('importValues', ValueImporter::class),
                    CatalogDataActions::export('exportValues', ValueExporter::class, all: true),
                    Action::make('manageValues')->label(__('data_exchange.actions.manage'))->url(ValueResource::getUrl()),
                ]),
            ]),
            Section::make(__('catalog.resources.attributes.plural'))->description(__('data_exchange.help.attributes'))->schema([
                Actions::make([
                    CatalogDataActions::import('importAttributes', AttributeImporter::class),
                    CatalogDataActions::export('exportAttributes', AttributeExporter::class, all: true),
                    Action::make('manageAttributes')->label(__('data_exchange.actions.manage'))->url(AttributeResource::getUrl()),
                ]),
            ]),
            Section::make(__('data_exchange.attribute_values'))->description(__('data_exchange.help.attribute_values'))->schema([
                Actions::make([
                    CatalogDataActions::import('importAttributeValues', AttributeValueImporter::class),
                    CatalogDataActions::export('exportAttributeValues', AttributeValueExporter::class, all: true),
                    Action::make('manageAttributeValues')->label(__('data_exchange.actions.manage'))->url(OptionResource::getUrl()),
                ]),
            ]),
        ]);
    }
}
