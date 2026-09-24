<?php

namespace App\Filament\Resources\Configurators\Schemas;

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
        $fields = [TextInput::make('name')->required()->maxLength(255), Textarea::make('description')->rows(3)->maxLength(5000)->columnSpanFull()];
        $sections = [View::make('filament.forms.validation-summary'), Section::make('Configurator details')->columns(1)->schema($fields)];
        if ($withContext) {
            $global = CatalogContextSettings::current()->choices;
            foreach (['territory' => 'Territory', 'application' => 'Application'] as $dimension => $label) {
                $sections[] = Section::make($label.' choices')
                    ->description('All global options are available by default. Change only the exceptions for this configurator.')
                    ->schema([
                        CheckboxList::make('hidden_context_options.'.$dimension)->label('Hide global options')
                            ->options(function (Get $get) use ($global, $dimension): array {
                                $choices = array_column($global[$dimension], 'label', 'value');
                                foreach ($get('hidden_context_options.'.$dimension) ?? [] as $value) {
                                    $choices[$value] ??= $value.' (no longer global)';
                                }

                                return $choices;
                            })->columns(['default' => 1, 'sm' => 2])->default([])
                            ->dehydrateStateUsing(fn (array $state): array => array_map('strval', $state))
                            ->helperText('Checked options are hidden here only. All remains available.'),
                        self::contextChoices($dimension, 'Local options')
                            ->helperText('Add choices for this configurator only. Using a global stable value customizes its label locally. Existing rules must be repaired before a used choice can be removed.'),
                    ]);
            }
        }

        return $sections;
    }

    public static function contextChoices(string $dimension, string $label): Repeater
    {
        return Repeater::make('context_schema.'.$dimension)->label($label)
            ->table([TableColumn::make('Label'), TableColumn::make('Stable value')])->compact()->schema([
                TextInput::make('label')->required()->maxLength(255),
                TextInput::make('value')->label('Stable value')->required()->maxLength(255)->distinct(),
            ])->defaultItems(0)->reorderableWithButtons()->columnSpanFull()->addActionLabel('Add option')
            ->helperText('Stable values are used by rules. Keep them unchanged when renaming labels.');
    }
}
