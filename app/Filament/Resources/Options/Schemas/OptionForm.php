<?php

namespace App\Filament\Resources\Options\Schemas;

use App\Filament\Resources\FormHints;
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
        return $schema->columns(1)->components([Section::make(__('catalog.attribute_values.headings.shared'))->key(fn (Section $component): string => 'shared-attribute-value::'.(filled($component->getStatePath()) ? $component->getStatePath().'::section' : 'section'), isInheritable: false)->afterHeader([FormHints::make(__('catalog.attribute_values.hints.the_attribute_master_value_and_exact_code'))])->compact()->columns(2)->schema([
            Select::make('attribute_id')->label(__('catalog.resources.attributes.singular'))->required()->rules(['integer'])->searchable()
                ->getSearchResultsUsing(fn (string $search): array => Attribute::where('label', 'like', '%'.$search.'%')->orWhere('key', 'like', '%'.$search.'%')->orderBy('label')->limit(50)->get()->mapWithKeys(fn (Attribute $attribute): array => [$attribute->id => $attribute->label.' · '.$attribute->key])->all())
                ->getOptionLabelUsing(fn (mixed $value): ?string => is_scalar($value) ? Attribute::find($value)?->label : null),
            Select::make('value_id')->label(__('catalog.fields.ui_name'))->required()->rules(['integer'])->searchable()
                ->getSearchResultsUsing(fn (string $search): array => Value::where('label', 'like', '%'.$search.'%')->orderBy('label')->limit(50)->get()->mapWithKeys(fn (Value $value): array => [$value->id => $value->label.' · #'.$value->id.($value->description ? ' · '.Str::limit($value->description, 70) : '')])->all())
                ->getOptionLabelUsing(fn (mixed $value): ?string => is_scalar($value) ? Value::find($value)?->label : null),
            TextInput::make('code')->label(__('catalog.fields.code'))->required()->length(2)->trim(false)->regex('/\A[A-Za-z0-9]{2}\z/D')->hintAction(FormHints::make(__('catalog.attribute_values.hints.enter_two_ascii_letters_or_digits_case'))),
        ])]);
    }
}
