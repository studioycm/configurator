<?php

namespace App\Filament\Resources\Configurators\Schemas;

use App\ConditionOperator;
use App\ConditionSource;
use App\Filament\Forms\Components\MappingSetsField;
use App\Filament\Resources\FormHints;
use App\Models\CatalogContextSettings;
use App\Models\Configurator;
use App\RuleEffectKind;
use App\RuleTargetScope;
use App\Services\ConfiguratorDefinitionCompiler;
use App\Services\ProductFields;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
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
                $options[$attribute->id][$option->id] = $option->option->displayName($option->label_override, $attribute->label_override ?? $attribute->attribute->label);
            }
        }
        $context = app(ConfiguratorDefinitionCompiler::class)->resolveContext($owner->context_schema ?? [], $owner->hidden_context_options ?? [], CatalogContextSettings::current()->choices);
        $properties = ProductFields::options(configuratorId: $owner->id);
        $predicate = fn (): array => self::predicate($attributes, $options, $context, $properties);
        $schema = [
            View::make('filament.forms.validation-summary'),
            Hidden::make('id')->default(fn (): string => 'new:'.Str::uuid()),
            Hidden::make('kind')->default($kind), Hidden::make('priority')->default(0),
            Grid::make(2)->schema([
                TextInput::make('label')->label(__('ui.fields.label'))->required()->maxLength(255),
                Toggle::make('is_active')->label(__('ui.status.enabled'))->default(true),
            ]),
            Builder::make('condition_blocks')->searchable()->label(__('configurator.labels.all_conditions'))->hintAction(FormHints::make(__('configurator.hints.an_empty_root_is_unconditional_groups_allow')))
                ->blocks([
                    Block::make('predicate')->label(__('configurator.labels.condition'))->schema($predicate())->columns(2),
                    Block::make('group')->label(__('configurator.labels.all_any_group'))->schema([
                        Hidden::make('id')->default(fn (): string => 'new:'.Str::uuid()),
                        Select::make('operator')->label(__('configurator.labels.match'))->options(['All' => __('ui.choices.all'), 'Any' => __('configurator.labels.any')])->required()->default('All'),
                        Repeater::make('conditions')->label(__('configurator.fields.conditions'))->schema($predicate())->minItems(1)->defaultItems(1)->columns(2)->columnSpanFull(),
                    ]),
                ])->default([])->columnSpanFull(),
        ];
        if ($kind === 'Mapping') {
            $schema[] = Section::make(__('configurator.headings.driver_and_target'))->key(fn (Section $component): string => 'driver-and-target::'.(filled($component->getStatePath()) ? $component->getStatePath().'::section' : 'section'), isInheritable: false)->afterHeader([FormHints::make(__('configurator.hints.changing_either_attribute_preserves_existing_set_references'))])->columns(2)->schema([
                Select::make('driver_configurator_attribute_id')->label(__('configurator.labels.driver_attribute'))->options($attributes)->required()->searchable()->live(),
                Select::make('target_configurator_attribute_id')->label(__('configurator.rules.fields.target_attribute'))->options($attributes)->required()->searchable()->live(),
            ]);
            $schema[] = MappingSetsField::make('sets')->label(__('configurator.labels.allowed_value_sets'))->required()->default([])->columnSpanFull()
                ->hintAction(FormHints::make(__('configurator.hints.unmapped_driver_values_add_no_restriction_each')))
                ->sourceChoices(fn (Get $get): array => $options[$get('driver_configurator_attribute_id')] ?? [])
                ->targetChoices(fn (Get $get): array => $options[$get('target_configurator_attribute_id')] ?? []);
            $schema[] = Hidden::make('effects')->default([]);
        } else {
            $schema[] = Hidden::make('driver_configurator_attribute_id')->default(null);
            $schema[] = Hidden::make('target_configurator_attribute_id')->default(null);
            $schema[] = Hidden::make('sets')->default([]);
            $schema[] = Repeater::make('effects')->label(__('configurator.labels.effects'))->minItems(1)->defaultItems(1)->columnSpanFull()->columns(2)->schema([
                Hidden::make('id')->default(fn (): string => 'new:'.Str::uuid()),
                Select::make('kind')->label(__('configurator.rules.fields.effect'))->options(collect(RuleEffectKind::cases())->mapWithKeys(fn (RuleEffectKind $kind): array => [$kind->value => $kind->getLabel()])->all())->required()->live()->hintAction(FormHints::make(__('configurator.hints.exclusion_is_an_advanced_restriction_use_a'))),
                Select::make('target_configurator_attribute_id')->label(__('configurator.rules.fields.target_attribute'))->options($attributes)->required()->searchable()->live(),
                Select::make('target_scope')->label(__('configurator.labels.applies_to'))->options(collect(RuleTargetScope::cases())->mapWithKeys(fn (RuleTargetScope $scope): array => [$scope->value => $scope->getLabel()])->all())->required()->default('Options')->live(),
                Select::make('option_ids')->label(__('configurator.labels.target_values'))->options(fn (Get $get): array => self::withStale($options[$get('target_configurator_attribute_id')] ?? [], $get('option_ids') ?? []))->multiple()->searchable()->default([])
                    ->visible(fn (Get $get): bool => $get('target_scope') === 'Options' || ($get('option_ids') ?? []) !== [])->dehydratedWhenHidden(),
                TextInput::make('display_value')->label(__('configurator.labels.presentation_text'))->maxLength(255)->default(null)
                    ->visible(fn (Get $get): bool => in_array($get('kind'), ['SetLabel', 'SetDisplayValue', 'SetHint'], true) || filled($get('display_value')))->dehydratedWhenHidden(),
            ]);
        }

        return array_values($schema);
    }

    /** @param array<int, string> $attributes @param array<int, array<int, string>> $options @param array<string, mixed> $context @return array<\Filament\Schemas\Components\Component> */
    private static function predicate(array $attributes, array $options, array $context, array $properties): array
    {
        $selection = fn (Get $get): bool => in_array($get('source_kind'), ['SelectionOption', 'SelectionCode'], true);
        $list = fn (Get $get): bool => in_array($get('operator'), ['In', 'NotIn'], true);
        $contextSource = fn (Get $get): bool => in_array($get('source_kind'), ['Territory', 'Application'], true);
        $contextChoices = fn (Get $get): array => array_column($context[$get('source_kind') === 'Territory' ? 'territory' : 'application'] ?? [], 'label', 'value');

        return [
            Hidden::make('id')->default(fn (): string => 'new:'.Str::uuid()),
            Select::make('source_kind')->label(__('configurator.labels.source'))->options([
                __('configurator.rules.sources.dashboard') => collect(ConditionSource::cases())->reject(fn (ConditionSource $source): bool => in_array($source->value, ['Territory', 'Application'], true))->mapWithKeys(fn (ConditionSource $source): array => [$source->value => $source->getLabel()])->all(),
                __('configurator.labels.future_public_catalog') => ['Territory' => __('configurator.labels.territory'), 'Application' => __('configurator.labels.application')],
            ])->hintAction(FormHints::make(__('configurator.hints.separate_territory_and_application_conditions_apply_only')))
                ->helperText(fn (Get $get): ?string => $selection($get) ? __('configurator.hints.selection_source_difference') : null)->required()->live()
                ->afterStateUpdated(fn (mixed $state, Set $set) => $set('context_dimension', match ($state) {
                    'Territory' => 'territory', 'Application' => 'application', default => null
                })),
            Select::make('operator')->label(__('configurator.fields.operator'))->options(fn (Get $get): array => collect(ConditionOperator::cases())->filter(fn (ConditionOperator $operator): bool => $operator !== ConditionOperator::Contains || $get('source_kind') === 'ProductProperty')->mapWithKeys(fn (ConditionOperator $operator): array => [$operator->value => $operator->getLabel()])->all())->required()->default('Equals')->live(),
            Select::make('source_configurator_attribute_id')->label(__('configurator.rules.fields.source_attribute'))->options($attributes)->searchable()->live()->default(null)
                ->visible(fn (Get $get): bool => $selection($get) || filled($get('source_configurator_attribute_id')))->dehydratedWhenHidden(),
            Select::make('property_key')->label(__('catalog.fields.product_property'))->options($properties)->searchable()->default(null)
                ->visible(fn (Get $get): bool => $get('source_kind') === 'ProductProperty' || filled($get('property_key')))->dehydratedWhenHidden(),
            Hidden::make('context_dimension')->default(null),
            Select::make('option_ids')->key(fn (Get $get): string => 'condition-values-'.($list($get) ? 'list' : 'single'))->label(__('configurator.labels.included_values_exact_codes'))->multiple()->searchable()->default([])
                ->options(fn (Get $get): array => self::withStale($options[$get('source_configurator_attribute_id')] ?? [], $get('option_ids') ?? []))
                ->maxItems(fn (Get $get): int => $list($get) ? 500 : 1)
                ->visible(fn (Get $get): bool => $selection($get) || ($get('option_ids') ?? []) !== [])->dehydratedWhenHidden(),
            TextInput::make('operand_text')->key('literal-text')->label(__('configurator.labels.exact_text'))->trim(false)->maxLength(5000)->default(null)
                ->visible(fn (Get $get): bool => ! $contextSource($get) && ((! $selection($get) && ! $list($get)) || $get('operand_text') !== null))->dehydratedWhenHidden(),
            Select::make('operand_text')->key('context-value')->label(__('configurator.labels.context_choice'))->options($contextChoices)->default(null)
                ->dehydrateStateUsing(fn (mixed $state): ?string => $state === null ? null : (string) $state)
                ->visible(fn (Get $get): bool => $contextSource($get) && (! $list($get) || $get('operand_text') !== null))->dehydratedWhenHidden(false)
                ->helperText(fn (Get $get): ?string => $list($get) ? __('configurator.hints.clear_the_previous_single_choice_before_saving') : null),
            TagsInput::make('operand_list')->key('literal-values')->label(__('configurator.labels.exact_text_values'))->default([])
                ->visible(fn (Get $get): bool => ! $contextSource($get) && ((! $selection($get) && $list($get)) || ($get('operand_list') ?? []) !== []))->dehydratedWhenHidden(),
            Select::make('operand_list')->key('context-values')->label(__('configurator.labels.context_choices'))->multiple()->options($contextChoices)->default([])
                ->dehydrateStateUsing(fn (array $state): array => array_map('strval', $state))
                ->visible(fn (Get $get): bool => $contextSource($get) && ($list($get) || ($get('operand_list') ?? []) !== []))->dehydratedWhenHidden(false)
                ->helperText(fn (Get $get): ?string => ! $list($get) ? __('configurator.hints.clear_the_previous_choices_before_saving_a') : null),
        ];
    }

    /** @param array<int|string, string> $choices @param list<int|string> $ids @return array<int|string, string> */
    private static function withStale(array $choices, array $ids): array
    {
        foreach ($ids as $id) {
            if ((is_int($id) || is_string($id)) && ! array_key_exists($id, $choices)) {
                $choices[$id] = __('configurator.rules.stale_reference', ['id' => $id]);
            }
        }

        return $choices;
    }
}
