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
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ValuesTable
{
    public static function configure(Table $table): Table
    {
        return TablePresentation::configure($table->columns([
            TextColumn::make('id')->label(__('ui.fields.id'))->sortable()->searchable(isIndividual: false, isGlobal: true)->toggleable(),
            TextColumn::make('label')->label(__('catalog.fields.ui_name'))->sortable()->toggleable()->searchable(),
            TextColumn::make('semantic_id')->label(__('catalog.fields.semantic_id'))->sortable()->toggleable()->searchable()->copyable(),
            TextColumn::make('description')->label(__('catalog.fields.specification'))->searchable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('tags')->label(__('catalog.fields.tags'))->badge(),
            ItemCountColumn::make('options_count', __('catalog.resources.attributes.plural'), 'value-options')->sortable()->toggleable(),
            TextColumn::make('created_at')->label(__('ui.fields.created_at'))->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('updated_at')->label(__('ui.fields.updated_at'))->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            Filter::make('tags')->label(__('catalog.fields.tags'))->schema(fn (): array => [self::tagFilterField()->extraFieldWrapperAttributes(['class' => 'catalog-modal-tag-filter'])])->query(function (Builder $query, array $data): Builder {
                $tags = array_values(array_filter($data['values'] ?? [], 'is_string'));

                return $query->when($tags !== [], fn (Builder $query): Builder => $query->where(function (Builder $query) use ($tags): void {
                    foreach ($tags as $tag) {
                        $query->orWhereJsonContains('tags', $tag);
                    }
                }));
            })->indicateUsing(function (array $data): array {
                $tags = array_values(array_filter($data['values'] ?? [], 'is_string'));

                return $tags === [] ? [] : [Indicator::make(__('catalog.filters.tags_indicator', ['tags' => implode(', ', $tags)]))->removeField('values')];
            }),
        ])
            ->searchPlaceholder(__('catalog.master_values.search_placeholder'))->defaultSort('label')->recordActions([Action::make('edit')->label(__('ui.actions.edit'))->url(fn (Value $record): string => ValueResource::getUrl('edit', ['record' => $record]))]), 'values', true);
    }

    public static function tagFilterField(): ToggleButtons
    {
        return ToggleButtons::make('values')->inline()->extraAttributes(['class' => 'catalog-tag-filters'])
            ->label(__('catalog.master_values.labels.filter_by_tags'))->multiple()->options(Value::tagOptions())->default([]);
    }
}
