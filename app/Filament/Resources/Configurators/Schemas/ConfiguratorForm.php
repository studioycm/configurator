<?php

namespace App\Filament\Resources\Configurators\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
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
        if ($withContext) {
            foreach (['territory' => 'Territory choices', 'application' => 'Application choices'] as $dimension => $label) {
                $fields[] = Repeater::make('context_schema.'.$dimension)->label($label)->schema([
                    TextInput::make('value')->label('Stable value')->required()->maxLength(255),
                    TextInput::make('label')->required()->maxLength(255),
                ])->columns(2)->defaultItems(0)->reorderableWithButtons()->columnSpanFull()->helperText('All is the built-in unrestricted choice. Existing rule references must be repaired before a choice is removed.');
            }
        }

        return [View::make('filament.forms.validation-summary'), Section::make('Configurator details')->columns(2)->schema($fields)];
    }
}
