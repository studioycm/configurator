<?php

namespace App\Filament\Resources\Options\Schemas;

use App\Models\Attribute;
use App\Models\Value;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class OptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([Section::make('Shared Option')->description('The Attribute, Value and exact code are shared by every local inclusion. Review usage before editing.')->columns(2)->schema([
            Select::make('attribute_id')->label('Attribute')->required()->rules(['integer'])->searchable()
                ->getSearchResultsUsing(fn (string $search): array => Attribute::where('label', 'like', '%'.$search.'%')->orWhere('key', 'like', '%'.$search.'%')->orderBy('label')->limit(50)->get()->mapWithKeys(fn (Attribute $attribute): array => [$attribute->id => $attribute->label.' · '.$attribute->key])->all())
                ->getOptionLabelUsing(fn (mixed $value): ?string => is_scalar($value) ? Attribute::find($value)?->label : null),
            Select::make('value_id')->label('Master value')->required()->rules(['integer'])->searchable()
                ->getSearchResultsUsing(fn (string $search): array => Value::where('label', 'like', '%'.$search.'%')->orderBy('label')->limit(50)->get()->mapWithKeys(fn (Value $value): array => [$value->id => $value->label.' · #'.$value->id.($value->description ? ' · '.Str::limit($value->description, 70) : '')])->all())
                ->getOptionLabelUsing(fn (mixed $value): ?string => is_scalar($value) ? Value::find($value)?->label : null),
            TextInput::make('code')->required()->length(2)->trim(false)->regex('/\A[A-Za-z0-9]{2}\z/D')->helperText('Enter two ASCII letters or digits. Case and leading zeros are preserved; the code must be globally unique.'),
        ])]);
    }
}
