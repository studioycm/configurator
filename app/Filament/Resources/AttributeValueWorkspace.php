<?php

namespace App\Filament\Resources;

use App\Actions\CreateMasterValues;
use App\Filament\Forms\Components\WorkspaceTableSelect;
use App\Filament\Resources\Values\Schemas\ValueForm;
use App\Models\Attribute;
use App\Models\Value;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AttributeValueWorkspace
{
    /** @param Closure():string $rootStatePath
     * @param  Closure(Get):string  $ownerRef
     * @param  list<string>  $tagOptions
     * @param  list<TableColumn>  $extraColumns
     * @param  list<Component>  $extraFields
     */
    public static function make(string $associationName, Closure $rootStatePath, Closure $ownerRef, ?Attribute $attribute = null, array $tagOptions = [], array $extraColumns = [], array $extraFields = []): Section
    {
        $active = fn (Get $get): bool => $get($rootStatePath().'._active_owner', isAbsolute: true) === $ownerRef($get);

        return Section::make(__('attribute-options.heading'))->key('attribute-values')->contained(false)->compact()
            ->description(__('attribute-options.help'))
            ->headerActions([
                Action::make('openValues')->button()->label(__('attribute-options.open_workspace'))->authorize('manage-catalog')
                    ->action(function (Section $component, Get $get, Set $set) use ($ownerRef, $rootStatePath): void {
                        Gate::authorize('manage-catalog');
                        $set('_selected_value_ids', $get('_selected_value_ids') ?? []);
                        $set('_selected_pending', $get('_selected_pending') ?? []);
                        $set($rootStatePath().'._active_owner', $ownerRef($get), isAbsolute: true);
                        if ($get('_master_composer') === null) {
                            $set('_master_composer', [(string) Str::uuid() => ['ref' => 'new:'.Str::uuid(), 'label' => null, 'semantic_id' => null, 'tags' => [], 'description' => null, '_suggested_semantic_id' => false]]);
                        }
                        $component->getRootContainer()->clearCachedChildSchemas();
                    }),
            ])
            ->registerActions([
                Action::make('useSelected')->button()->label(__('attribute-options.use_selected'))->authorize('manage-catalog')->visible($active)
                    ->disabled(fn (Get $get): bool => empty($get('_selected_value_ids')) && empty($get('_selected_pending')))
                    ->action(function (Section $component, Get $get, Set $set) use ($associationName, $rootStatePath, $attribute): void {
                        Gate::authorize('manage-catalog');
                        $ids = $get('_selected_value_ids') ?? [];
                        Validator::make(['ids' => $ids], ['ids' => ['array', 'list', 'max:500'], 'ids.*' => ['integer', 'min:1', 'distinct']])->validate();
                        $pending = $get($associationName) ?? [];
                        $existing = Value::query()->whereIn('id', $ids)->when($attribute, fn ($query) => $query->whereDoesntHave('options', fn ($options) => $options->where('attribute_id', $attribute->id)))->get()->keyBy('id');
                        foreach ($ids as $id) {
                            if (! isset($existing[$id])) {
                                throw ValidationException::withMessages([self::path($component, '_selected_value_ids') => __('catalog.creation.missing_ref')]);
                            }
                            if (! in_array((int) $id, array_map(intval(...), array_column($pending, 'value_id')), true)) {
                                $pending[(string) Str::uuid()] = ['ref' => 'new:'.Str::uuid(), 'value_id' => (int) $id, 'name' => $existing[$id]->labelWithSemanticId(), 'code' => ''];
                            }
                        }
                        $pool = $get($rootStatePath().'.master_values', isAbsolute: true) ?? [];
                        $pool = array_column($pool, null, 'ref');
                        foreach ($get('_selected_pending') ?? [] as $ref) {
                            if (! is_string($ref) || ! isset($pool[$ref])) {
                                throw ValidationException::withMessages([self::path($component, '_selected_pending') => __('catalog.creation.missing_ref')]);
                            }
                            if (! in_array($ref, array_column($pending, 'value_ref'), true)) {
                                $pending[(string) Str::uuid()] = ['ref' => 'new:'.Str::uuid(), 'value_ref' => $ref, 'name' => $pool[$ref]['label'].' ('.$pool[$ref]['semantic_id'].')', 'code' => ''];
                            }
                        }
                        if (count($pending) > 500) {
                            throw ValidationException::withMessages([self::path($component, $associationName) => __('catalog.creation.limit')]);
                        }
                        $set($associationName, $pending);
                        $set('_selected_value_ids', []);
                        $set('_selected_pending', []);
                        $component->callAfterStateUpdated(shouldBubbleToParents: false);
                    }),
                Action::make('createAndUse')->button()->label(__('attribute-options.add_new'))->authorize('manage-catalog')->visible($active)
                    ->action(function (Section $component, Get $get, Set $set) use ($associationName, $rootStatePath): void {
                        Gate::authorize('manage-catalog');
                        $composer = $component->getChildSchema()->getComponentByStatePath('_master_composer');
                        $pool = $get($rootStatePath().'.master_values', isAbsolute: true) ?? [];
                        $pending = $get($associationName) ?? [];
                        $rows = [];
                        if (count($composer->getRawState() ?? []) > 500) {
                            throw ValidationException::withMessages([$composer->getStatePath() => __('catalog.creation.limit')]);
                        }
                        foreach ($composer->getRawState() ?? [] as $key => $row) {
                            if (! CreateMasterValues::isPopulated($row)) {
                                continue;
                            }
                            $validated = $composer->getItemState($key);
                            $ref = $validated['ref'] ?? null;
                            if (! is_string($ref) || ! str_starts_with($ref, 'new:') || ! Str::isUuid(substr($ref, 4)) || in_array($ref, array_column([...$pool, ...$rows], 'ref'), true)) {
                                throw ValidationException::withMessages([$composer->getStatePath().'.'.$key.'.ref' => __('catalog.creation.invalid_ref')]);
                            }
                            if (in_array($validated['semantic_id'], array_column([...$pool, ...$rows], 'semantic_id'), true)) {
                                throw ValidationException::withMessages([$composer->getStatePath().'.'.$key.'.semantic_id' => __('validation.unique', ['attribute' => __('catalog.fields.semantic_id')])]);
                            }
                            $rows[] = array_intersect_key($validated, array_flip(['ref', 'label', 'semantic_id', 'tags', 'description']));
                        }
                        if ($rows === []) {
                            throw ValidationException::withMessages([$composer->getStatePath() => __('catalog.creation.required')]);
                        }
                        if (count($pool) + count($rows) > 500 || count($pending) + count($rows) > 500) {
                            throw ValidationException::withMessages([$composer->getStatePath() => __('catalog.creation.limit')]);
                        }
                        foreach ($rows as $row) {
                            $pool[(string) Str::uuid()] = $row;
                            $pending[(string) Str::uuid()] = ['ref' => 'new:'.Str::uuid(), 'value_ref' => $row['ref'], 'name' => $row['label'].' ('.$row['semantic_id'].')', 'code' => ''];
                        }
                        $set($rootStatePath().'.master_values', $pool, isAbsolute: true);
                        $set($associationName, $pending);
                        $set('_master_composer', []);
                        $component->callAfterStateUpdated(shouldBubbleToParents: false);
                    }),
                Action::make('discardComposer')->button()->label(__('attribute-options.clear_entries'))->color('gray')->authorize('manage-catalog')
                    ->visible(fn (Get $get): bool => $active($get) && (count($get('_master_composer') ?? []) > 1 || array_any($get('_master_composer') ?? [], CreateMasterValues::isPopulated(...))))
                    ->requiresConfirmation(fn (Get $get): bool => array_any($get('_master_composer') ?? [], CreateMasterValues::isPopulated(...)))
                    ->action(function (Set $set): void {
                        Gate::authorize('manage-catalog');
                        $set('_master_composer', []);
                    }),
            ])
            ->schema(function (Section $component, Get $get) use ($active, $associationName, $rootStatePath, $ownerRef, $attribute, $tagOptions, $extraColumns, $extraFields): array {
                $components = [self::associationTable($associationName, $extraColumns, $extraFields)];
                if (! $active($get)) {
                    return $components;
                }
                Gate::authorize('manage-catalog');
                $picker = WorkspaceTableSelect::make('_selected_value_ids')->label(__('attribute-options.selected'))->multiple()->default([])->live()->dehydrated(false)->selectRowsByClick()
                    ->key(fn (Get $get): string => 'master-picker-'.$ownerRef($get).'-'.md5(json_encode(array_values(array_column($get($associationName) ?? [], 'value_id')))))
                    ->tableConfiguration(ValueSelectionTable::class)->tableArguments(fn (Get $get): array => ['owner_id' => $ownerRef($get), 'attribute_id' => $attribute?->id, 'excluded_ids' => array_values(array_column($get($associationName) ?? [], 'value_id'))]);
                $pendingPicker = CheckboxList::make('_selected_pending')->label(__('attribute-options.new_in_batch'))->searchable()->live()->dehydrated(false)
                    ->options(function (Get $get) use ($rootStatePath, $associationName): array {
                        $used = array_column($get($associationName) ?? [], 'value_ref');
                        $options = [];
                        foreach ($get($rootStatePath().'.master_values', isAbsolute: true) ?? [] as $row) {
                            if (! in_array($row['ref'], $used, true)) {
                                $options[$row['ref']] = $row['label'].' ('.$row['semantic_id'].')';
                            }
                        }

                        return $options;
                    })->visible(fn (CheckboxList $component): bool => $component->getOptions() !== [])->helperText(__('attribute-options.unreferenced'));
                $composer = ValueForm::batch('_master_composer', function (Get $get) use ($rootStatePath): array {
                    $pool = $get($rootStatePath().'.master_values', isAbsolute: true) ?? [];

                    return array_values(array_column($pool, 'semantic_id'));
                }, $tagOptions)->dehydrated(false);

                $components[] = Tabs::make(__('attribute-options.value_sources'))->key('value-sources', isInheritable: false)->tabs([
                    Tab::make(__('attribute-options.existing_masters'))->schema([
                        Section::make()->key('existing-master-selection', isInheritable: false)->contained(false)->schema([$picker, $pendingPicker])
                            ->footer([$component->getAction('useSelected')]),
                    ]),
                    Tab::make(__('attribute-options.new_masters'))->schema([
                        Section::make()->key('new-master-entry', isInheritable: false)->contained(false)->schema([$composer])
                            ->footer([$component->getAction('createAndUse'), $component->getAction('discardComposer')]),
                    ]),
                ]);

                return $components;
            });
    }

    /** @param list<TableColumn> $extraColumns
     * @param  list<Component>  $extraFields
     */
    public static function associationTable(string $name, array $extraColumns = [], array $extraFields = []): Repeater
    {
        return Repeater::make($name)->hiddenLabel()->default([])->maxItems(500)->addable(false)->reorderable(false)->compact()
            ->table([TableColumn::make(__('catalog.fields.value_ui_name')), TableColumn::make(__('catalog.fields.code')), ...$extraColumns])
            ->schema([
                Hidden::make('ref')->default(fn (): string => 'new:'.Str::uuid())->required(fn (Get $get): bool => filled($get('value_ref')))->dehydrated(fn (mixed $state): bool => $state !== null),
                Hidden::make('value_id')->dehydrated(fn (mixed $state): bool => $state !== null),
                Hidden::make('value_ref')->dehydrated(fn (mixed $state): bool => $state !== null),
                TextInput::make('name')->label(__('catalog.fields.value_ui_name'))->extraInputAttributes(['aria-label' => e(__('catalog.fields.value_ui_name'))])->readOnly()->dehydrated(false),
                TextInput::make('code')->label(__('catalog.fields.code'))->extraInputAttributes(['aria-label' => e(__('catalog.fields.code'))])->required()->length(2)->trim(false)->regex('/\A[A-Za-z0-9]{2}\z/D')->distinct()->unique(table: 'options', column: 'code', ignoreRecord: false)->hintAction(FormHints::make(__('attribute-options.code_help'))),
                ...$extraFields,
            ])->deleteAction(fn (Action $action): Action => $action->requiresConfirmation());
    }

    private static function path(Section $component, string $field): string
    {
        return $component->getContainer()->getStatePath().'.'.$field;
    }
}
