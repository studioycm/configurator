<?php

namespace App\Filament\Resources\Attributes\Schemas;

use App\Filament\Forms\Components\WorkspaceTableSelect as TableSelect;
use App\Filament\Resources\FormHints;
use App\Filament\Resources\ValueSelectionTable;
use App\Models\Attribute;
use App\Models\Value;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class AttributeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([Section::make()->key(fn (Section $component): string => 'shared-attribute::'.(filled($component->getStatePath()) ? $component->getStatePath().'::section' : 'section'), isInheritable: false)->contained(false)->compact()->columns(2)->schema([
            TextInput::make('key')->label(__('catalog.fields.key'))->required()->maxLength(100)->hintAction(FormHints::make(__('catalog.attributes.hints.a_stable_identity_used_keys_require_reference'))),
            TextInput::make('label')->label(__('ui.fields.label'))->hintAction(FormHints::make(__('catalog.attributes.hints.labels_apply_wherever_this_attribute_is_included')))->required()->maxLength(255),
        ]), Section::make(__('attribute-options.heading'))->key('attribute-values')->contained(false)->compact()->description(__('attribute-options.help'))
            ->headerActions([
                Action::make('addValues')->label(__('attribute-options.add'))->modalSubmitActionLabel(__('attribute-options.add_selected'))->authorize('manage-catalog')
                    ->schema(fn (Get $schemaGet, ?Attribute $record): array => [TableSelect::make('values')->label(__('attribute-options.selected'))->multiple()->required()->minItems(1)
                        ->tableConfiguration(ValueSelectionTable::class)
                        ->tableArguments(['owner_id' => $record?->id ?? 'create', 'attribute_id' => $record?->id, 'excluded_ids' => array_column($schemaGet('pending_options') ?? [], 'value_id')])])
                    ->action(function (array $data, Get $schemaGet, Set $schemaSet): void {
                        $pending = $schemaGet('pending_options') ?? [];
                        $ids = array_diff($data['values'], array_column($pending, 'value_id'));
                        foreach (Value::whereIn('id', $ids)->orderBy('label')->get() as $value) {
                            $pending[] = ['value_id' => $value->id, 'name' => $value->labelWithSemanticId(), 'code' => ''];
                        }
                        $schemaSet('pending_options', $pending);
                    }),
            ])->schema([
                Repeater::make('pending_options')->hiddenLabel()->default([])->addable(false)->reorderable(false)->compact()
                    ->table([TableColumn::make(__('catalog.fields.value_ui_name')), TableColumn::make(__('catalog.fields.code'))])
                    ->schema([
                        Hidden::make('value_id')->required()->rules(['integer'])->distinct(),
                        TextInput::make('name')->label(__('catalog.fields.value_ui_name'))->readOnly()->dehydrated(false),
                        TextInput::make('code')->label(__('catalog.fields.code'))->required()->length(2)->trim(false)->regex('/\A[A-Za-z0-9]{2}\z/D')->distinct()->unique('options', 'code', ignoreRecord: false)
                            ->hintAction(FormHints::make(__('attribute-options.code_help'))),
                    ]),
            ])]);
    }
}
