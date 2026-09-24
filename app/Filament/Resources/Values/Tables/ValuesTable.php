<?php

namespace App\Filament\Resources\Values\Tables;

use App\Filament\Resources\Values\ValueResource;
use App\Models\Value;
use Filament\Actions\Action;
use Filament\Forms\Components\ToggleButtons;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ValuesTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('id')->label('ID')->sortable()->searchable(isIndividual: true, isGlobal: false)->toggleable(),
            TextColumn::make('label')->label('Master value')->sortable()->toggleable()->searchable(['label', 'description']),
            TextColumn::make('tags')->badge(),
            TextColumn::make('options_count')->label('Options')->sortable()->toggleable(),
            TextColumn::make('created_at')->label('Created At')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('updated_at')->label('Updated At')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            Filter::make('tags')->schema([
                ToggleButtons::make('values')->label('Filter by tags')->multiple()->inline()->extraAttributes(['class' => 'catalog-tag-filters'])->options(fn (): array => Value::tagOptions()),
            ])->query(function (Builder $query, array $data): Builder {
                $tags = array_values(array_filter($data['values'] ?? [], 'is_string'));

                return $query->when($tags !== [], fn (Builder $query): Builder => $query->where(function (Builder $query) use ($tags): void {
                    foreach ($tags as $tag) {
                        $query->orWhereJsonContains('tags', $tag);
                    }
                }));
            }),
        ])->filtersFormColumns(1)->deferFilters(false)->filtersLayout(FiltersLayout::AboveContent)
            ->searchPlaceholder('Search master values')->defaultSort('label')->recordActions([Action::make('edit')->label('Edit')->url(fn (Value $record): string => ValueResource::getUrl('edit', ['record' => $record]))]);
    }
}
