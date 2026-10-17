<?php

namespace App\Filament\Resources\Groups\Schemas;

use App\Filament\Resources\FormHints;
use App\Filament\Resources\FormOrderControls;
use App\Filament\Resources\Groups\Pages\EditGroup;
use App\Models\Configurator;
use App\Models\Group;
use App\Models\GroupFilter;
use App\Models\SubGroup;
use App\Services\CatalogDiscovery;
use App\Services\CatalogImportParser;
use App\Services\CatalogPolicy;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class GroupForm
{
    public static function configure(Schema $schema): Schema
    {

        $details = [
            TextInput::make('name')->label(__('ui.fields.name'))->required()->maxLength(255)->hintAction(FormHints::make(__('catalog.groups.hints.imported_names_are_replaced_by_the_source'))),
            Textarea::make('description')->label(__('catalog.fields.description'))->maxLength(5000)->columnSpanFull(),
            Select::make('parent_id')->label(__('catalog.groups.fields.parent'))->searchable()->placeholder(__('catalog.groups.help.root_group'))
                ->options(fn (?Group $record): array => self::parentOptions($record)),
            Select::make('configurator_id')->label(__('configurator.resource.singular'))->searchable()->placeholder(__('ui.choices.unassigned'))
                ->getSearchResultsUsing(fn (string $search): array => Configurator::where('name', 'like', '%'.$search.'%')->orderBy('name')->limit(50)->pluck('name', 'id')->all())
                ->getOptionLabelUsing(fn (mixed $value): ?string => Configurator::find($value)?->name)
                ->disabled(fn (?Group $record): bool => $record?->children()->exists() ?? false)
                ->hintAction(FormHints::make(__('catalog.groups.hints.leaf_groups_can_share_one_configurator_changes'))),
            TextInput::make('sort_order')->label(__('catalog.groups.fields.sort_order'))->integer()->default(0)->required(),
        ];
        $record = $schema->getRecord();
        if (! $record instanceof Group || $record->children()->exists()) {
            return $schema->columns(2)->components($details);
        }
        $sections = self::settingsSections();
        $leaf = fn (?Group $record): bool => $record !== null && ! $record->children()->exists();

        return $schema->columns(1)->components([Tabs::make(__('catalog.groups.headings.group_settings'))->key('group-settings')->columnSpanFull()
            ->activeTab(fn (Component $livewire): int => $livewire instanceof EditGroup && $livewire->initialTab === 'form.group-settings.presentation::data::tab' ? 4 : 1)
            ->persistTabInQueryString(fn (Component $livewire): ?string => $livewire instanceof EditGroup && $livewire->isItemDrawerEditor() ? null : 'group-tab')
            ->tabs([
                Tab::make(__('ui.tabs.details'))->key(fn (Tab $component): string => 'details::'.(filled($component->getStatePath()) ? $component->getStatePath().'::tab' : 'tab'), isInheritable: false)->columns(2)->schema($details),
                Tab::make(__('ui.tables.filters'))->key(fn (Tab $component): string => 'filters::'.(filled($component->getStatePath()) ? $component->getStatePath().'::tab' : 'tab'), isInheritable: false)->schema($sections[3]->getDefaultChildComponents())->visible($leaf),
                Tab::make(__('catalog.groups.tabs.presets'))->key(fn (Tab $component): string => 'presets::'.(filled($component->getStatePath()) ? $component->getStatePath().'::tab' : 'tab'), isInheritable: false)->schema($sections[4]->getDefaultChildComponents())->visible($leaf),
                Tab::make(__('catalog.groups.tabs.presentation'))->key(fn (Tab $component): string => 'presentation::'.(filled($component->getStatePath()) ? $component->getStatePath().'::tab' : 'tab'), isInheritable: false)->schema([$sections[0], $sections[1], $sections[2], $sections[5]])->visible($leaf),
            ])]);
    }

    /** @return list<Section> */
    private static function settingsSections(): array
    {
        $properties = array_combine(CatalogImportParser::propertyKeys(), array_map(fn (string $key): string => str_replace('_', ' ', $key), CatalogImportParser::propertyKeys()));

        return [
            Section::make(__('catalog.groups.headings.product_cards'))->key(fn (Section $component): string => 'product-cards::'.(filled($component->getStatePath()) ? $component->getStatePath().'::section' : 'section'), isInheritable: false)->schema([
                Select::make('catalog_settings.result_settings.card_properties')->label(__('catalog.groups.labels.properties_to_display'))->multiple()->searchable()
                    ->options($properties)->default([])->placeholder(__('catalog.groups.help.use_group_filter_properties'))
                    ->hintAction(FormHints::make(__('catalog.groups.hints.leave_empty_to_use_the_groups_filter')))
                    ->columnSpanFull(),
                Select::make('catalog_settings.result_settings.cards_per_row')->label(__('catalog.groups.labels.cards_per_row'))
                    ->options(array_combine(range(1, CatalogPolicy::MAX_CARDS_PER_ROW), range(1, CatalogPolicy::MAX_CARDS_PER_ROW)))
                    ->default(4)->required()->selectablePlaceholder(false)
                    ->hintAction(FormHints::make(__('catalog.groups.hints.desktop_columns_smaller_screens_use_fewer_columns'))),
                TextInput::make('catalog_settings.result_settings.products_debounce_ms')->label(__('catalog.groups.labels.product_update_delay_ms'))
                    ->integer()->minValue(0)->maxValue(2147483600)->step(100)->rules(['multiple_of:100'])->default(0)->required()
                    ->hintAction(FormHints::make(__('catalog.groups.hints.0_requests_products_immediately_use_steps_of'))),
                Select::make('catalog_settings.result_settings.max_results')->label(__('catalog.groups.labels.show_cards_when_results_are_at_most'))
                    ->options(['all' => __('ui.choices.all')] + array_combine(range(1, CatalogPolicy::MAX_RESULT_THRESHOLD), range(1, CatalogPolicy::MAX_RESULT_THRESHOLD)))
                    ->default('all')->required()->selectablePlaceholder(false)
                    ->hintAction(FormHints::make(__('catalog.groups.hints.all_shows_cards_immediately_a_number_shows'))),
            ])->columns(2)->columnSpanFull(),
            Section::make(__('catalog.groups.headings.card_appearance'))->key(fn (Section $component): string => 'card-appearance::'.(filled($component->getStatePath()) ? $component->getStatePath().'::section' : 'section'), isInheritable: false)->schema([
                Toggle::make('catalog_settings.result_settings.card_only_differences')->label(__('catalog.groups.labels.only_differing_properties'))->default(true)
                    ->hintAction(FormHints::make(__('catalog.groups.hints.compare_the_complete_matching_set_and_hide'))),
                Toggle::make('catalog_settings.result_settings.card_show_labels')->label(__('catalog.groups.labels.show_property_labels'))->default(false)
                    ->hintAction(FormHints::make(__('catalog.groups.hints.property_names_remain_accessible_when_their_labels'))),
                Select::make('catalog_settings.result_settings.card_property_layout')->label(__('catalog.groups.labels.label_and_value_layout'))
                    ->options(fn (): array => array_combine(array_keys(CatalogPolicy::CARD_LAYOUTS), array_map(fn (string $key): string => __('catalog.groups.card_layouts.'.$key), array_keys(CatalogPolicy::CARD_LAYOUTS))))->default('inline_space_between')->required()->selectablePlaceholder(false)
                    ->hintAction(FormHints::make(__('catalog.groups.hints.applies_when_labels_are_shown_your_choice'))),
                Select::make('catalog_settings.result_settings.card_property_columns')->label(__('catalog.groups.labels.property_columns'))
                    ->options([1 => '1', 2 => '2'])->default(2)->required()->selectablePlaceholder(false),
            ])->columns(2)->columnSpanFull(),
            Section::make(__('catalog.groups.headings.card_spacing'))->key(fn (Section $component): string => 'card-spacing::'.(filled($component->getStatePath()) ? $component->getStatePath().'::section' : 'section'), isInheritable: false)->schema([
                TextInput::make('catalog_settings.result_settings.card_padding_block')->label(__('catalog.groups.labels.block_padding'))
                    ->integer()->minValue(0)->maxValue(16)->step(1)->suffix('px')->default(4)->required(),
                TextInput::make('catalog_settings.result_settings.card_padding_inline')->label(__('catalog.groups.labels.inline_padding'))
                    ->integer()->minValue(0)->maxValue(20)->step(1)->suffix('px')->default(6)->required(),
            ])->columns(2)->columnSpanFull(),
            Section::make(__('catalog.groups.headings.catalog_filters'))->key(fn (Section $component): string => 'catalog-filters::'.(filled($component->getStatePath()) ? $component->getStatePath().'::section' : 'section'), isInheritable: false)->schema([
                Select::make('filter_properties_to_add')->label(__('catalog.groups.labels.add_filter_properties'))->multiple()->searchable()->options($properties)->dehydrated(false)->live()
                    ->hintAction(FormHints::make(__('catalog.groups.hints.choose_several_properties_to_add_existing_filter')))
                    ->afterStateUpdated(function (Get $get, Set $set, ?Group $record, mixed $state) use ($properties): void {
                        $rows = $get('catalog_settings.filters') ?? [];
                        $existing = array_column($rows, 'property_key');
                        foreach ($state ?? [] as $key) {
                            if (isset($properties[$key]) && ! in_array($key, $existing, true)) {
                                $rows[Str::uuid()->toString()] = ['id' => null, 'property_key' => $key, 'label' => $properties[$key], 'values' => array_map(fn (string $value): array => ['value' => $value, 'label' => ''], self::values($record, $key))];
                                $existing[] = $key;
                            }
                        }
                        if (count($rows) > 18) {
                            throw ValidationException::withMessages(['data.filter_properties_to_add' => __('catalog.groups.validation.a_group_supports_up_to_18_filters')]);
                        }
                        $set('catalog_settings.filters', $rows);
                        $set('filter_properties_to_add', []);
                    })->columnSpanFull(),
                FormOrderControls::make('catalog_settings.filters')->label(__('ui.tables.filters'))->hintAction(FormHints::make(__('catalog.groups.hints.only_these_properties_appear_as_discovery_filters')))->collapsible()->collapsed()->itemLabel(fn (array $state): ?string => $state['label'] ?? null)->defaultItems(0)->maxItems(18)->reorderableWithButtons()->columnSpanFull()->schema([
                    Hidden::make('id'),
                    Select::make('property_key')->label(__('catalog.fields.product_property'))->options($properties)->required()->searchable()->live()
                        ->afterStateUpdated(function (Set $set, mixed $state, ?Group $record): void {
                            $set('values', array_map(fn (string $value): array => ['value' => $value, 'label' => ''], self::values($record, $state)));
                        }),
                    TextInput::make('label')->label(__('catalog.groups.labels.filter_label'))->required()->maxLength(255),
                    FormOrderControls::make('values')->label(__('catalog.groups.labels.value_order_and_labels'))->defaultItems(0)->addable(false)->reorderableWithButtons()->columnSpanFull()
                        ->hintAction(FormHints::make(__('catalog.groups.hints.canonical_values_are_read_only_remove_stale_values')))
                        ->schema([
                            TextInput::make('value')->label(__('catalog.groups.labels.source_value'))->readOnly()->required(),
                            TextInput::make('label')->label(__('catalog.groups.labels.display_label'))->maxLength(255)->placeholder(__('catalog.groups.help.use_source_value')),
                        ])->columns(2),
                ])->columns(2),
            ])->columnSpanFull(),
            Section::make(__('catalog.groups.headings.subgroups'))->key(fn (Section $component): string => 'subgroups::'.(filled($component->getStatePath()) ? $component->getStatePath().'::section' : 'section'), isInheritable: false)->schema([
                FormOrderControls::make('catalog_settings.sub_groups')->label(__('catalog.groups.tabs.presets'))->hintAction(FormHints::make(__('catalog.groups.hints.named_presets_select_an_allowed_set_for')))->defaultItems(0)->reorderableWithButtons()->schema([
                    Hidden::make('id'),
                    TextInput::make('label')->label(__('catalog.groups.labels.preset_label'))->required()->maxLength(255),
                    Select::make('property_key')->label(__('catalog.fields.product_property'))->options($properties)->required()->searchable()->live(),
                    Select::make('allowed_values')->label(__('catalog.groups.labels.allowed_source_values'))->multiple()->required()->minItems(1)->searchable()
                        ->options(fn (Get $get, ?Group $record): array => self::valueOptions($record, $get('property_key')))
                        ->dehydrateStateUsing(fn (array $state): array => array_map('strval', $state))
                        ->hintAction(FormHints::make(__('catalog.groups.hints.one_available_option_hides_this_propertys_filter')))
                        ->columnSpanFull(),
                ])->columns(2)->columnSpanFull(),
            ])->columnSpanFull(),
            Section::make(__('catalog.groups.headings.results'))->key(fn (Section $component): string => 'results::'.(filled($component->getStatePath()) ? $component->getStatePath().'::section' : 'section'), isInheritable: false)->hidden()->schema([
                TextInput::make('catalog_settings.result_settings.default_page_size')->label(__('catalog.groups.labels.default_products_per_page'))->integer()->required()->default(10)->minValue(1)->maxValue(CatalogPolicy::MAX_PAGE_SIZE),
                Toggle::make('catalog_settings.result_settings.allow_page_size_change')->label(__('catalog.groups.labels.let_visitors_change_page_size'))->default(false)->live(),
                FormOrderControls::make('catalog_settings.result_settings.page_size_options')->label(__('catalog.groups.labels.available_page_sizes'))->reorderableWithButtons()
                    ->hintAction(FormHints::make(__('catalog.groups.hints.include_the_default_size_when_visitor_changes')))
                    ->schema([TextInput::make('size')->label(__('catalog.groups.labels.products_per_page'))->integer()->required()->minValue(1)->maxValue(CatalogPolicy::MAX_PAGE_SIZE)])
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
            'sub_groups' => $record->subGroups()->orderBy('sort_order')->orderBy('id')->get()->map(fn (SubGroup $preset): array => $preset->only(['id', 'label', 'property_key', 'allowed_values']))->all(),
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
