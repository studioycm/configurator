<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Values\Tables\ValuesTable;
use App\Models\ConfiguratorAttribute;
use App\Models\Option;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class OptionSelectionTable
{
    public static function configure(Table $table): Table
    {
        Gate::authorize('manage-catalog');

        return TablePresentation::configure($table->query(fn (Table $table) => Option::where('is_active', true)->where('is_hidden', false)->where('attribute_id', ConfiguratorAttribute::findOrFail((int) $table->getArguments()['owner_id'])->attribute_id)->whereNotIn('id', ConfiguratorAttribute::findOrFail((int) $table->getArguments()['owner_id'])->options()->select('option_id'))->with('value'))
            ->columns([TextColumn::make('code')->label('Code')->searchable()->toggleable(), TextColumn::make('value.label')->label('Value')->searchable()->toggleable(), TextColumn::make('value.tags')->label('Tags')->badge()])
            ->filters([Filter::make('tags')->schema(fn (): array => [ValuesTable::tagFilterField()])
                ->query(fn (Builder $query, array $data): Builder => $query->when(! empty($data['values']), fn (Builder $query): Builder => $query->whereHas('value', function (Builder $query) use ($data): void {
                    $query->where(function (Builder $query) use ($data): void {
                        foreach (array_filter($data['values'], 'is_string') as $tag) {
                            $query->orWhereJsonContains('tags', $tag);
                        }
                    });
                })))])->paginationPageOptions([10, 25, 50]), 'select-option', true)
            ->filtersLayout(FiltersLayout::AboveContent)->deferFilters(false);
    }
}
