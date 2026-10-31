<?php

namespace App\Filament\Resources;

use App\Filament\Pages\CatalogDisplay;
use App\Models\Group;
use App\Models\GroupTagCategory;
use App\Models\GroupTagValue;
use App\Services\CatalogPresentation;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\Gate;

class CatalogDisplayForm
{
    /** @return list<Section> */
    public static function shared(): array
    {
        $fields = [];
        foreach (app(CatalogPresentation::class)->defaults() as $key => $default) {
            $fields[] = self::field($key);
        }

        return [Section::make(__('catalog.display.shared'))->columns(['default' => 1, 'lg' => 2])->schema($fields)];
    }

    /** @return list<Component> */
    public static function group(): array
    {
        $fields = [Select::make('requested_group_id')->label(__('catalog.display.choose_group'))->searchable()->optionsLimit(50)
            ->getSearchResultsUsing(fn (string $search): array => self::groupOptions($search))
            ->getOptionLabelUsing(function (mixed $value): ?string {
                Gate::authorize('manage-catalog');

                return Group::query()->whereKey($value)->value('name');
            })->live()->dehydrated(false)->afterStateUpdated(fn (CatalogDisplay $livewire, mixed $state) => $livewire->requestGroup($state))];
        foreach (app(CatalogPresentation::class)->overrideKeys() as $key) {
            $product = str_starts_with($key, 'product_');
            $fields[] = Section::make(__('catalog.display.fields.'.$key))->columns(1)
                ->visible(fn (CatalogDisplay $livewire): bool => $livewire->selectedGroupId !== null && (! $product || $livewire->groupIsLeaf))
                ->schema([
                    Toggle::make('use_'.$key)->label(__('catalog.display.override'))->default(false)->live(),
                    self::field($key)->disabled(fn (Get $get): bool => ! $get('use_'.$key)),
                ]);
        }

        return $fields;
    }

    /** @return list<Select> */
    public static function tags(): array
    {
        return [Select::make('value_ids')->label(__('catalog.display.group_tags'))->multiple()->reorderable()->searchable()->optionsLimit(50)
            ->getSearchResultsUsing(fn (string $search): array => self::valueOptions($search))
            ->getOptionLabelsUsing(function (array $values): array {
                Gate::authorize('manage-catalog');

                return GroupTagValue::query()->with('category:id,label')->whereKey($values)->get()->mapWithKeys(fn (GroupTagValue $value): array => [$value->id => $value->category->label.' · '.$value->label])->all();
            })->dehydrateStateUsing(fn (array $state): array => array_map('intval', $state))];
    }

    /** @return list<Component> */
    public static function vocabulary(bool $value): array
    {
        $fields = [self::vocabularyRecord($value)->placeholder(__('catalog.display.create_new'))->live()
            ->afterStateUpdated(function (mixed $state, Set $set) use ($value): void {
                Gate::authorize('manage-catalog');
                $record = $state === null ? null : ($value ? GroupTagValue::query() : GroupTagCategory::query())->findOrFail($state);
                $set('label', $record?->label ?? '');
                $set('sort_order', $record?->sort_order ?? 0);
                if ($value) {
                    $set('group_tag_category_id', $record?->group_tag_category_id);
                }
            }),
            TextInput::make('label')->label(__('catalog.display.tag_label'))->required()->maxLength($value ? 150 : 100),
            TextInput::make('sort_order')->label(__('catalog.display.sort_order'))->integer()->minValue(0)->maxValue(4294967295)->default(0)->required(),
        ];
        if ($value) {
            $fields[] = Select::make('group_tag_category_id')->label(__('catalog.display.category'))->required()->searchable()->optionsLimit(50)
                ->getSearchResultsUsing(fn (string $search): array => self::categoryOptions($search))
                ->getOptionLabelUsing(function (mixed $value): ?string {
                    Gate::authorize('manage-catalog');

                    return GroupTagCategory::query()->whereKey($value)->value('label');
                });
        }

        return $fields;
    }

