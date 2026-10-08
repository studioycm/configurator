<?php

namespace App\Filament\Resources\Values\Tables;

use App\Filament\Resources\ItemCountColumn;
use App\Filament\Resources\TablePresentation;
use App\Filament\Resources\Values\ValueResource;
use App\Models\Value;
use Filament\Actions\Action;
use Filament\Forms\Components\ToggleButtons;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ValuesTable
{
    public static function configure(Table $table): Table
    {
        return TablePresentation::configure($table->columns([
            TextColumn::make('id')->label('ID')->sortable()->searchable(isIndividual: false, isGlobal: true)->toggleable(),
            TextColumn::make('label')->label('Master value')->sortable()->toggleable()->searchable(),
            TextColumn::make('description')->searchable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('tags')->badge(),
            ItemCountColumn::make('options_count', 'Options', 'value-options')->sortable()->toggleable(),
            TextColumn::make('created_at')->label('Created At')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('updated_at')->label('Updated At')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            Filter::make('tags')->schema(fn (): array => [self::tagFilterField()->extraFieldWrapperAttributes(['class' => 'catalog-modal-tag-filter'])])->query(function (Builder $query, array $data): Builder {
                $tags = array_values(array_filter($data['values'] ?? [], 'is_string'));

                return $query->when($tags !== [], fn (Builder $query): Builder => $query->where(function (Builder $query) use ($tags): void {
                    foreach ($tags as $tag) {
                        $query->orWhereJsonContains('tags', $tag);
                    }
                }));
            }),
        ])
            ->searchPlaceholder('Search master values')->defaultSort('label')->recordActions([Action::make('edit')->label('Edit')->url(fn (Value $record): string => ValueResource::getUrl('edit', ['record' => $record]))]), 'values', true);
    }

    public static function tagFilterField(): ToggleButtons
    {
        return ToggleButtons::make('values')->inline()->extraAttributes(['class' => 'catalog-tag-filters'])
            ->label('Filter by tags')->multiple()->options(Value::tagOptions())->default([]);
    }
}
