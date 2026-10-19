<?php

namespace App\Filament\Resources\Values\Tables;

use App\Filament\Resources\BatchEditActions;
use App\Filament\Resources\ItemCountColumn;
use App\Filament\Resources\TablePresentation;
use App\Filament\Resources\Values\ValueResource;
use App\Filament\Resources\ValueTagFilter;
use App\Models\Value;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\View;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

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
            ValueTagFilter::make(),
        ])
            ->searchPlaceholder(__('catalog.master_values.search_placeholder'))->defaultSort('label')->recordActions([Action::make('edit')->label(__('ui.actions.edit'))->url(fn (Value $record): string => ValueResource::getUrl('edit', ['record' => $record]))]), 'values', true)->pushToolbarActions([self::assignTagsAction()]);
    }

    private static function assignTagsAction(): BulkAction
    {
        return BatchEditActions::group('values')->getFlatActions()['batchEdit']
            ->name('assignTags')->label(__('tags.assign'))
            ->modalDescription(__('tags.assignment_help'))
            ->schema([
                Hidden::make('operation')->default('edit'),
                Hidden::make('changes.0.field')->default('tags'),
                Select::make('changes.0.mode')->label(__('tags.mode'))->options(['add' => __('tags.add'), 'remove' => __('tags.remove_selected'), 'set' => __('tags.replace')])->default('add')->required(),
                TagsInput::make('changes.0.value')->label(__('catalog.fields.tags'))->suggestions(fn (): array => array_keys(Value::tagOptions()))->default([]),
                View::make('filament.resources.batch-preview'),
            ]);
    }

    public static function tagFilterField(): ToggleButtons
    {
        return ToggleButtons::make('values')->inline()->extraAttributes(['class' => 'catalog-tag-filters'])
            ->label(__('catalog.master_values.labels.filter_by_tags'))->multiple()->options(function ($livewire): array {
                $options = Value::tagOptions();
                foreach ($livewire->tableFilters['tags']['values'] ?? [] as $tag) {
                    if (is_string($tag) && ! isset($options[$tag])) {
                        $options[$tag] = __('tags.unused_filter', ['tag' => $tag]);
                    }
                }

                return $options;
            })->default([]);
    }
}
