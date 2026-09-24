<?php

namespace App\Filament\Resources\Values\Schemas;

use App\Models\Value;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ValueForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([Section::make('Master value')->description('Reuse an existing meaning where appropriate. Equal labels can describe distinct meanings.')->schema([
            TextInput::make('label')->required()->maxLength(255),
            TagsInput::make('tags')->default([])->trim()->nestedRecursiveRules(['required', 'string', 'max:80', 'distinct'])->rules(['array', 'max:30'])->suggestions(fn (): array => array_values(Value::tagOptions()))->helperText('Press Enter after each tag. Tags help filter the master value list.'),
            Textarea::make('description')->label('Explanatory text')->rows(3)->maxLength(5000)->columnSpanFull(),
        ])]);
    }
}
