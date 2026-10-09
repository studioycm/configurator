<?php

namespace App\Filament\Resources;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\ToggleButtons;
use Filament\QueryBuilder\Constraints\NumberConstraint;
use Filament\QueryBuilder\Constraints\TextConstraint;
use Filament\QueryBuilder\Forms\Components\RuleBuilder;
use Filament\Schemas\Components\StateCasts\OptionStateCast;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Enums\ColumnManagerLayout;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Js;

class TablePresentation
{
    /** @param list<string> $frequentActions */
    public static function configure(Table $table, string $key, bool $narrow = false, array $frequentActions = []): Table
    {
        if (in_array($key, ['values', 'attributes', 'options', 'attribute-options', 'configurators', 'groups', 'configurator-attributes', 'configurator-options', 'configurator-rules'], true)) {
            $table->toolbarActions([BatchEditActions::group($key)]);
        }
        StatusActions::configure($table, $key);
        $component = $table->getLivewire();
        $configuratorWorkspace = method_exists($component, 'workspaceVisibilityActions');
        if ($configuratorWorkspace) {
            $table->selectable(fn (): bool => $component->showSelection);
            $table->authorizeReorder(fn (): bool => auth()->user()?->can('manage-catalog') ?? false);
        }
        if (! method_exists($component, 'scopedSearchColumns')) {
            $table->pushFilters([Filter::make('workspaceSearch')->schema([Hidden::make('scope')->default('all')])->indicateUsing(fn (array $data): array => [])]);
            $table->searchUsing(function (Builder $query, string $search) use ($table, $component): void {
                $scope = $component->tableFilters['workspaceSearch']['scope'] ?? 'all';
                if (! is_string($scope) || ! array_key_exists($scope, self::searchColumns($table))) {
                    $query->whereRaw('1 = 0');

                    return;
                }
                $words = $table->shouldSplitSearchTerms() ? array_filter(str_getcsv(preg_replace('/(\\s|\\x{3164}|\\x{1160})+/u', ' ', $search), separator: ' ', escape: '\\'), fn ($word): bool => filled($word)) : [$search];
                foreach ($words as $word) {
                    $query->where(function (Builder $nested) use ($table, $scope, $word): void {
                        $isFirst = true;
                        foreach ($table->getColumns() as $column) {
                            if ($column->isGloballySearchable() && ($scope === 'all' || $column->getName() === $scope)) {
                                $column->applySearchConstraint($nested, $word, $isFirst);
                            }
                        }
                    });
                }
            });
        }
        $context = method_exists($component, 'getOwnerRecord') ? $component->getOwnerRecord()->getKey() : ($component->parentId ?? $component->tableArguments['owner_id'] ?? 'list');
        $widthKey = $key.':'.$context.':'.($component->listKey ?? '');
        $table->extraAttributes(['data-width-table' => $widthKey], merge: true);
        foreach ($table->getColumns() as $column) {
            $column->extraHeaderAttributes(['data-width-column' => $column->getName(), 'data-width-label' => (string) $column->getLabel()], merge: true);
            $column->extraCellAttributes(['data-width-column' => $column->getName()], merge: true);
        }
        if ($key === 'configurator-rules') {
            $table->pushFilters([
                SelectFilter::make('kind')->label('Rule kind')->options(['Mapping' => 'Mapping', 'Advanced' => 'Advanced']),
                SelectFilter::make('is_active')->label('Rule activation')->options(['1' => 'Enabled', '0' => 'Disabled']),
            ]);
        }
        self::configureQuickFilters($table);
        $constraints = [];
        foreach ($table->getColumns() as $column) {
            if ($column->isSearchable() && ! str_starts_with($key, 'array-')) {
                $constraints[] = $column->getName() === 'id'
                    ? NumberConstraint::make('id')->label('ID')
                    : TextConstraint::make($column->getName())->label((string) $column->getLabel());
            }
        }
        if ($constraints !== []) {
            $table->pushFilters([QueryBuilder::make('constraints')->label('Combined constraints')->constraints($constraints)->maxRules(20)->maxNestingDepth(3)
                ->schema(fn (QueryBuilder $filter): array => [RuleBuilder::make('rules')->label($filter->getLabel())
                    ->hintAction(FormHints::make('Combine field conditions using AND or OR.'))
                    ->constraints($filter->getConstraints())->blockPickerColumns($filter->getConstraintPickerColumns())->blockPickerWidth($filter->getConstraintPickerWidth())
                    ->maxRules($filter->getMaxRules())->maxNestingDepth($filter->getMaxNestingDepth())])]);
        }
        foreach ($table->getFlatRecordActions() as $action) {
            $action->iconButton();
            if (! $action->hasTooltip()) {
                $action->tooltip(fn (Action $action): string => (string) $action->getLabel());
            }
            $action->icon($action->getIcon() ?? match ($action->getName()) {
                'remove', 'delete' => Heroicon::OutlinedTrash,
                'openCatalog' => Heroicon::OutlinedArrowTopRightOnSquare,
                'moveUp' => Heroicon::OutlinedArrowUp,
                'moveDown' => Heroicon::OutlinedArrowDown,
                default => Heroicon::OutlinedPencilSquare,
            });
        }
        if ($narrow) {
            $headerActions = $table->getHeaderActions();
            $directActions = [];
            $remainingActions = [];
            foreach ($headerActions as $headerAction) {
                $actionNames = $headerAction instanceof ActionGroup ? array_keys($headerAction->getFlatActions()) : [$headerAction->getName()];
                if (array_intersect($actionNames, $frequentActions) !== []) {
                    $directActions[] = $headerAction->iconButton()->icon($headerAction->getIcon() ?? Heroicon::OutlinedPlus)->tooltip($headerAction->getLabel());
                } else {
                    $remainingActions[] = $headerAction;
                }
            }
            $selectionActions = $table->getToolbarActions();
            $table->toolbarActions([]);
            foreach ($selectionActions as $selectionAction) {
                $remainingActions[] = $selectionAction->dropdown(false);
            }
            if ($configuratorWorkspace) {
                array_push($remainingActions, ...$component->workspaceVisibilityActions());
            } elseif (filled($table->getReorderColumn()) && ! method_exists($component, 'rowOrderingActions')) {
                $table->reorderRecordsTriggerAction(fn (Action $action): Action => $action->extraAttributes(['class' => 'catalog-reorder-trigger'], merge: true));
                $remainingActions[] = $table->getReorderRecordsTriggerAction(false)->label('Reorder rows')->visible(fn (): bool => $table->isReorderable());
            }
            if (filled($table->getReorderColumn()) && method_exists($component, 'rowOrderingActions')) {
                $table->reorderRecordsTriggerAction(fn (Action $action): Action => $action->extraAttributes(['style' => 'display: none'], merge: true));
                array_push($directActions, ...$component->rowOrderingActions());
            }
            if ($remainingActions !== []) {
                $menu = ActionGroup::make($remainingActions)->label('More actions')->icon(Heroicon::OutlinedEllipsisHorizontal)->iconButton()->tooltip('More actions');
                if (collect($menu->getFlatActions())->every(fn (Action $action): bool => $action instanceof BulkAction)) {
                    $menu->extraAttributes(['x-cloak' => true, 'x-show' => 'getSelectedRecordsCount()']);
                }
                $directActions[] = $menu;
            }
            $table->headerActions($directActions);
        }

        return $table->extraAttributes(['class' => 'catalog-workspace-table'.($narrow ? ' catalog-narrow-table' : ''), 'data-workspace-table' => $key, 'data-reordering' => (int) $component->isTableReordering], merge: true)
            ->header(view('filament.resources.table-native-header'))
            ->searchable(false)->searchPlaceholder('Search')->deselectAllRecordsWhenFiltered(false)
            ->filtersLayout(FiltersLayout::Modal)->filtersFormColumns(1)->deferFilters()
            ->filtersTriggerAction(fn (Action $action): Action => $action->label('Filters')->iconButton()->tooltip('Filters')->slideOver()->stickyModalHeader()->stickyModalFooter())
            ->filtersResetAction(fn (Action $action): Action => $action->label('Clear filters'))
            ->reorderableColumns()->columnManagerLayout(ColumnManagerLayout::Modal)->persistColumnsInSession()
            ->columnManagerTriggerAction(fn (Action $action): Action => $action->label('Columns')->iconButton()->tooltip('Columns')->slideOver()->stickyModalHeader()->stickyModalFooter()->extraModalFooterActions([$table->getColumnManagerApplyAction()->alpineClickHandler("workspaceColumnManager(\$el, 'applyTableColumnManager'); close()"), Action::make('resetColumnManager')->label('Reset columns')->color('danger')->alpineClickHandler("workspaceColumnManager(\$el, 'resetDeferredColumns'); \$wire.resetTableColumnManager(); ".'$dispatch('.Js::from('table-widths-reset').', '.Js::from(['table' => $widthKey]).')'), Action::make('resetWidths')->label('Reset widths')->color('gray')->alpineClickHandler('$dispatch('.Js::from('table-widths-reset').', '.Js::from(['table' => $widthKey]).')')]));
    }

