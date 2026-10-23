<?php

namespace App\Livewire\Catalog;

use App\Actions\BatchCatalogChanges;
use App\Actions\ManageValueTags;
use App\Filament\Resources\InteractsWithScopedTableSearch;
use App\Filament\Resources\TablePresentation;
use App\Filament\Resources\ValueTagFilter;
use App\Models\Value;
use Filament\Actions\BulkAction;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class TagValues extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithScopedTableSearch, InteractsWithTable {
        InteractsWithTable::removeTableFilter as protected removeNativeTableFilter;
        InteractsWithScopedTableSearch::applyGlobalSearchToTableQuery insteadof InteractsWithTable;
        InteractsWithScopedTableSearch::updatedTableFilters insteadof InteractsWithTable;
        InteractsWithScopedTableSearch::removeTableFilter insteadof InteractsWithTable;
        InteractsWithScopedTableSearch::resetTableColumnManager insteadof InteractsWithTable;
        InteractsWithScopedTableSearch::getTableColumnsSessionKey insteadof InteractsWithTable;
        InteractsWithScopedTableSearch::getHasReorderedTableColumnsSessionKey insteadof InteractsWithTable;
        InteractsWithScopedTableSearch::getTableSearchSessionKey insteadof InteractsWithTable;
        InteractsWithScopedTableSearch::getTableColumnSearchesSessionKey insteadof InteractsWithTable;
        InteractsWithScopedTableSearch::getTableSortSessionKey insteadof InteractsWithTable;
        InteractsWithScopedTableSearch::getTableFiltersSessionKey insteadof InteractsWithTable;
        InteractsWithScopedTableSearch::getTablePerPageSessionKey insteadof InteractsWithTable;
        InteractsWithScopedTableSearch::getTableGroupingSessionKey insteadof InteractsWithTable;
    }

    #[Locked]
    public ?string $tag = null;

    #[Locked]
    public bool $picking = false;

    #[Locked]
    public string $listKey = 'tag-members';

    #[Locked]
    public ?string $globalOperation = null;

    /** @var array{target: ?string} */
    public array $globalData = ['target' => null];

    /** @var array<string, mixed> */
    #[Locked]
    public array $tagPreview = [];

    /** @var list<int> */
    #[Locked]
    public array $assignmentIds = [];

    /** @var array{mode: string, tags: list<string>} */
    public array $assignmentData = ['mode' => 'add', 'tags' => []];

    /** @var array{name: string} */
    public array $newTagData = ['name' => ''];

    public function mount(?string $tag = null): void
    {
        Gate::authorize('manage-catalog');
        $this->tag = $tag;
        $this->picking = $tag === null;
        $this->listKey = $this->picking ? 'tag-picker' : 'tag-members';
        $this->nameForm->fill();
    }

    public function table(Table $table): Table
    {
        $columns = [
            TextColumn::make('label')->label(__('catalog.fields.ui_name'))->searchable()->sortable()->description(fn (Value $record): string => $record->semantic_id),
        ];
        $columns[] = $this->picking
            ? TextColumn::make('tags')->label(__('catalog.fields.tags'))->badge()
            : ViewColumn::make('tags')->label(__('catalog.fields.tags'))->view('filament.pages.tag-value-cell');

        $action = $this->picking
            ? BulkAction::make('assignTag')->label(__($this->tag === null ? 'tags.create_assign' : 'tags.add_selected'))
                ->action(fn (Collection $records) => $this->assignTag($records))->deselectRecordsAfterCompletion()
            : BulkAction::make('editTags')->label(__('tags.edit_selected'))->action(fn (Collection $records) => $this->beginBulk($records));

        return TablePresentation::configure($table->query(fn (): Builder => $this->valueQuery())->columns($columns)
            ->heading('')->defaultSort('label')->paginationPageOptions([10, 25, 50])->queryStringIdentifier('tag-values')
            ->filters($this->picking ? [ValueTagFilter::make()] : [])
            ->toolbarActions([$action->authorize('manage-catalog')->extraAttributes(['data-tag-requires-clean' => true])]), 'tag-workspace');
    }

    private function valueQuery(): Builder
    {
        Gate::authorize('manage-catalog');

        return Value::query()->when($this->tag !== null, fn (Builder $query): Builder => $this->picking
            ? $query->where(fn (Builder $values): Builder => $values->whereJsonDoesntContain('tags', $this->tag)->orWhereNull('tags'))
            : $query->whereJsonContains('tags', $this->tag));
    }

    #[Computed]
    public function usage(): int
    {
        return $this->tag === null ? 0 : Value::query()->whereJsonContains('tags', $this->tag)->count();
    }

    public function nameForm(Schema $schema): Schema
    {
        return $schema->statePath('newTagData')->components([
            TextInput::make('name')->label(__('tags.tag_name'))->required()->maxLength(80),
        ]);
    }

    public function globalForm(Schema $schema): Schema
    {
        $field = $this->globalOperation === 'merge'
            ? Select::make('target')->label(__('tags.destination'))->options(fn (): array => array_diff_key(Value::tagOptions(), [$this->tag => true]))->searchable()->required()
            : TextInput::make('target')->label(__('tags.new_name'))->required()->maxLength(80);

        return $schema->statePath('globalData')->components($this->globalOperation === 'remove' ? [] : [
            $field->live(debounce: 350)->afterStateUpdated(fn () => $this->refreshGlobalPreview()),
        ]);
    }

    public function assignmentForm(Schema $schema): Schema
    {
        return $schema->statePath('assignmentData')->components([
            Select::make('mode')->label(__('tags.mode'))->options(['add' => __('tags.add'), 'remove' => __('tags.remove_selected'), 'set' => __('tags.replace')])
                ->required()->rules([Rule::in(['add', 'remove', 'set'])])->live()->afterStateUpdated(fn () => $this->refreshAssignmentPreview()),
            TagsInput::make('tags')->label(__('catalog.fields.tags'))->placeholder(__('tags.input_placeholder'))->trim()->default([])->suggestions(fn (): array => array_keys(Value::tagOptions()))
                ->rules(['array', 'list', 'max:30'])->nestedRecursiveRules(['required', 'string', 'max:80', 'distinct:strict'])
                ->live()->afterStateUpdated(fn () => $this->refreshAssignmentPreview()),
        ]);
    }

    public function showPicker(bool $picking = true): void
    {
        Gate::authorize('manage-catalog');
        $this->picking = $this->tag === null || $picking;
        $this->listKey = $this->picking ? 'tag-picker' : 'tag-members';
        $this->globalOperation = null;
        $this->cancelAssignment();
        $this->resetTable();
        $this->resetTableFiltersForm();
    }

    public function beginGlobal(string $operation): void
    {
        Gate::authorize('manage-catalog');
        abort_unless($this->tag !== null && in_array($operation, ['rename', 'merge', 'remove'], true), 422);
        $this->globalOperation = $operation;
        $this->tagPreview = [];
        $this->cacheSchema('globalForm')->fill(['target' => null]);
        $this->cancelAssignment();
        $this->refreshGlobalPreview();
    }

    public function cancelGlobal(): void
    {
        Gate::authorize('manage-catalog');
        $this->globalOperation = null;
        $this->tagPreview = [];
        $this->resetValidation();
    }

    public function refreshGlobalPreview(): void
    {
        Gate::authorize('manage-catalog');
        $this->tagPreview = [];
        $this->resetValidation();
        if ($this->globalOperation === null || ($this->globalOperation !== 'remove' && blank($this->globalData['target'] ?? null))) {
            return;
        }
        try {
            $this->tagPreview = app(ManageValueTags::class)->preview(auth()->user(), $this->globalOperation, $this->tag, $this->globalData['target'] ?? null);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $key => $messages) {
                $this->addError($key === 'target' ? 'globalData.target' : $key, $messages[0]);
            }
        }
    }

    public function applyGlobal(): void
    {
        Gate::authorize('manage-catalog');
        if ($this->globalOperation === null || empty($this->tagPreview['token'])) {
            throw ValidationException::withMessages(['preview' => __('tags.complete_change')]);
        }
        $target = $this->globalOperation === 'remove' ? null : $this->globalForm->getState()['target'];
        try {
            app(ManageValueTags::class)->apply(auth()->user(), $this->globalOperation, $this->tag, $target, $this->tagPreview['token']);
        } catch (ValidationException $exception) {
            $this->tagPreview = [];
            throw $exception;
        }
        $this->globalOperation = null;
        $this->tagPreview = [];
        $this->dispatch('catalog-tags-updated');
        if ($target !== null) {
            $this->tag = trim($target);
            $this->dispatch('tag-workspace-selected', tag: $this->tag);
        }
        $this->flushCachedTableRecords();
        Notification::make()->title(__('tags.saved'))->success()->send();
    }

    private function beginBulk(Collection $records): void
    {
        Gate::authorize('manage-catalog');
        $this->assignmentIds = $records->modelKeys();
        $this->globalOperation = null;
        $this->assignmentForm->fill(['mode' => 'add', 'tags' => []]);
        $this->batchPreview = [];
    }

    public function refreshAssignmentPreview(): void
    {
        Gate::authorize('manage-catalog');
        $this->batchPreview = [];
        $this->resetValidation();
        if ($this->assignmentIds === [] || (($this->assignmentData['mode'] ?? 'add') !== 'set' && empty($this->assignmentData['tags']))) {
            return;
        }
        try {
            $this->batchPreview = app(BatchCatalogChanges::class)->preview(auth()->user(), 'values', $this->assignmentIds, $this->assignmentIntent());
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $key => $messages) {
                $this->addError($key, $messages[0]);
            }
        }
    }

    public function applyAssignment(): void
    {
        Gate::authorize('manage-catalog');
        $this->assignmentForm->getState();
        if (empty($this->batchPreview['token'])) {
            throw ValidationException::withMessages(['preview' => __('tags.complete_change')]);
        }
        app(BatchCatalogChanges::class)->apply(auth()->user(), 'values', $this->assignmentIds, $this->assignmentIntent(), $this->batchPreview['token']);
        $this->cancelAssignment();
        $this->deselectAllTableRecords();
        $this->dispatch('catalog-tags-updated');
        $this->flushCachedTableRecords();
        Notification::make()->title(__('tags.saved'))->success()->send();
    }

    public function cancelAssignment(): void
    {
        Gate::authorize('manage-catalog');
        $this->assignmentIds = [];
        $this->batchPreview = [];
        $this->resetValidation();
    }

    /** @return array{operation: string, changes: list<array{field: string, mode: string, value: array}>} */
    private function assignmentIntent(): array
    {
        return ['operation' => 'edit', 'changes' => [['field' => 'tags', 'mode' => $this->assignmentData['mode'], 'value' => $this->assignmentData['tags'] ?? []]]];
    }

    private function assignTag(Collection $records): void
    {
        Gate::authorize('manage-catalog');
        $tag = $this->tag;
        if ($tag === null) {
            $tag = trim($this->nameForm->getState()['name']);
            if (array_key_exists($tag, Value::tagOptions())) {
                throw ValidationException::withMessages(['newTagData.name' => __('tags.use_existing')]);
            }
        }
        $ids = $records->modelKeys();
        $intent = ['operation' => 'edit', 'changes' => [['field' => 'tags', 'mode' => 'add', 'value' => [$tag]]]];
        $action = app(BatchCatalogChanges::class);
        $preview = $action->preview(auth()->user(), 'values', $ids, $intent);
        $action->apply(auth()->user(), 'values', $ids, $intent, $preview['token']);
        $this->tag = $tag;
        $this->showPicker(false);
        $this->dispatch('tag-workspace-selected', tag: $tag);
        $this->dispatch('catalog-tags-updated');
        Notification::make()->title(__('tags.saved'))->success()->send();
    }

    #[On('catalog-tags-updated')]
    public function refreshTags(): void
    {
        Gate::authorize('manage-catalog');
        unset($this->usage);
        $this->flushCachedTableRecords();
    }

    public function render(): View
    {
        return view('livewire.catalog.tag-values');
    }
}
