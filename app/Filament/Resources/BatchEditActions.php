<?php

namespace App\Filament\Resources;

use App\Actions\BatchCatalogChanges;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class BatchEditActions
{
    public static function group(string $table): BulkActionGroup
    {
        $fields = app(BatchCatalogChanges::class)->fields($table);
        $labels = array_combine(array_keys($fields), array_map(fn (string $key): string => __('admin.batch.fields.'.$key), array_keys($fields)));
        $actions = [self::base('batchEdit', __('admin.batch.labels.edit_selected'), $table)->schema([
            Hidden::make('operation')->default('edit'),
            Repeater::make('changes')->label(__('admin.batch.labels.field_changes'))->minItems(1)->defaultItems(1)->reorderable(false)->columns(2)->schema([
                Select::make('field')->label(__('ui.fields.field'))->options($labels)->required()->live(),
                Select::make('mode')->label(__('admin.batch.labels.change'))->default('unchanged')->required()->live()->options(function (Get $get) use ($fields): array {
                    $field = $fields[$get('field')] ?? [];

                    return ['unchanged' => __('admin.batch.choices.unchanged'), 'set' => __('admin.batch.choices.set')] + (($field['nullable'] ?? false) ? ['clear' => __('admin.batch.choices.clear')] : []) + (($field['type'] ?? '') === 'tags' ? ['add' => __('admin.batch.choices.add_tags'), 'remove' => __('admin.batch.choices.remove_tags')] : []);
                }),
                Textarea::make('value')->label(__('admin.batch.labels.new_value'))->rows(2)->visible(fn (Get $get): bool => ($fields[$get('field')]['type'] ?? '') === 'text' && $get('mode') === 'set')->columnSpanFull(),
                TextInput::make('value')->label(__('admin.batch.labels.new_value'))->integer()->visible(fn (Get $get): bool => ($fields[$get('field')]['type'] ?? '') === 'integer' && $get('mode') === 'set'),
                Toggle::make('value')->label(__('ui.status.enabled'))->visible(fn (Get $get): bool => ($fields[$get('field')]['type'] ?? '') === 'boolean' && $get('mode') === 'set'),
                Select::make('value')->label(__('admin.batch.labels.new_value'))->options(fn (Get $get): array => $fields[$get('field')]['options'] ?? [])->multiple(fn (Get $get): bool => ($fields[$get('field')]['type'] ?? '') === 'multiple')->visible(fn (Get $get): bool => in_array($fields[$get('field')]['type'] ?? '', ['select', 'multiple'], true) && $get('mode') === 'set'),
                TagsInput::make('value')->label(__('catalog.fields.tags'))->placeholder(__('tags.input_placeholder'))->visible(fn (Get $get): bool => ($fields[$get('field')]['type'] ?? '') === 'tags' && in_array($get('mode'), ['set', 'add', 'remove'], true)),
            ])->hintAction(FormHints::make(__('admin.batch.hints.values_may_differ_across_the_selection_only'))),
            View::make('filament.resources.batch-preview'),
        ]), self::base('batchReplace', __('admin.batch.labels.find_and_replace'), $table)->schema([
            Hidden::make('operation')->default('replace'),
            Select::make('field')->label(__('ui.fields.field'))->options(array_intersect_key($labels, array_filter($fields, fn (array $field): bool => $field['type'] === 'text')))->required(),
            Select::make('mode')->label(__('admin.batch.labels.match'))->options(['literal' => __('admin.batch.labels.literal_text'), 'regex' => __('admin.batch.labels.regular_expression')])->default('literal')->required(),
            Textarea::make('search')->label(__('admin.batch.labels.find_pattern'))->required()->rows(2),
            Textarea::make('replacement')->label(__('admin.batch.labels.replace_with'))->default('')->rows(2)->hintAction(FormHints::make(__('admin.batch.hints.regex_captures_1_or_1_empty_replacement'))),
            Toggle::make('case_sensitive')->label(__('catalog.fields.case_sensitive'))->default(true),
            Select::make('occurrences')->label(__('catalog.fields.occurrences'))->options(['all' => __('admin.batch.labels.all_matches'), 'first' => __('admin.batch.labels.first_match')])->default('all')->required(),
            TextInput::make('flags')->label(__('admin.batch.labels.regex_flags'))->default('')->hintAction(FormHints::make(__('admin.batch.hints.optional_m_s_u_x_patterns_are'))),
            View::make('filament.resources.batch-preview'),
        ])];
        if (app(BatchCatalogChanges::class)->canRemove($table)) {
            $actions[] = self::base('batchRemove', str_starts_with($table, 'configurator-') ? __('admin.batch.labels.remove_selected_inclusions_rules') : __('admin.batch.labels.delete_selected'), $table)->color('danger')->schema([
                Hidden::make('operation')->default('remove'),
                View::make('filament.resources.batch-preview'),
            ])->modalDescription(__('admin.batch.help.preview_the_complete_selection_first_dependencies_block'));
        }

        return BulkActionGroup::make($actions)->label(__('admin.batch.labels.selected'));
    }

    private static function base(string $name, string $label, string $table): BulkAction
    {
        return BulkAction::make($name)->label($label)->authorize('manage-catalog')->slideOver()->stickyModalHeader()->stickyModalFooter()->modalSubmitActionLabel(__('admin.batch.labels.apply_previewed_changes'))
            ->modalSubmitAction(fn (Action $action, $livewire): Action => $action->disabled(empty($livewire->batchPreview['token']) || ! empty($livewire->batchPreview['blockers'])))
            ->beforeFormFilled(function ($livewire): void {
                $livewire->batchPreview = [];
            })
            ->extraModalFooterActions(function (Action $action, $livewire): array {
                $actions = [$action->makeModalSubmitAction('previewBatch', arguments: ['preview' => true])->label(__('admin.batch.labels.preview_changes'))->color('gray')];
                foreach (collect($livewire->batchPreview['blockers'] ?? [])->groupBy('category') as $category => $blockers) {
                    $choices = $blockers->values()->all();
                    $actions[] = Action::make('blocking-'.$category)->label(__('admin.batch.labels.view', ['value1' => DependencyActions::categoryLabel($category), 'value2' => $blockers->sum('count')]))->color('gray')->slideOver()->modalSubmitAction(false)->modalCancelActionLabel(__('ui.actions.back'))->schema([
                        Select::make('blocking_record')->label(__('admin.batch.labels.blocked_record'))->options(array_map(fn (array $blocker): string => $blocker['name'].' #'.$blocker['parent_id'].' ('.$blocker['count'].')', $choices))->default(0)->selectablePlaceholder(false)->live(),
                        View::make('filament.resources.item-list-content')->viewData(function (Get $get) use ($choices): array {
                            $choice = $choices[$get('blocking_record')] ?? $choices[0];

                            return ['listKey' => $choice['list_key'], 'parentId' => $choice['parent_id']];
                        }),
                    ]);
                }

                return $actions;
            })
            ->action(function (Action $action, Collection $records, array $data, array $arguments, $livewire) use ($table): void {
                $ownerId = method_exists($livewire, 'getOwnerRecord') ? $livewire->getOwnerRecord()->getKey() : null;
                $ids = $records->modelKeys();
                if ($arguments['preview'] ?? false) {
                    $livewire->batchPreview = app(BatchCatalogChanges::class)->preview(auth()->user(), $table, $ids, $data, $ownerId);
                    $action->halt();
                }
                if (empty($livewire->batchPreview['token'])) {
                    throw ValidationException::withMessages(['preview' => __('admin.batch.validation.preview_the_selected_records_before_applying_changes')]);
                }
                try {
                    app(BatchCatalogChanges::class)->apply(auth()->user(), $table, $ids, $data, $livewire->batchPreview['token'], $ownerId);
                } catch (ValidationException $exception) {
                    $livewire->batchPreview = app(BatchCatalogChanges::class)->preview(auth()->user(), $table, $ids, $data, $ownerId);
                    throw $exception;
                }
                $changedIds = array_column(array_filter($livewire->batchPreview['rows'], fn (array $row): bool => $row['changed']), 'id');
                if (method_exists($livewire, 'reconcileBatchChanges')) {
                    $livewire->reconcileBatchChanges($changedIds, $data['operation'] === 'remove');
                }
                $livewire->dispatch('catalog-batch-applied', model: $records->first()::class, ids: $changedIds);
                $livewire->deselectAllTableRecords();
                $livewire->batchPreview = [];
                $livewire->flushCachedTableRecords();
                $livewire->dispatch('configurator-updated');
                $livewire->dispatch('refresh-sidebar');
                Notification::make()->title(__('admin.batch.headings.selected_records_updated'))->success()->send();
            });
    }
}
