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
        return $schema->columns(1)->components([Section::make(__('catalog.attributes.headings.shared_attribute'))->key(fn (Section $component): string => 'shared-attribute::'.(filled($component->getStatePath()) ? $component->getStatePath().'::section' : 'section'), isInheritable: false)->afterHeader([FormHints::make(__('catalog.attributes.hints.labels_apply_wherever_this_attribute_is_included'))])->compact()->columns(2)->schema([
            TextInput::make('key')->label(__('catalog.fields.key'))->required()->maxLength(100)->hintAction(FormHints::make(__('catalog.attributes.hints.a_stable_identity_used_keys_require_reference'))),
            TextInput::make('label')->label(__('ui.fields.label'))->required()->maxLength(255),
        ])]);
    }
}