    public static function vocabularyRecord(bool $forValue): Select
    {
        return Select::make('record_id')->label(__('catalog.display.existing_record'))->searchable()->optionsLimit(50)
            ->getSearchResultsUsing(fn (string $search): array => $forValue ? self::valueOptions($search) : self::categoryOptions($search))
            ->getOptionLabelUsing(function (mixed $value) use ($forValue): ?string {
                Gate::authorize('manage-catalog');

                return ($forValue ? GroupTagValue::query() : GroupTagCategory::query())->whereKey($value)->value('label');
            });
    }

    public static function field(string $key): Select|TextInput|Toggle|ToggleButtons
    {
        $label = __('catalog.display.fields.'.$key);
        if ($key === 'tag_counts') {
            return Toggle::make($key)->label($label);
        }
        if (in_array($key, ['group_cards_per_row', 'tag_columns'], true)) {
            return TextInput::make($key)->label($label)->integer()->required()->minValue($key === 'tag_columns' ? 1 : 2)->maxValue($key === 'tag_columns' ? 3 : 4)
                ->dehydrateStateUsing(fn (mixed $state): int => (int) $state);
        }
        if ($key === 'group_card_content') {
            return Select::make($key)->label($label)->multiple()->reorderable()->searchable()
                ->options(self::labels(['description', 'tags', 'product_count', 'legacy_id']));
        }
        if ($key === 'group_card_tag_categories') {
            return Select::make($key)->label($label)->multiple()->reorderable()->searchable()->optionsLimit(50)
                ->getSearchResultsUsing(fn (string $search): array => self::categoryOptions($search))
                ->getOptionLabelsUsing(function (array $values): array {
                    Gate::authorize('manage-catalog');

                    return GroupTagCategory::query()->whereKey($values)->pluck('label', 'id')->all();
                })->dehydrateStateUsing(fn (array $state): array => array_map('intval', $state));
        }
        $choices = match ($key) {
            'sidebar_mode' => ['tree', 'tags'],
            'group_image_placement', 'product_image_placement' => ['above', 'below', 'left', 'right'],
            'group_image_scale', 'product_image_scale', 'product_page_image_scale' => ['small', 'medium', 'large'],
            'group_card_density', 'tag_density' => ['compact', 'comfortable'],
            'tag_presentation' => ['cards', 'chip_grid', 'chips', 'rows'],
            'branch_tag_retention' => ['keep_available', 'clear'],
            'criteria_container' => ['responsive', 'drawer'],
            'criteria_columns' => ['plain', 'divided'],
            'criteria_choices' => ['checkbox', 'buttons'],
            'product_page_layout' => ['compact_header', 'image_column'],
        };

        return ToggleButtons::make($key)->label($label)->options(self::labels($choices))->inline()->required();
    }

    /** @return array<int,string> */
    public static function groupOptions(string $search): array
    {
        Gate::authorize('manage-catalog');

        return Group::query()->where('name', 'like', '%'.$search.'%')->orderBy('name')->orderBy('id')->limit(50)->pluck('name', 'id')->all();
    }

    /** @return array<int,string> */
    public static function categoryOptions(string $search): array
    {
        Gate::authorize('manage-catalog');

        return GroupTagCategory::query()->where('label', 'like', '%'.$search.'%')->orderBy('sort_order')->orderBy('id')->limit(50)->pluck('label', 'id')->all();
    }

    /** @return array<int,string> */
    public static function valueOptions(string $search): array
    {
        Gate::authorize('manage-catalog');

        return GroupTagValue::query()->with('category:id,label')->where('label', 'like', '%'.$search.'%')->orderBy('sort_order')->orderBy('id')->limit(50)->get()
            ->mapWithKeys(fn (GroupTagValue $value): array => [$value->id => $value->category->label.' · '.$value->label])->all();
    }

    /** @param list<string> $values @return array<string,string> */
    private static function labels(array $values): array
    {
        return array_combine($values, array_map(fn (string $value): string => __('catalog.display.options.'.$value), $values));
    }
}