    private static function configureQuickFilters(Table $table): void
    {
        foreach ($table->getFilters() as $filter) {
            if (! $filter instanceof SelectFilter || $filter->hasSchema()) {
                continue;
            }
            $filter->schema(function () use ($filter): array {
                $field = $filter->getFormField();
                $limit = $filter->getOptionsLimit();
                try {
                    $options = (clone $field)->model($filter->getTable()->getModel())->preload()->searchable()->optionsLimit(7)->getOptions();
                } finally {
                    $filter->optionsLimit($limit);
                }
                if (empty($options) || count($options) > 6 || count(array_filter($options, 'is_array')) > 0) {
                    return [$field];
                }
                $multiple = $filter->isMultiple();

                $buttons = ToggleButtons::make($multiple ? 'values' : 'value')->label($filter->getLabel())->inline()->multiple($multiple)
                    ->options($multiple ? $options : ['' => 'All'] + $options)
                    ->default($filter->getDefaultState() ?? ($multiple ? [] : ''))
                    ->afterStateHydrated(function (ToggleButtons $component, mixed $state) use ($multiple): void {
                        if (! $multiple && $state === null) {
                            $component->state('');
                        }
                    });
                if (! $multiple) {
                    $buttons->stateCast(new OptionStateCast(isNullable: false));
                }

                return [$buttons];
            });
        }
    }

    /** @return array<string, string> */
    public static function searchColumns(Table $table): array
    {
        $fields = ['all' => 'All fields'];
        foreach ($table->getColumns() as $column) {
            if ($column->isGloballySearchable()) {
                $fields[$column->getName()] = (string) $column->getLabel();
            }
        }

        return $fields;
    }
}
