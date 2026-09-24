<?php

namespace App\Filament\Resources\Attributes\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AttributeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([Section::make('Shared Attribute')->description('Labels apply wherever this Attribute is included. Local label overrides remain unchanged.')->columns(2)->schema([
            TextInput::make('key')->required()->maxLength(100)->helperText('A stable identity. Used keys require reference repair before changing.'),
            TextInput::make('label')->required()->maxLength(255),
        ])]);
    }
}
