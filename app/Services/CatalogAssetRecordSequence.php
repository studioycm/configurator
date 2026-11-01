<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CatalogAssetRecordSequence
{
    /** @param list<array{column: string, direction: string}> $sort
     * @return array{record_id: int, position: int, total: int, page: int}|null
     */
    public function position(Builder $query, int $recordId, array $sort, int $pageSize): ?array
    {
        $record = (clone $query)->reorder()->find($recordId);
        if ($record === null) {
            return null;
        }

        return $this->located($query, $record, $sort, $pageSize);
    }

    /** @param list<array{column: string, direction: string}> $sort
     * @return array{record_id: int, position: int, total: int, page: int}|null
     */
    public function neighbor(Builder $query, int $recordId, string $direction, array $sort, int $pageSize): ?array
    {
        $this->direction($direction);
        $current = (clone $query)->reorder()->find($recordId);
        if ($current === null) {
            return null;
        }
        $neighbor = $this->ordered($this->relative($query, $current, $sort, $direction === 'previous'), $sort, $direction === 'previous')->first();

        return $neighbor === null ? null : $this->located($query, $neighbor, $sort, $pageSize);
    }

    /** @param list<array{column: string, direction: string}> $sort
     * @return array{record_id: int, position: int, total: int, page: int}|null
     */
    public function boundary(Builder $query, string $direction, array $sort, int $pageSize): ?array
    {
        $this->direction($direction);
        $record = $this->ordered($query, $sort, $direction === 'previous')->first();
        if ($record === null) {
            return null;
        }
        $total = (clone $query)->reorder()->count();

        return $this->result($record, $direction === 'next' ? 1 : $total, $total, $pageSize);
    }

    /** @param list<array{column: string, direction: string}> $sort */
    public function ordered(Builder $query, array $sort, bool $reverse = false): Builder
    {
        $query = (clone $query)->reorder();
        foreach ($sort as $term) {
            if (preg_match('/^(products|groups)\.(id|product_code|product_name|legacy_id|name|sort_order)$/D', $term['column']) !== 1 || ! in_array($term['direction'], ['asc', 'desc'], true)) {
                throw ValidationException::withMessages(['tableSort' => 'Choose a valid scalar sort.']);
            }
            $query->orderBy($term['column'], $reverse ? ($term['direction'] === 'asc' ? 'desc' : 'asc') : $term['direction']);
        }

        return $query;
    }

    /** @param list<array{column: string, direction: string}> $sort
     * @return array{record_id: int, position: int, total: int, page: int}
     */
    private function located(Builder $query, Model $record, array $sort, int $pageSize): array
    {
        $total = (clone $query)->reorder()->count();
        $position = $this->relative($query, $record, $sort, true)->reorder()->count() + 1;

        return $this->result($record, $position, $total, $pageSize);
    }

    /** @param list<array{column: string, direction: string}> $sort */
    private function relative(Builder $query, Model $record, array $sort, bool $before): Builder
    {
        $this->ordered($query, $sort);

        return (clone $query)->where(function (Builder $outer) use ($record, $sort, $before): void {
            foreach ($sort as $index => $term) {
                $outer->orWhere(function (Builder $branch) use ($record, $sort, $before, $index, $term): void {
                    for ($previous = 0; $previous < $index; $previous++) {
                        $column = $sort[$previous]['column'];
                        $value = $record->getAttribute(substr($column, strpos($column, '.') + 1));
                        $value === null ? $branch->whereNull($column) : $branch->where($column, $value);
                    }
                    $value = $record->getAttribute(substr($term['column'], strpos($term['column'], '.') + 1));
                    $less = ($term['direction'] === 'asc') === $before;
                    if ($value === null) {
                        $less ? $branch->whereRaw('1 = 0') : $branch->whereNotNull($term['column']);
                    } else {
                        $branch->where(function (Builder $comparison) use ($term, $value, $less): void {
                            $comparison->where($term['column'], $less ? '<' : '>', $value);
                            if ($less) {
                                $comparison->orWhereNull($term['column']);
                            }
                        });
                    }
                });
            }
        });
    }

    /** @return array{record_id: int, position: int, total: int, page: int} */
    private function result(Model $record, int $position, int $total, int $pageSize): array
    {
        if ($pageSize < 1 || $pageSize > 500) {
            throw ValidationException::withMessages(['imagePageSize' => 'Use 1–500 rows.']);
        }

        return ['record_id' => (int) $record->getKey(), 'position' => $position, 'total' => $total, 'page' => intdiv($position - 1, $pageSize) + 1];
    }

    private function direction(string $direction): void
    {
        if (! in_array($direction, ['previous', 'next'], true)) {
            throw ValidationException::withMessages(['imageNavigation' => 'Choose Previous or Next.']);
        }
    }
}
