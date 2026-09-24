<?php

namespace App\Filament\Resources\Configurators\Schemas;

use App\ConditionOperator;
use App\ConditionSource;
use App\Filament\Forms\Components\MappingSetsField;
use App\Models\Configurator;
use App\RuleEffectKind;
use App\Services\CatalogImportParser;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Illuminate\Support\Str;

class ConfiguratorRuleForm
{
    /** @return array<Component> */
    public static function components(Configurator $owner, string $kind): array
    {
        $attributes = [];
        $options = [];
        foreach ($owner->attributes()->with(['attribute', 'options.option.value'])->orderBy('display_order')->get() as $attribute) {
            $attributes[$attribute->id] = ($attribute->label_override ?? $attribute->attribute->label).' · '.$attribute->attribute->key;
            foreach ($attribute->options->sortBy('display_order') as $option) {
                $options[$attribute->id][$option->id] = $option->option->code.' · '.($option->label_override ?? $option->option->value->label);
            }
        }
        $predicate = fn (): array => self::predicate($attributes, $options, $owner->context_schema ?? []);
        $schema = [
            View::make('filament.forms.validation-summary'),
            Hidden::make('id')->default(fn (): string => 'new:'.Str::uuid()),
            Hidden::make('kind')->default($kind), Hidden::make('priority')->default(0),
            Section::make($kind === 'Mapping' ? 'Allowed-option mapping' : 'Advanced rule')->columns(2)->schema([
                TextInput::make('label')->required()->maxLength(255),
                Toggle::make('is_active')->label('Enabled')->default(true),
            ]),
            Builder::make('condition_blocks')->label('All conditions')->helperText('An empty root is unconditional. Groups allow one level of All or Any predicates.')
                ->blocks([
                    Block::make('predicate')->label('Condition')->schema($predicate())->columns(2),
                    Block::make('group')->label('All / Any group')->schema([
                        Hidden::make('id')->default(fn (): string => 'new:'.Str::uuid()),
                        Select::make('operator')->label('Match')->options(['All' => 'All', 'Any' => 'Any'])->required()->default('All'),
                        Repeater::make('conditions')->schema($predicate())->minItems(1)->defaultItems(1)->columns(2)->columnSpanFull(),
                    ]),
                ])->default([])->columnSpanFull(),
        ];
        if ($kind === 'Mapping') {
            $schema[] = Section::make('Driver and target')->description('Changing either Attribute preserves existing set references. Remove or replace incompatible references explicitly.')->columns(2)->schema([
                Select::make('driver_configurator_attribute_id')->label('Driver Attribute')->options($attributes)->required()->searchable()->live(),
                Select::make('target_configurator_attribute_id')->label('Target Attribute')->options($attributes)->required()->searchable()->live(),
            ]);
            $schema[] = MappingSetsField::make('sets')->label('Allowed-option sets')->required()->default([])->columnSpanFull()
                ->sourceChoices(fn (Get $get): array => $options[$get('driver_configurator_attribute_id')] ?? [])
                ->targetChoices(fn (Get $get): array => $options[$get('target_configurator_attribute_id')] ?? []);
            $schema[] = Hidden::make('effects')->default([]);
        } else {
            $schema[] = Hidden::make('driver_configurator_attribute_id')->default(null);
            $schema[] = Hidden::make('target_configurator_attribute_id')->default(null);
            $schema[] = Hidden::make('sets')->default([]);
            $schema[] = Repeater::make('effects')->label('Effects')->minItems(1)->defaultItems(1)->columnSpanFull()->columns(2)->schema([
                Hidden::make('id')->default(fn (): string => 'new:'.Str::uuid()),
                Select::make('kind')->label('Effect')->options(collect(RuleEffectKind::cases())->mapWithKeys(fn (RuleEffectKind $kind): array => [$kind->value => Str::headline($kind->value)])->all())->required()->live()->helperText('Exclusion is an advanced restriction. Use a mapping for ordinary allowed-option sets.'),
                Select::make('target_configurator_attribute_id')->label('Target Attribute')->options($attributes)->required()->searchable()->live(),
                Select::make('target_scope')->label('Applies to')->options(['Attribute' => 'Whole Attribute', 'Options' => 'Selected Options'])->required()->default('Options')->live(),
                Select::make('option_ids')->label('Target Options')->options(fn (Get $get): array => self::withStale($options[$get('target_configurator_attribute_id')] ?? [], $get('option_ids') ?? []))->multiple()->searchable()->default([])
                    ->visible(fn (Get $get): bool => $get('target_scope') === 'Options' || ($get('option_ids') ?? []) !== [])->dehydratedWhenHidden(),
                TextInput::make('display_value')->label('Presentation text')->maxLength(255)->default(null)
                    ->visible(fn (Get $get): bool => in_array($get('kind'), ['SetLabel', 'SetDisplayValue', 'SetHint'], true) || filled($get('display_value')))->dehydratedWhenHidden(),
            ]);
        }

        return $schema;
    }

