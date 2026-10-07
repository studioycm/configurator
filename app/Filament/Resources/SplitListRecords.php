<?php

namespace App\Filament\Resources;

use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;

abstract class SplitListRecords extends ListRecords
{
    use InteractsWithScopedTableSearch;

    protected string $view = 'filament.resources.split-list';

    #[Url(as: 'record')]
    public ?string $selectedRecord = null;

    public function selectRecord(string $record): void
    {
        $resource = static::getResource();
        $model = $resource::getEloquentQuery()->findOrFail($record);
        abort_unless($resource::hasPage('edit') ? $resource::canEdit($model) : $resource::canView($model), 403);
        $this->selectedRecord = (string) $model->getKey();
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
        return parent::makeTable()
            ->recordUrl(null)
            ->recordAction('select')
            ->recordClasses(fn (Model $record): ?string => (string) $record->getKey() === $this->selectedRecord ? 'catalog-selected-row' : null)
            ->recordActions([
                Action::make('select')->label(static::getResource()::hasPage('edit') ? 'Edit' : 'View')
                    ->iconButton()->icon(Heroicon::OutlinedPencilSquare)->tooltip(static::getResource()::hasPage('edit') ? 'Edit' : 'View')
                    ->action(fn (Model $record) => $this->selectRecord((string) $record->getKey())),
            ]);
    }

    #[On('catalog-record-saved')]
    #[On('configurator-updated')]
    public function refreshRecords(): void
    {
        $this->flushCachedTableRecords();
    }
}
