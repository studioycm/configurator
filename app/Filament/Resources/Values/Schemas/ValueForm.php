<?php

namespace App\Filament\Resources\Values\Schemas;

use App\Filament\Resources\FormHints;
use App\Filament\Resources\SuggestedIdentifier;
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
        return $schema->columns(1)->components([Section::make()->key(fn (Section $component): string => 'master-value::'.(filled($component->getStatePath()) ? $component->getStatePath().'::section' : 'section'), isInheritable: false)->contained(false)->compact()->columns(2)->extraAttributes(['class' => 'catalog-simple-form'])->schema([
            ...SuggestedIdentifier::fields(
                TextInput::make('label')->label(__('catalog.fields.ui_name'))->hintAction(FormHints::make(__('catalog.master_values.hints.reuse_an_existing_meaning_where_appropriate_equal')))->required()->maxLength(255),
                TextInput::make('semantic_id')->label(__('catalog.fields.semantic_id'))->required()->maxLength(100)
                    ->regex('/\A[a-z0-9]+(?:_[a-z0-9]+)*\z/D')->unique()
                    ->hintAction(FormHints::make(__('catalog.master_values.help.semantic_id'))),
                Value::suggestSemanticId(...),
            ),
            TagsInput::make('tags')->columnSpanFull()->label(__('catalog.fields.tags'))->default([])->trim()->nestedRecursiveRules(['required', 'string', 'max:80', 'distinct'])->rules(['array', 'max:30'])->suggestions(fn (): array => array_values(Value::tagOptions()))->hintAction(FormHints::make(__('catalog.master_values.hints.press_enter_after_each_tag_tags_help'))),
            Textarea::make('description')->label(__('catalog.fields.specification'))->rows(3)->maxLength(5000)->columnSpanFull(),
        ])]);
    }
}
