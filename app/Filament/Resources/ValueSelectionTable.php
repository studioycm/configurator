<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Values\Tables\ValuesTable;
use App\Models\Value;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class ValueSelectionTable
{
    public static function configure(Table $table): Table
    {
        Gate::authorize('manage-catalog');

        return TablePresentation::configure($table->query(fn (Table $table) => Value::query()
            ->when($table->getArguments()['attribute_id'] ?? null, fn ($query, $id) => $query->whereDoesntHave('options', fn ($options) => $options->where('attribute_id', $id)))
            ->whereNotIn('id', $table->getArguments()['excluded_ids'] ?? []))
            ->columns([
                TextColumn::make('label')->label(__('catalog.fields.ui_name'))->searchable()->sortable(),
                TextColumn::make('semantic_id')->label(__('catalog.fields.semantic_id'))->searchable()->sortable()->toggleable(),
                TextColumn::make('tags')->label(__('catalog.fields.tags'))->badge()->toggleable(),
            ])->filters([
                Filter::make('tags')->label(__('catalog.fields.tags'))->schema([ValuesTable::tagFilterField()])
                    ->query(fn (Builder $query, array $data): Builder => $query->when($data['values'] ?? [], fn (Builder $query, array $tags): Builder => $query->where(function (Builder $query) use ($tags): void {
                        foreach ($tags as $tag) {
                            $query->orWhereJsonContains('tags', $tag);
                        }
                    })))
                    ->indicateUsing(fn (array $data): array => empty($data['values']) ? [] : [Indicator::make(__('catalog.filters.tags_indicator', ['tags' => implode(', ', $data['values'])]))->removeField('values')]),
            ])->defaultSort('label')->paginationPageOptions([10, 25, 50]), 'select-value', true);
    }
}
