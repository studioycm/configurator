<?php

namespace App\Filament\Resources\Values\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ValueForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([Section::make('Shared Value')->description('Reuse an existing meaning where appropriate. Equal labels can describe distinct meanings.')->schema([
            TextInput::make('label')->required()->maxLength(255),
            Textarea::make('description')->label('Explanatory text')->rows(3)->maxLength(5000)->columnSpanFull(),
        ])]);
    }
}
