<?php

namespace App\Filament\Resources\Groups\Schemas;

use App\Models\Configurator;
use App\Models\Group;
use App\Models\GroupFilter;
use App\Models\SubGroup;
use App\Services\CatalogDiscovery;
use App\Services\CatalogImportParser;
use App\Services\CatalogPolicy;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group as SchemaGroup;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class GroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Details')->schema([
                TextInput::make('name')->required()->maxLength(255)->helperText('Imported names are replaced by the source name on reimport.'),
                Textarea::make('description')->maxLength(5000)->columnSpanFull(),
                Select::make('parent_id')->label('Parent')->searchable()->placeholder('Root group')
                    ->options(fn (?Group $record): array => self::parentOptions($record)),
                Select::make('configurator_id')->label('Configurator')->searchable()->placeholder('Unassigned')
                    ->getSearchResultsUsing(fn (string $search): array => Configurator::where('name', 'like', '%'.$search.'%')->orderBy('name')->limit(50)->pluck('name', 'id')->all())
                    ->getOptionLabelUsing(fn (mixed $value): ?string => Configurator::find($value)?->name)
                    ->disabled(fn (?Group $record): bool => $record?->children()->exists() ?? false)
                    ->helperText('Leaf groups can share one configurator. Changes to it affect every assigned group.'),
                TextInput::make('sort_order')->label('Sort Order')->integer()->default(0)->required(),
            ])->columns(2)->columnSpanFull(),
            SchemaGroup::make(self::settingsSections())
                ->visible(fn (?Group $record): bool => $record !== null && ! $record->children()->exists())
                ->columnSpanFull(),
        ]);
    }

    /** @return list<Section> */
    private static function settingsSections(): array
    {
        $properties = array_combine(CatalogImportParser::propertyKeys(), array_map(fn (string $key): string => str_replace('_', ' ', $key), CatalogImportParser::propertyKeys()));

        return [
            Section::make('Catalog filters')->description('Only these properties appear as discovery filters. Omitted source values remain available.')->schema([
                Repeater::make('catalog_settings.filters')->label('Filters')->collapsible()->collapsed()->itemLabel(fn (array $state): ?string => $state['label'] ?? null)->defaultItems(0)->maxItems(18)->reorderableWithButtons()->columnSpanFull()->schema([
                    Hidden::make('id'),
                    Select::make('property_key')->label('Product property')->options($properties)->required()->searchable()->live()
                        ->afterStateUpdated(function (Set $set, mixed $state, ?Group $record): void {
                            $set('values', array_map(fn (string $value): array => ['value' => $value, 'label' => ''], self::values($record, $state)));
                        }),
                    TextInput::make('label')->label('Filter label')->required()->maxLength(255),
                    Repeater::make('values')->label('Value order and labels')->defaultItems(0)->addable(false)->reorderableWithButtons()->columnSpanFull()
                        ->helperText('Canonical values are read-only. Remove stale values to repair the draft; omitted current values still appear after your ordered values.')
                        ->schema([
                            TextInput::make('value')->label('Source value')->readOnly()->required(),
                            TextInput::make('label')->label('Display label')->maxLength(255)->placeholder('Use source value'),
                        ])->columns(2),
                ])->columns(2),
            ])->columnSpanFull(),
            Section::make('SubGroups')->description('Named presets select an allowed set for one property. They do not create child Groups or duplicate Products.')->schema([
                Repeater::make('catalog_settings.sub_groups')->label('Presets')->defaultItems(0)->reorderableWithButtons()->schema([
                    Hidden::make('id'),
                    TextInput::make('label')->label('Preset label')->required()->maxLength(255),
                    Select::make('property_key')->label('Product property')->options($properties)->required()->searchable()->live(),
                    Select::make('allowed_values')->label('Allowed source values')->multiple()->required()->minItems(1)->searchable()
                        ->options(fn (Get $get, ?Group $record): array => self::valueOptions($record, $get('property_key')))
                        ->helperText('A property can be used here without adding it as a visible filter.'),
                    Toggle::make('force_hide')->label('Hide this property’s filter')->default(false)
                        ->helperText('One allowed value hides the filter automatically. Multiple values keep it visible unless this is enabled. No effect if the property has no filter.'),
                ])->columns(2)->columnSpanFull(),
            ])->columnSpanFull(),
            Section::make('Results')->schema([
                TextInput::make('catalog_settings.result_settings.default_page_size')->label('Default products per page')->integer()->required()->default(10)->minValue(1)->maxValue(CatalogPolicy::MAX_PAGE_SIZE),
                Toggle::make('catalog_settings.result_settings.allow_page_size_change')->label('Let visitors change page size')->default(false)->live(),
                Repeater::make('catalog_settings.result_settings.page_size_options')->label('Available page sizes')->reorderableWithButtons()
                    ->helperText('Include the default size when visitor changes are enabled. These choices are kept when the visitor control is disabled.')
                    ->schema([TextInput::make('size')->label('Products per page')->integer()->required()->minValue(1)->maxValue(CatalogPolicy::MAX_PAGE_SIZE)])
                    ->default([['size' => 1], ['size' => 2], ['size' => 10]])->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),
        ];
    }

    /** @return list<string> */
    private static function values(?Group $record, mixed $key): array
    {
        if ($record === null || ! is_string($key) || ! in_array($key, CatalogImportParser::propertyKeys(), true)) {
            return [];
        }

        return array_column(app(CatalogDiscovery::class)->vocabulary($record->id, $key), 'value');
    }

    /** @return array<string, string> */
    private static function valueOptions(?Group $record, mixed $key): array
    {
        $values = self::values($record, $key);

        return array_combine($values, $values);
    }

    /** @return array<string, mixed> */
    public static function settingsState(Group $record): array
    {
        $settings = CatalogPolicy::resultSettings($record->result_settings);
        $settings['page_size_options'] = array_map(fn (int $size): array => ['size' => $size], $settings['page_size_options']);

        return [
            'filters' => $record->filters()->orderBy('sort_order')->orderBy('id')->get()->map(function (GroupFilter $filter) use ($record): array {
                $values = array_values(array_unique([...($filter->value_order ?? []), ...self::values($record, $filter->property_key)], SORT_STRING));

                return ['id' => $filter->id, 'property_key' => $filter->property_key, 'label' => $filter->label,
                    'values' => array_map(fn (string $value): array => ['value' => $value, 'label' => $filter->value_labels[$value] ?? ''], $values)];
            })->all(),
            'sub_groups' => $record->subGroups()->orderBy('sort_order')->orderBy('id')->get()->map(fn (SubGroup $preset): array => $preset->only(['id', 'label', 'property_key', 'allowed_values', 'force_hide']))->all(),
            'result_settings' => $settings,
        ];
    }

    /** @return array<int, string> */
    private static function parentOptions(?Group $record): array
    {
        $groups = Group::withCount('products')->orderBy('name')->get(['id', 'parent_id', 'name', 'configurator_id']);
        $excluded = $record ? [$record->id] : [];
        do {
            $before = count($excluded);
            foreach ($groups as $group) {
                if (in_array($group->parent_id, $excluded, true) && ! in_array($group->id, $excluded, true)) {
                    $excluded[] = $group->id;
                }
            }
        } while (count($excluded) !== $before);

        return $groups->reject(fn (Group $group): bool => in_array($group->id, $excluded, true) || $group->products_count > 0 || $group->configurator_id !== null)->pluck('name', 'id')->all();
    }
}
