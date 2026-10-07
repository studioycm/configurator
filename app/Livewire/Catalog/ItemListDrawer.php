<?php

namespace App\Livewire\Catalog;

use App\DTO\ItemListDefinition;
use App\Filament\Resources\InteractsWithScopedTableSearch;
use App\Filament\Resources\TablePresentation;
use App\Services\ItemLists;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ItemListDrawer extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithScopedTableSearch, InteractsWithTable {
        InteractsWithScopedTableSearch::applyGlobalSearchToTableQuery insteadof InteractsWithTable;
        InteractsWithScopedTableSearch::resetTableColumnManager insteadof InteractsWithTable;
    }

    #[Locked]
    public string $listKey;

    #[Locked]
    public int $parentId;

    public function mount(string $listKey, int $parentId): void
    {
        $this->listKey = $listKey;
        $this->parentId = $parentId;
        $this->definition();
    }

    private function definition(): ItemListDefinition
    {
        return app(ItemLists::class)->definition(auth()->user(), $this->listKey, $this->parentId);
    }

    public function table(Table $table): Table
    {
        $definition = $this->definition();
        $columns = [];
        foreach ($definition->columns as $field => $label) {
            $columns[] = TextColumn::make($field)->label($label)->wrap()->searchable()->toggleable();
        }
        if (is_array($definition->query)) {
            $table->records(function (int $page, int|string $recordsPerPage, ?string $search): LengthAwarePaginator {
                $definition = $this->definition();
                $records = collect($definition->query)->filter(function (array $row) use ($search, $definition): bool {
                    if (blank($search)) {
                        return true;
                    }
                    $fields = $this->tableSearchScope === 'all' ? array_keys($definition->columns) : (array_key_exists($this->tableSearchScope, $definition->columns) ? [$this->tableSearchScope] : []);
                    foreach ($fields as $field) {
                        if (str_contains(mb_strtolower((string) data_get($row, $field)), mb_strtolower($search))) {
                            return true;
                        }
                    }

                    return false;
                });

                return new LengthAwarePaginator($records->forPage($page, (int) $recordsPerPage), $records->count(), (int) $recordsPerPage, $page);
            });
        } else {
            $table->query($definition->query)->defaultSort('id')->recordActions([Action::make('edit')->label('Open editor')->icon('heroicon-o-arrow-top-right-on-square')->url(fn (Model $record): string => app(ItemLists::class)->recordUrl($record))->openUrlInNewTab()]);
        }

        return TablePresentation::configure($table->heading($definition->title)->columns($columns)
            ->paginationPageOptions([10, 25, 50])
            ->headerActions($definition->fullListUrl ? [Action::make('fullList')->label('Open full list')->url($definition->fullListUrl)->openUrlInNewTab()] : []), (is_array($definition->query) ? 'array-' : 'items-').$this->listKey, true);
    }

    public function render(): View
    {
        $this->definition();

        return view('livewire.catalog.item-list-drawer');
    }
}
