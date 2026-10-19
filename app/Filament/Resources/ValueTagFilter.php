<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Values\Tables\ValuesTable;
use Filament\Forms\Components\Hidden;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Illuminate\Database\Eloquent\Builder;

class ValueTagFilter
{
    public static function make(string $relationship = ''): Filter
    {
        return Filter::make('tags')->label(__('catalog.fields.tags'))->schema(fn (): array => [Hidden::make('operator')->default('or'), ValuesTable::tagFilterField()])
            ->query(function (Builder $query, array $data) use ($relationship): Builder {
                $tags = array_values(array_filter($data['values'] ?? [], 'is_string'));
                if ($tags === []) {
                    return $query;
                }
                $operator = $data['operator'] ?? 'or';
                if (! in_array($operator, ['or', 'and'], true)) {
                    return $query->whereRaw('1 = 0');
                }
                $match = function (Builder $values) use ($tags, $operator): void {
                    $values->where(function (Builder $values) use ($tags, $operator): void {
                        foreach ($tags as $tag) {
                            $values->whereJsonContains('tags', $tag, $operator);
                        }
                    });
                };

                return $relationship === '' ? $query->where($match) : $query->whereHas($relationship, $match);
            })
            ->indicateUsing(fn (array $data): array => empty($data['values']) ? [] : [Indicator::make(__('catalog.filters.tags_indicator', ['tags' => implode(($data['operator'] ?? 'or') === 'and' ? ' AND ' : ' OR ', $data['values'])]))->removeField('values')]);
    }
}
