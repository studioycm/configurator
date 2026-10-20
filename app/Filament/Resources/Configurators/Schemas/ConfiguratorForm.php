<?php

namespace App\Filament\Resources\Configurators\Schemas;

use App\Filament\Resources\FormHints;
use App\Filament\Resources\FormOrderControls;
use App\Models\CatalogContextSettings;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class ConfiguratorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components(self::overview(false));
    }

    /** @return array<Component> */
    public static function overview(bool $withContext = true): array
    {
        $fields = [TextInput::make('name')->label(__('ui.fields.name'))->required()->maxLength(255), Textarea::make('description')->label(__('configurator.fields.description'))->rows(3)->maxLength(5000)->columnSpanFull()];
        $sections = [View::make('filament.forms.validation-summary')->columnSpanFull(), ...$fields];
        if ($withContext) {
            $global = CatalogContextSettings::current()->choices;
            $contextSections = [];
            foreach (['territory' => __('configurator.labels.territory'), 'application' => __('configurator.labels.application')] as $dimension => $label) {
                $contextSections[] = Section::make(__('configurator.headings.choices', ['value1' => $label]))->contained(false)->extraAttributes(['class' => 'catalog-context-choices'])
                    ->afterHeader([FormHints::make(__('configurator.hints.all_global_options_are_available_by_default'))])
                    ->schema([
                        CheckboxList::make('hidden_context_options.'.$dimension)->label(__('configurator.labels.hide_global_options'))
                            ->options(function (Get $get) use ($global, $dimension): array {
                                $choices = array_column($global[$dimension], 'label', 'value');
                                foreach ($get('hidden_context_options.'.$dimension) ?? [] as $value) {
                                    $choices[$value] ??= $value.__('configurator.context.no_longer_global_suffix');
                                }

                                return $choices;
                            })->columns(['default' => 1, 'sm' => 2])->default([])
                            ->dehydrateStateUsing(fn (array $state): array => array_map('strval', $state))
                            ->hintAction(FormHints::make(__('configurator.hints.checked_options_are_hidden_here_only_all'))),
                        self::contextChoices($dimension, __('configurator.context.local_options'))->extraAttributes(['class' => 'catalog-context-local-options'])
                            ->hintAction(FormHints::make(__('configurator.hints.add_choices_for_this_configurator_only_using'))),
                    ]);
            }
            $sections[] = Section::make(__('configurator.headings.future_public_catalog_context'))->key('public-context')->collapsed()->columns(['default' => 1, 'lg' => 2])->columnSpanFull()
                ->afterHeader([FormHints::make(__('configurator.hints.territory_and_application_are_reserved_for_the'))])
                ->schema($contextSections);
        }

        return $sections;
    }

    public static function contextChoices(string $dimension, string $label): Repeater
    {
        return FormOrderControls::make('context_schema.'.$dimension)->label($label)
            ->table([TableColumn::make('Label'), TableColumn::make(__('configurator.context.stable_value'))])->compact()->schema([
                TextInput::make('label')->label(__('ui.fields.label'))->required()->maxLength(255),
                TextInput::make('value')->label(__('configurator.labels.stable_value'))->required()->maxLength(255)->distinct(),
            ])->defaultItems(0)->reorderableWithButtons()->columnSpanFull()->addActionLabel(__('configurator.labels.add_option'))
            ->hintAction(FormHints::make(__('configurator.hints.stable_values_are_used_by_rules_keep')));
    }
}
