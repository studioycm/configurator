<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Values\Tables\ValuesTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Illuminate\Database\Eloquent\Builder;

class ValueTagFilter
{
    public static function make(string $relationship = ''): Filter
    {
        return Filter::make('tags')->label(__('catalog.fields.tags'))->schema(fn (): array => [ValuesTable::tagFilterField()])
            ->query(function (Builder $query, array $data) use ($relationship): Builder {
                $tags = array_values(array_filter($data['values'] ?? [], 'is_string'));
                if ($tags === []) {
                    return $query;
                }
                $match = function (Builder $values) use ($tags): void {
                    $values->where(function (Builder $values) use ($tags): void {
                        foreach ($tags as $tag) {
                            $values->orWhereJsonContains('tags', $tag);
                        }
                    });
                };

                return $relationship === '' ? $query->where($match) : $query->whereHas($relationship, $match);
            })
            ->indicateUsing(fn (array $data): array => empty($data['values']) ? [] : [Indicator::make(__('catalog.filters.tags_indicator', ['tags' => implode(', ', $data['values'])]))->removeField('values')]);
    }
}
