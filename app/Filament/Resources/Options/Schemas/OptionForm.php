<?php

namespace App\Filament\Resources\Options\Schemas;

use App\Filament\Resources\FormHints;
use App\Models\Attribute;
use App\Models\Value;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class OptionForm
{
    public static function configure(Schema $schema, ?int $fixedValueId = null): Schema
    {
        return $schema->columns(1)->components([Section::make()->key(fn (Section $component): string => 'shared-attribute-value::'.(filled($component->getStatePath()) ? $component->getStatePath().'::section' : 'section'), isInheritable: false)->contained(false)->compact()->columns(2)->extraAttributes(['class' => 'catalog-simple-form'])->schema([
            Select::make('attribute_id')->label(__('catalog.resources.attributes.singular'))->hintAction(FormHints::make(__('catalog.attribute_values.hints.the_attribute_master_value_and_exact_code')))->required()->rules(['integer'])->searchable()
                ->getSearchResultsUsing(fn (string $search): array => Attribute::query()
                    ->when($fixedValueId !== null, fn (Builder $query) => $query->whereDoesntHave('options', fn (Builder $query) => $query->where('value_id', $fixedValueId)))
                    ->where(fn (Builder $query) => $query->where('label', 'like', '%'.$search.'%')->orWhere('key', 'like', '%'.$search.'%'))
                    ->orderBy('label')->limit(50)->get()->mapWithKeys(fn (Attribute $attribute): array => [$attribute->id => $attribute->label.' · '.$attribute->key])->all())
                ->getOptionLabelUsing(fn (mixed $value): ?string => is_scalar($value) ? Attribute::find($value)?->label : null),
            Select::make('value_id')->label(__('catalog.fields.master_value'))->required()->rules(['integer'])->searchable()->disabled($fixedValueId !== null)->dehydrated($fixedValueId === null)
                ->getSearchResultsUsing(fn (string $search): array => Value::query()->where(fn (Builder $query) => $query->where('label', 'like', '%'.$search.'%')->orWhere('semantic_id', 'like', '%'.$search.'%'))->orderBy('label')->limit(50)->get()->mapWithKeys(fn (Value $value): array => [$value->id => $value->labelWithSemanticId().($value->description ? ' · '.Str::limit($value->description, 70) : '')])->all())
                ->getOptionLabelUsing(fn (mixed $value): ?string => is_scalar($value) ? Value::find($value)?->labelWithSemanticId() : null),
            TextInput::make('code')->label(__('catalog.fields.code'))->required()->length(2)->trim(false)->regex('/\A[A-Za-z0-9]{2}\z/D')->hintAction(FormHints::make(__('catalog.attribute_values.hints.enter_two_ascii_letters_or_digits_case'))),
        ])]);
    }
}
