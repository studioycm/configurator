<?php

namespace App\Filament\Resources;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;

abstract class SplitListRecords extends ListRecords
{
    use InteractsWithScopedTableSearch;

    protected string $view = 'filament.resources.split-list';

    #[Url(as: 'record')]
    public ?string $selectedRecord = null;

    protected bool $inlineCreationEnabled = false;

    #[Locked]
    public bool $isCreatingRecord = false;

    public function createRecord(): void
    {
        abort_unless($this->inlineCreationEnabled && static::getResource()::canCreate(), 403);
        $this->isCreatingRecord = true;
        $this->dispatch('catalog-editor-opened');
    }

    #[On('catalog-create-cancelled')]
    public function cancelCreation(?string $resource = null): void
    {
        if ($resource !== null && $resource !== static::getResource()) {
            return;
        }
        $this->isCreatingRecord = false;
    }

    #[On('catalog-record-created')]
    public function selectCreatedRecord(string $resource, string $record): void
    {
        if (! $this->isCreatingRecord || $resource !== static::getResource()) {
            return;
        }
        $this->selectRecord($record);
        $this->flushCachedTableRecords();
    }

    public function selectRecord(string $record): void
    {
        $resource = static::getResource();
        $model = $resource::getEloquentQuery()->findOrFail($record);
        abort_unless($resource::hasPage('edit') ? $resource::canEdit($model) : $resource::canView($model), 403);
        $this->isCreatingRecord = false;
        $this->selectedRecord = (string) $model->getKey();
        $this->dispatch('catalog-editor-opened');
    }

    /** @param list<int> $ids */
    public function reconcileBatchChanges(array $ids, bool $removed): void
    {
        Gate::authorize('manage-catalog');
        if ($removed && $this->selectedRecord !== null && in_array((int) $this->selectedRecord, $ids, true)) {
            $this->selectedRecord = null;
        }
    }

    public function editorComponent(): ?string
    {
        if ($this->isCreatingRecord) {
            abort_unless($this->inlineCreationEnabled && static::getResource()::canCreate(), 403);

            return static::getResource()::getPages()['create']->getPage();
        }
        if ($this->selectedRecord === null) {
            return null;
        }
        $resource = static::getResource();
        $record = $resource::getEloquentQuery()->find($this->selectedRecord);
        if (! $record) {
            return null;
        }
        $page = $resource::hasPage('edit') ? 'edit' : 'view';
        abort_unless($page === 'edit' ? $resource::canEdit($record) : $resource::canView($record), 403);

        return $resource::getPages()[$page]->getPage();
    }

    protected function makeTable(): Table
    {
        $table = parent::makeTable();
        $hasEditPage = static::getResource()::hasPage('edit');
        $otherActions = array_filter($table->getRecordActions(), fn (Action|ActionGroup $action): bool => ! ($action instanceof Action && in_array($action->getName(), ['edit', 'view'], true)));

        if ($this->inlineCreationEnabled) {
            $label = 'Create '.static::getResource()::getModelLabel();
            $table->headerActions([
                Action::make('create')->label($label)->iconButton()->icon(Heroicon::OutlinedPlus)->tooltip($label)
                    ->visible(fn (): bool => static::getResource()::canCreate())->disabled(fn (): bool => $this->isCreatingRecord)
                    ->extraAttributes(['data-editor-transition' => true])->action(fn () => $this->createRecord()),
                ...$table->getHeaderActions(),
            ]);
        }

        return $table
            ->recordUrl(null)
            ->recordAction('select')
            ->recordClasses(fn (Model $record): ?string => ! $this->isCreatingRecord && (string) $record->getKey() === $this->selectedRecord ? 'catalog-selected-row' : null)
            ->recordActions([
                Action::make('select')->label($hasEditPage ? 'Edit' : 'View')
                    ->iconButton()->icon($hasEditPage ? Heroicon::OutlinedPencilSquare : Heroicon::OutlinedEye)->tooltip($hasEditPage ? 'Edit' : 'View')
                    ->action(fn (Model $record) => $this->selectRecord((string) $record->getKey())),
                ...$otherActions,
            ]);
    }

    #[On('catalog-record-saved')]
    #[On('configurator-updated')]
    public function refreshRecords(): void
    {
        $this->flushCachedTableRecords();
    }
}
