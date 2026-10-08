<?php

namespace App\Filament\Resources;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Computed;

trait InteractsWithCompactListHeader
{
    public function getHeader(): ?View
    {
        return view('filament.resources.compact-list-header');
    }

    public function getBreadcrumbs(): array
    {
        $group = static::getResource()::getNavigationGroup();

        return is_string($group) && filled($group) ? [$group, $this->getTitle()] : [$this->getTitle()];
    }

    /** @return array{matched: int, total: int, filtered: bool} */
    #[Computed]
    public function resourceTableCounts(): array
    {
        $records = $this->getTableRecords();

        return [
            'matched' => $records instanceof LengthAwarePaginator ? $records->total() : $records->count(),
            'total' => $this->getTable()->getQuery()->toBase()->getCountForPagination(),
            'filtered' => filled($this->getTableSearch()) || collect($this->tableFilters ?? [])->flatten()
                ->contains(fn (mixed $value): bool => $value !== false && filled($value)),
        ];
    }
}
