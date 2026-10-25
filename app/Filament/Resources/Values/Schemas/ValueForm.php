<?php

namespace App\Filament\Resources\Values\Schemas;

use App\Actions\CreateMasterValues;
use App\Filament\Resources\FormHints;
use App\Filament\Resources\SuggestedIdentifier;
use App\Models\Value;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Js;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class ValueForm
{
    public static function configure(Schema $schema): Schema
    {
        Gate::authorize('manage-catalog');

        return $schema->columns(1)->components([Section::make()->key(fn (Section $component): string => 'master-value::'.(filled($component->getStatePath()) ? $component->getStatePath().'::section' : 'section'), isInheritable: false)->contained(false)->compact()->columns(['default' => 1, 'md' => 2])->extraAttributes(['class' => 'catalog-simple-form'])->schema(self::fields(tagOptions: Value::tagVocabulary()))]);
    }

    /**
     * @param  Closure(Get): list<string>|null  $reservedIdentifiers
     * @param  list<string>  $tagOptions
     * @return array<Hidden|TextInput|TagsInput|Textarea>
     */
    public static function fields(?Closure $reservedIdentifiers = null, array $tagOptions = []): array
    {
        return [
            ...SuggestedIdentifier::fields(
                TextInput::make('label')->label(__('catalog.fields.ui_name'))->hintAction(FormHints::make(__('catalog.master_values.hints.reuse_an_existing_meaning_where_appropriate_equal')))->required()->maxLength(255),
                TextInput::make('semantic_id')->label(__('catalog.fields.semantic_id'))->required()->maxLength(100)
                    ->regex('/\A[a-z0-9]+(?:_[a-z0-9]+)*\z/D')->unique(table: 'values', column: 'semantic_id', ignorable: fn (?Model $record): ?Value => $record instanceof Value && $record->exists ? $record : null, ignoreRecord: false)
                    ->hintAction(FormHints::make(__('catalog.master_values.help.semantic_id'))),
                Value::suggestSemanticId(...), $reservedIdentifiers,
            ),
            TagsInput::make('tags')->columnSpanFull()->label(__('catalog.fields.tags'))->placeholder(__('tags.input_placeholder'))->default([])->trim()->nestedRecursiveRules(['required', 'string', 'max:80', 'distinct:strict'])->rules(['array', 'list', 'max:30'])->suggestions($tagOptions)->hintAction(FormHints::make(__('catalog.master_values.hints.press_enter_after_each_tag_tags_help'))),
            Textarea::make('description')->label(__('catalog.fields.specification'))->rows(3)->maxLength(5000)->columnSpanFull(),
        ];
    }

    /** @param Closure(Get): list<string>|null $reservedIdentifiers
     * @param  list<string>  $tagOptions
     */
    public static function batch(string $name, ?Closure $reservedIdentifiers = null, array $tagOptions = []): Group
    {
        $reserved = function (Get $get) use ($reservedIdentifiers): array {
            $rows = $get('../') ?? [];
            $ref = $get('ref');
            $identifiers = [];
            foreach (is_array($rows) ? $rows : [] as $row) {
                if (is_array($row) && ($row['ref'] ?? null) !== $ref && ($reservedIdentifier = SuggestedIdentifier::reservation($row, 'semantic_id')) !== null) {
                    $identifiers[] = $reservedIdentifier;
                }
            }

            return [...$identifiers, ...($reservedIdentifiers ? $reservedIdentifiers($get) : [])];
        };
        $fields = [Hidden::make('ref')->default(fn (): string => 'new:'.Str::uuid())->required(), ...self::fields($reserved, $tagOptions)];
        foreach ($fields as $field) {
            if ($field instanceof Hidden) {
                continue;
            }
            $field->extraFieldWrapperAttributes(['data-batch-field' => $field->getName()], merge: true)
                ->extraInputAttributes(['aria-label' => e((string) $field->getLabel())], merge: true);
            if ($field instanceof TextInput) {
                $field->required(fn (Get $get): bool => CreateMasterValues::isPopulated($get('') ?? []));
            }
            if ($field->getName() === 'semantic_id') {
                $field->unique(table: 'values', column: 'semantic_id', ignoreRecord: false);
            }
        }
        $repeater = Repeater::make($name)->hiddenLabel()->model(Value::class)
            ->schema(Schema::make()->operation('create')->components($fields))
            ->table([TableColumn::make(__('catalog.fields.ui_name')), TableColumn::make(__('catalog.fields.semantic_id')), TableColumn::make(__('catalog.fields.tags')), TableColumn::make(__('catalog.fields.specification'))])
            ->compact(false)->defaultItems(1)->maxItems(500)->reorderable(false)->cloneable(false)
            ->addActionLabel(__('catalog.creation.add_row'))
            ->addAction(fn (Action $action): Action => $action->extraAttributes(['data-master-batch-add' => true])->action(fn (Repeater $component) => self::appendRows($component, 1)));
        $repeater->hintActions(array_map(fn (int $count): Closure => fn (Repeater $component): Action => Action::make('add'.$count)->label(__('catalog.creation.add_rows', ['count' => $count]))->schemaComponent($component)->authorize('manage-catalog')
            ->disabled(fn (): bool => count($component->getRawState() ?? []) + $count > 500)
            ->action(fn (Repeater $component) => self::appendRows($component, $count)), [5, 10]));

        return Group::make([View::make('filament.resources.master-value-batch-columns'), $repeater])->columnSpanFull()
            ->extraAttributes(fn (Component $livewire, Group $component): array => [
                'data-master-value-batch' => true,
                'x-data' => 'masterValueBatch('.Js::from(['user' => (string) auth()->id(), 'context' => str_replace('\\', '-', $livewire::class), 'path' => $component->getChildSchema()->getComponents()[1]->getStatePath(), 'discardMessage' => __('catalog.creation.discard_heading')]).')',
                'x-bind:data-show-tags' => 'showTags', 'x-bind:data-show-specification' => 'showSpecification',
                'x-on:keydown' => 'keydown($event)',
                'x-on:master-value-batch-appended.window' => 'appended($event.detail)',
            ]);
    }

    private static function appendRows(Repeater $component, int $count): void
    {
        Gate::authorize('manage-catalog');
        $items = $component->getRawState() ?? [];
        if (! in_array($count, [1, 5, 10], true) || count($items) + $count > 500) {
            throw ValidationException::withMessages([$component->getStatePath() => __('catalog.creation.limit')]);
        }
        $newKeys = [];
        foreach (range(1, $count) as $offset) {
            $key = $component->generateUuid();
            $items[$key] = [];
            $newKeys[] = $key;
        }
        $component->rawState($items);
        foreach ($newKeys as $key) {
            $component->getChildSchema($key)->fill();
        }
        $component->collapsed(false, shouldMakeComponentCollapsible: false);
        $component->callAfterStateUpdated();
        $component->shouldPartiallyRenderAfterActionsCalled() ? $component->partiallyRender() : null;
        $component->getLivewire()->dispatch('master-value-batch-appended', path: $component->getStatePath(), field: $component->getStatePath().'.'.$newKeys[0].'.label', rowRef: $component->getRawState()[$newKeys[0]]['ref'], livewireId: $component->getLivewire()->getId());
    }
}
