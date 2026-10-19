<?php

namespace App\Filament\Resources\Configurators\Schemas;

use App\ConfigInputType;
use App\Filament\Resources\FormHints;
use App\Filament\Resources\FormOrderControls;
use App\Models\Attribute;
use App\Models\Configurator;
use App\Models\ConfiguratorAttribute;
use App\Models\Option;
use App\Services\ConfiguratorInclusionDrafts;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Illuminate\Support\Str;

class ConfiguratorAttributeForm
{
    /** @return array<Component> */
    public static function reviewComponents(Configurator $owner): array
    {
        $fields = self::components($owner, chooseCanonical: false);
        $default = array_pop($fields);

        return [$default, Section::make(__('configurator.headings.local_settings_and_values'))->key(fn (Section $component): string => 'local-settings-and-values::'.(filled($component->getStatePath()) ? $component->getStatePath().'::section' : 'section'), isInheritable: false)->compact()->collapsible()->collapsed()->schema($fields)];
    }

    /** @return array<Component> */
    public static function components(Configurator $owner, ?ConfiguratorAttribute $inclusion = null, bool $withOptions = true, bool $chooseCanonical = true): array
    {
        $optionsByAttribute = [];
        $optionLabels = function (mixed $attributeId, ?string $attributeLabel = null, array $overrides = []) use (&$optionsByAttribute): array {
            if (! is_numeric($attributeId)) {
                return [];
            }

            $optionsByAttribute[(int) $attributeId] ??= Option::with(['value', 'attribute'])->where('attribute_id', $attributeId)->orderBy('code')->get();

            return $optionsByAttribute[(int) $attributeId]->mapWithKeys(fn (Option $option): array => [$option->id => $option->displayName($overrides[$option->id] ?? null, $attributeLabel)])->all();
        };

        return [
            View::make('filament.forms.validation-summary'),
            Hidden::make('id')->default(fn (): string => 'new:'.Str::uuid()),
            Hidden::make('canonical_drafts')->default([])->dehydrated(false),
            Grid::make(2)->schema([
                Select::make('attribute_id')->label(__('configurator.labels.canonical_attribute'))->required()->rules(['integer'])->searchable()->live()->disabled($inclusion !== null || ! $chooseCanonical)->dehydrated()
                    ->hintAction(FormHints::make(__('configurator.hints.these_settings_change_this_configurator_only_canonical')))
                    ->afterStateUpdated(function (Get $get, Set $set, mixed $state, mixed $old): void {
                        $drafts = $get('canonical_drafts') ?? [];
                        $fields = ['options', 'default_configurator_option_id', 'label_override', 'help_text', 'input_type'];
                        if (is_numeric($old)) {
                            $drafts[(int) $old] = array_combine($fields, array_map(fn (string $field): mixed => $get($field), $fields));
                            $set('canonical_drafts', $drafts);
                        }
                        if (is_numeric($state)) {
                            $row = $drafts[(int) $state] ?? app(ConfiguratorInclusionDrafts::class)->attribute((int) $state);
                            foreach ($fields as $field) {
                                $set($field, $row[$field]);
                            }
                        }
                    })
                    ->options(fn (): array => Attribute::where(fn ($query) => $query->where('is_active', true)->when($inclusion, fn ($query) => $query->orWhereKey($inclusion->attribute_id)))->whereNotIn('id', $owner->attributes()->when($inclusion, fn ($query) => $query->whereKeyNot($inclusion->id))->pluck('attribute_id'))->orderBy('label')->get()->mapWithKeys(fn (Attribute $attribute): array => [$attribute->id => $attribute->label.' · '.$attribute->key])->all()),
                Select::make('input_type')->label(__('configurator.fields.input_type'))->required()->options(ConfigInputType::class)->default('toggle'),
                TextInput::make('label_override')->label(__('configurator.labels.local_label'))->maxLength(255)->hintAction(FormHints::make(__('configurator.hints.leave_empty_to_use_the_shared_attribute'))),
                Textarea::make('help_text')->label(__('configurator.labels.local_help'))->maxLength(1000)->rows(3),
            ]),
            ...($withOptions ? [FormOrderControls::make('options')->label(__('configurator.inclusions.values.heading'))->minItems(1)->defaultItems(0)->reorderableWithButtons()->live()->columnSpanFull()->columns(2)->schema([
                Hidden::make('id')->default(fn (): string => 'new:'.Str::uuid()),
                Select::make('option_id')->label(__('catalog.attribute_values.headings.shared'))->required()->rules(['integer'])->searchable()->live()
                    ->disabled(fn (Get $get): bool => filled($get('id')) && ! str_starts_with((string) $get('id'), 'new:'))->dehydrated()
                    ->options(fn (Get $get): array => $optionLabels($get('../../attribute_id'), $get('../../label_override'), [$get('option_id') => $get('label_override')])),
                TextInput::make('label_override')->label(__('configurator.inclusions.values.fields.local_ui_name'))->maxLength(255),
                TextInput::make('display_value_override')->label(__('configurator.labels.local_display_value'))->maxLength(255),
                TextInput::make('hint')->label(__('configurator.labels.local_hint'))->maxLength(1000),
                Toggle::make('hidden_by_default')->label(__('configurator.labels.initially_hidden'))->default(false),
                Toggle::make('disabled_by_default')->label(__('configurator.labels.initially_disabled'))->default(false),
            ])->hintAction(FormHints::make(__('configurator.hints.reordering_does_not_change_the_stored_default')))] : []),
            Select::make('default_configurator_option_id')->label(__('configurator.labels.stored_default'))->required()->live()->options(function (Get $get) use ($optionLabels, $withOptions, $inclusion): array {
                if (! $withOptions) {
                    return $inclusion->options()->with(['option.value', 'option.attribute'])->orderBy('display_order')->get()
                        ->mapWithKeys(fn ($option): array => [$option->id => $option->option->displayName($option->label_override, $get('label_override') ?? $inclusion->attribute->label)])->all();
                }
                $canonical = $optionLabels($get('attribute_id'), $get('label_override'), array_column($get('options') ?? [], 'label_override', 'option_id'));
                $options = [];
                foreach ($get('options') ?? [] as $row) {
                    if (isset($row['id'], $row['option_id'])) {
                        $options[(string) $row['id']] = $canonical[$row['option_id']] ?? __('configurator.inclusions.values.unavailable');
                    }
                }
                $current = $get('default_configurator_option_id');
                if (is_scalar($current) && (string) $current !== '' && ! isset($options[(string) $current])) {
                    $options[(string) $current] = __('configurator.inclusions.values.removed');
                }

                return $options;
            })->hintAction(FormHints::make(__('configurator.hints.local_initial_or_rule_driven_unavailability_may_fall'))),
        ];
    }
}
