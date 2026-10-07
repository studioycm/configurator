<?php

namespace App\Filament\Resources\Attributes\Schemas;

use App\Filament\Resources\FormHints;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AttributeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([Section::make('Shared Attribute')->afterHeader([FormHints::make('Labels apply wherever this Attribute is included. Local label overrides remain unchanged.')])->compact()->columns(2)->schema([
            TextInput::make('key')->required()->maxLength(100)->hintAction(FormHints::make('A stable identity. Used keys require reference repair before changing.')),
            TextInput::make('label')->required()->maxLength(255),
        ])]);
    }
}
