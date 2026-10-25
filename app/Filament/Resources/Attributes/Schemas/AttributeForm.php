<?php

namespace App\Filament\Resources\Attributes\Schemas;

use App\Actions\CreateMasterValues;
use App\Filament\Forms\Components\WorkspaceTableSelect as TableSelect;
use App\Filament\Resources\AttributeValueWorkspace;
use App\Filament\Resources\FormHints;
use App\Filament\Resources\SuggestedIdentifier;
use App\Filament\Resources\ValueSelectionTable;
use App\Models\Attribute;
use App\Models\Value;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AttributeForm
{
    public static function configure(Schema $schema): Schema
    {
        Gate::authorize('manage-catalog');
        $tagOptions = Value::tagVocabulary();
        $attribute = $schema->getRecord();

        return $schema->columns(1)->components([Hidden::make('_workspace_owner')->default(fn (): string => 'new:'.Str::uuid())->dehydrated(false), Hidden::make('_active_owner')->default('')->dehydrated(false), AttributeBatchForm::masterPool(fn (): string => $schema->getStatePath()), Section::make()->key(fn (Section $component): string => 'shared-attribute::'.(filled($component->getStatePath()) ? $component->getStatePath().'::section' : 'section'), isInheritable: false)->contained(false)->compact()->columns(2)->extraAttributes(['class' => 'catalog-simple-form'])->schema([
            ...self::definitionFields(),
        ]), self::valuesSection(attribute: $attribute instanceof Attribute ? $attribute : null, tagOptions: $tagOptions, rootStatePath: fn (): string => $schema->getStatePath())]);
    }

    /** @param \Closure(Get): list<string>|null $reservedKeys
     * @return array<Component>
     */
    public static function definitionFields(?\Closure $reservedKeys = null): array
    {
        return [
            ...SuggestedIdentifier::fields(
                TextInput::make('label')->label(__('ui.fields.label'))->hintAction(FormHints::make(__('catalog.attributes.hints.labels_apply_wherever_this_attribute_is_included')))->required()->maxLength(255),
                TextInput::make('key')->label(__('catalog.fields.key'))->required()->maxLength(100)->unique(table: 'attributes', column: 'key', ignorable: fn (?Model $record): ?Attribute => $record instanceof Attribute && $record->exists ? $record : null, ignoreRecord: false)->hintAction(FormHints::make(__('catalog.attributes.hints.a_stable_identity_used_keys_require_reference'))),
                fn (string $label, array $reserved = []): string => SuggestedIdentifier::unique($label, Attribute::query(), 'key', $reserved), $reservedKeys,
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public static function editorDraft(array $state): array
    {
        $projectRows = function (array $rows, array $fields, bool $onlyPopulated = false): array {
            $projected = [];
            foreach ($rows as $key => $row) {
                if ($onlyPopulated && ! CreateMasterValues::isPopulated($row)) {
                    continue;
                }
                $values = [];
                foreach ($fields as $field) {
                    $values[$field] = $row[$field] ?? ($field === 'tags' ? [] : null);
                }
                $projected[] = [(string) $key, $values];
            }

            return $projected;
        };
        $masterFields = ['ref', 'label', 'semantic_id', 'tags', 'description'];

        return [
            'label' => $state['label'] ?? null,
            'key' => $state['key'] ?? null,
            'pending_options' => $projectRows($state['pending_options'] ?? [], ['ref', 'value_id', 'value_ref', 'code']),
            'master_values' => $projectRows($state['master_values'] ?? [], $masterFields, true),
            '_master_composer' => $projectRows($state['_master_composer'] ?? [], $masterFields, true),
            '_selected_value_ids' => $state['_selected_value_ids'] ?? [],
            '_selected_pending' => $state['_selected_pending'] ?? [],
        ];
    }

    public static function assertValuesStaged(Schema $schema): void
    {
        $state = $schema->getRawState();
        if (array_any($state['_master_composer'] ?? [], CreateMasterValues::isPopulated(...)) || ! empty($state['_selected_value_ids']) || ! empty($state['_selected_pending'])) {
            throw ValidationException::withMessages([$schema->getStatePath().'._master_composer' => __('attribute-options.unstaged')]);
        }
    }

    /** @param list<string> $tagOptions */
    public static function valuesSection(bool $standalone = false, ?Attribute $attribute = null, array $tagOptions = [], ?\Closure $rootStatePath = null): Section
    {
        if (! $standalone && $rootStatePath !== null) {
            return AttributeValueWorkspace::make('pending_options', $rootStatePath, fn (Get $get): string => $attribute?->exists ? 'attribute:'.$attribute->id : (string) $get('_workspace_owner'), $attribute, $tagOptions);
        }

        return Section::make(__('attribute-options.heading'))->key('attribute-values')->contained(false)->compact()->description(__($standalone ? 'admin-ux.add_values_help' : 'attribute-options.help'))
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
            ]);
    }
}
