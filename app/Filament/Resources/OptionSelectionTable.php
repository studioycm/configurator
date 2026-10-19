<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Values\Tables\ValuesTable;
use App\Models\ConfiguratorAttribute;
use App\Models\Option;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class OptionSelectionTable
{
    public static function configure(Table $table): Table
    {
        Gate::authorize('manage-catalog');

        $owner = null;
        $resolveOwner = function (Table $table) use (&$owner): ConfiguratorAttribute {
            return $owner ??= ConfiguratorAttribute::with('attribute')->findOrFail((int) $table->getArguments()['owner_id']);
        };

        return TablePresentation::configure($table->heading(fn (Table $table): string => __('catalog.attribute_values.headings.for_attribute', ['attribute' => $resolveOwner($table)->label_override ?? $resolveOwner($table)->attribute->label]))
            ->query(fn (Table $table) => Option::where('is_active', true)->where('is_hidden', false)->where('attribute_id', $resolveOwner($table)->attribute_id)->whereNotIn('id', $resolveOwner($table)->options()->select('option_id'))->with('value'))
            ->columns([TextColumn::make('code')->label(__('catalog.fields.code'))->searchable()->toggleable(), TextColumn::make('value.label')->label(__('catalog.fields.value_ui_name'))->searchable()->toggleable(), TextColumn::make('value.semantic_id')->label(__('catalog.fields.semantic_id'))->searchable()->toggleable(), TextColumn::make('value.tags')->label(__('catalog.fields.tags'))->badge()])
            ->filters([Filter::make('tags')->label(__('catalog.fields.tags'))->schema(fn (): array => [ValuesTable::tagFilterField()])
                ->indicateUsing(fn (array $data): ?string => empty($data['values']) ? null : __('catalog.filters.tags_indicator', ['tags' => implode(', ', $data['values'])]))
                ->query(fn (Builder $query, array $data): Builder => $query->when(! empty($data['values']), fn (Builder $query): Builder => $query->whereHas('value', function (Builder $query) use ($data): void {
                    $query->where(function (Builder $query) use ($data): void {
                        foreach (array_filter($data['values'], 'is_string') as $tag) {
                            $query->orWhereJsonContains('tags', $tag);
                        }
                    });
                })))])->paginationPageOptions([10, 25, 50]), 'select-option', true);
    }
}
