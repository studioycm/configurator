<?php

namespace App\Filament\Resources\Configurators\Schemas;

use App\ConfigInputType;
use App\Models\Attribute;
use App\Models\Configurator;
use App\Models\ConfiguratorAttribute;
use App\Models\Option;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Illuminate\Support\Str;

class ConfiguratorAttributeForm
{
    /** @return array<Component> */
    public static function components(Configurator $owner, ?ConfiguratorAttribute $inclusion = null): array
    {
        $labels = [];
        $optionLabels = function (mixed $attributeId) use (&$labels): array {
            if (! is_numeric($attributeId)) {
                return [];
            }

            return $labels[(int) $attributeId] ??= Option::with('value')->where('attribute_id', $attributeId)->orderBy('code')->get()->mapWithKeys(fn (Option $option): array => [$option->id => $option->code.' · '.$option->value->label])->all();
        };

        return [
            View::make('filament.forms.validation-summary'),
            Hidden::make('id')->default(fn (): string => 'new:'.Str::uuid()),
            Section::make('Local inclusion')->description('These settings change this Configurator only. Canonical identity and codes stay in the shared library.')->columns(2)->schema([
                Select::make('attribute_id')->label('Canonical Attribute')->required()->rules(['integer'])->searchable()->live()->disabled($inclusion !== null)->dehydrated()
                    ->options(fn (): array => Attribute::whereNotIn('id', $owner->attributes()->when($inclusion, fn ($query) => $query->whereKeyNot($inclusion->id))->pluck('attribute_id'))->orderBy('label')->get()->mapWithKeys(fn (Attribute $attribute): array => [$attribute->id => $attribute->label.' · '.$attribute->key])->all()),
                Select::make('input_type')->required()->options(ConfigInputType::class)->default('toggle'),
                TextInput::make('label_override')->label('Local label')->maxLength(255)->helperText('Leave empty to use the shared Attribute label.'),
                Textarea::make('help_text')->label('Local help')->maxLength(1000)->rows(3),
            ]),
            Repeater::make('options')->label('Included Options')->minItems(1)->defaultItems(0)->reorderableWithButtons()->live()->columnSpanFull()->columns(2)->schema([
                Hidden::make('id')->default(fn (): string => 'new:'.Str::uuid()),
                Select::make('option_id')->label('Canonical Option')->required()->rules(['integer'])->searchable()->live()
                    ->disabled(fn (Get $get): bool => filled($get('id')) && ! str_starts_with((string) $get('id'), 'new:'))->dehydrated()
                    ->options(fn (Get $get): array => $optionLabels($get('../../attribute_id')))
                    ->afterStateUpdated(function (Get $get, Set $set): void {
                        $rows = array_filter($get('../../options') ?? [], fn (array $row): bool => filled($row['option_id'] ?? null));
                        if (str_starts_with((string) $get('../../id'), 'new:') && blank($get('../../default_configurator_option_id')) && count($rows) === 1) {
                            $set('../../default_configurator_option_id', $get('id'));
                        }
                    }),
                TextInput::make('label_override')->label('Local option label')->maxLength(255),
                TextInput::make('display_value_override')->label('Local display value')->maxLength(255),
                TextInput::make('hint')->label('Local hint')->maxLength(1000),
                Toggle::make('hidden_by_default')->label('Initially hidden')->default(false),
                Toggle::make('disabled_by_default')->label('Initially disabled')->default(false),
            ])->helperText('Reordering does not change the stored default. Repair defaults and rule references before removing an Option.'),
            Select::make('default_configurator_option_id')->label('Stored default')->required()->live()->options(function (Get $get) use ($optionLabels): array {
                $canonical = $optionLabels($get('attribute_id'));
                $options = [];
                foreach ($get('options') ?? [] as $row) {
                    if (isset($row['id'], $row['option_id'])) {
                        $options[(string) $row['id']] = $canonical[$row['option_id']] ?? 'Unavailable Option — repair required';
                    }
                }
                $current = $get('default_configurator_option_id');
                if (is_scalar($current) && (string) $current !== '' && ! isset($options[(string) $current])) {
                    $options[(string) $current] = 'Removed Option — choose a replacement';
                }

                return $options;
            })->helperText('A hidden or disabled runtime default may fall back temporarily. This stored choice changes only when you edit it.'),
        ];
    }
}