    /** @param array<int, string> $attributes @param array<int, array<int, string>> $options @param array<string, mixed> $context @return array<\Filament\Schemas\Components\Component> */
    private static function predicate(array $attributes, array $options, array $context): array
    {
        $selection = fn (Get $get): bool => in_array($get('source_kind'), ['SelectionOption', 'SelectionCode'], true);
        $list = fn (Get $get): bool => in_array($get('operator'), ['In', 'NotIn'], true);
        $contextSource = fn (Get $get): bool => in_array($get('source_kind'), ['Territory', 'Application'], true);
        $contextChoices = fn (Get $get): array => array_column($context[$get('source_kind') === 'Territory' ? 'territory' : 'application'] ?? [], 'label', 'value');

        return [
            Hidden::make('id')->default(fn (): string => 'new:'.Str::uuid()),
            Select::make('source_kind')->label('Source')->options(collect(ConditionSource::cases())->mapWithKeys(fn (ConditionSource $source): array => [$source->value => Str::headline($source->value)])->all())->required()->live()
                ->afterStateUpdated(fn (mixed $state, Set $set) => $set('context_dimension', match ($state) {
                    'Territory' => 'territory', 'Application' => 'application', default => null
                })),
            Select::make('operator')->options(fn (Get $get): array => collect(ConditionOperator::cases())->filter(fn (ConditionOperator $operator): bool => $operator !== ConditionOperator::Contains || $get('source_kind') === 'ProductProperty')->mapWithKeys(fn (ConditionOperator $operator): array => [$operator->value => Str::headline($operator->value)])->all())->required()->default('Equals')->live(),
            Select::make('source_configurator_attribute_id')->label('Source Attribute')->options($attributes)->searchable()->live()->default(null)
                ->visible(fn (Get $get): bool => $selection($get) || filled($get('source_configurator_attribute_id')))->dehydratedWhenHidden(),
            Select::make('property_key')->label('Product property')->options(array_combine(CatalogImportParser::propertyKeys(), CatalogImportParser::propertyKeys()))->searchable()->default(null)
                ->visible(fn (Get $get): bool => $get('source_kind') === 'ProductProperty' || filled($get('property_key')))->dehydratedWhenHidden(),
            Hidden::make('context_dimension')->default(null),
            Select::make('option_ids')->label('Included Options / exact codes')->multiple()->searchable()->default([])
                ->options(fn (Get $get): array => self::withStale($options[$get('source_configurator_attribute_id')] ?? [], $get('option_ids') ?? []))
                ->maxItems(fn (Get $get): int => $list($get) ? 500 : 1)
                ->visible(fn (Get $get): bool => $selection($get) || ($get('option_ids') ?? []) !== [])->dehydratedWhenHidden(),
            TextInput::make('operand_text')->key('literal-text')->label('Exact text')->trim(false)->maxLength(5000)->default(null)
                ->visible(fn (Get $get): bool => ! $contextSource($get) && ((! $selection($get) && ! $list($get)) || $get('operand_text') !== null))->dehydratedWhenHidden(),
            Select::make('operand_text')->key('context-value')->label('Context choice')->options($contextChoices)->default(null)
                ->visible(fn (Get $get): bool => $contextSource($get) && (! $list($get) || $get('operand_text') !== null))->dehydratedWhenHidden(false)
                ->helperText(fn (Get $get): ?string => $list($get) ? 'Clear the previous single choice before saving a list condition.' : null),
            TagsInput::make('operand_list')->key('literal-values')->label('Exact text values')->default([])
                ->visible(fn (Get $get): bool => ! $contextSource($get) && ((! $selection($get) && $list($get)) || ($get('operand_list') ?? []) !== []))->dehydratedWhenHidden(),
            Select::make('operand_list')->key('context-values')->label('Context choices')->multiple()->options($contextChoices)->default([])
                ->visible(fn (Get $get): bool => $contextSource($get) && ($list($get) || ($get('operand_list') ?? []) !== []))->dehydratedWhenHidden(false)
                ->helperText(fn (Get $get): ?string => ! $list($get) ? 'Clear the previous choices before saving a single-value condition.' : null),
        ];
    }

    /** @param array<int|string, string> $choices @param list<int|string> $ids @return array<int|string, string> */
    private static function withStale(array $choices, array $ids): array
    {
        foreach ($ids as $id) {
            if ((is_int($id) || is_string($id)) && ! array_key_exists($id, $choices)) {
                $choices[$id] = 'Unavailable local reference '.$id.' — remove or replace';
            }
        }

        return $choices;
    }
}
