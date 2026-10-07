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
        $labels = array_combine(array_keys($fields), array_map(fn (string $key): string => str($key)->replace('_', ' ')->title()->toString(), array_keys($fields)));
        $actions = [self::base('batchEdit', 'Edit selected', $table)->schema([
            Hidden::make('operation')->default('edit'),
            Repeater::make('changes')->label('Field changes')->minItems(1)->defaultItems(1)->reorderable(false)->columns(2)->schema([
                Select::make('field')->options($labels)->required()->live(),
                Select::make('mode')->label('Change')->default('unchanged')->required()->live()->options(function (Get $get) use ($fields): array {
                    $field = $fields[$get('field')] ?? [];

                    return ['unchanged' => 'Leave unchanged', 'set' => 'Set'] + (($field['nullable'] ?? false) ? ['clear' => 'Clear / use fallback'] : []) + (($field['type'] ?? '') === 'tags' ? ['add' => 'Add tags', 'remove' => 'Remove tags'] : []);
                }),
                Textarea::make('value')->label('New value')->rows(2)->visible(fn (Get $get): bool => ($fields[$get('field')]['type'] ?? '') === 'text' && $get('mode') === 'set')->columnSpanFull(),
                TextInput::make('value')->label('New value')->integer()->visible(fn (Get $get): bool => ($fields[$get('field')]['type'] ?? '') === 'integer' && $get('mode') === 'set'),
                Toggle::make('value')->label('Enabled')->visible(fn (Get $get): bool => ($fields[$get('field')]['type'] ?? '') === 'boolean' && $get('mode') === 'set'),
                Select::make('value')->label('New value')->options(fn (Get $get): array => $fields[$get('field')]['options'] ?? [])->multiple(fn (Get $get): bool => ($fields[$get('field')]['type'] ?? '') === 'multiple')->visible(fn (Get $get): bool => in_array($fields[$get('field')]['type'] ?? '', ['select', 'multiple'], true) && $get('mode') === 'set'),
                TagsInput::make('value')->label('Tags')->visible(fn (Get $get): bool => ($fields[$get('field')]['type'] ?? '') === 'tags' && in_array($get('mode'), ['set', 'add', 'remove'], true)),
            ])->hintAction(FormHints::make('Values may differ across the selection. Only fields explicitly set or cleared are changed.')),
            View::make('filament.resources.batch-preview'),
        ]), self::base('batchReplace', 'Find and replace', $table)->schema([
            Hidden::make('operation')->default('replace'),
            Select::make('field')->options(array_intersect_key($labels, array_filter($fields, fn (array $field): bool => $field['type'] === 'text')))->required(),
            Select::make('mode')->label('Match')->options(['literal' => 'Literal text', 'regex' => 'Regular expression'])->default('literal')->required(),
            Textarea::make('search')->label('Find / pattern')->required()->rows(2),
            Textarea::make('replacement')->label('Replace with')->default('')->rows(2)->hintAction(FormHints::make('Regex captures: $1 or ${1}. Empty replacement removes matched text; null fields stay null.')),
            Toggle::make('case_sensitive')->default(true),
            Select::make('occurrences')->options(['all' => 'All matches', 'first' => 'First match'])->default('all')->required(),
            TextInput::make('flags')->label('Regex flags')->default('')->hintAction(FormHints::make('Optional: m, s, u, x. Patterns are bounded; invalid or expensive patterns block Apply.')),
            View::make('filament.resources.batch-preview'),
        ])];
        if (app(BatchCatalogChanges::class)->canRemove($table)) {
            $actions[] = self::base('batchRemove', str_starts_with($table, 'configurator-') ? 'Remove selected inclusions / rules' : 'Delete selected', $table)->color('danger')->schema([
                Hidden::make('operation')->default('remove'),
                View::make('filament.resources.batch-preview'),
            ])->modalDescription('Preview the complete selection first. Dependencies block the whole operation. Shared records stay available when removing local inclusions.');
        }

        return BulkActionGroup::make($actions)->label('Selected');
    }

    private static function base(string $name, string $label, string $table): BulkAction
    {
        return BulkAction::make($name)->label($label)->authorize('manage-catalog')->slideOver()->stickyModalHeader()->stickyModalFooter()->modalSubmitActionLabel('Apply previewed changes')
            ->modalSubmitAction(fn (Action $action, $livewire): Action => $action->disabled(empty($livewire->batchPreview['token']) || ! empty($livewire->batchPreview['blockers'])))
            ->beforeFormFilled(function ($livewire): void {
                $livewire->batchPreview = [];
            })
            ->extraModalFooterActions(function (Action $action, $livewire): array {
                $actions = [$action->makeModalSubmitAction('previewBatch', arguments: ['preview' => true])->label('Preview changes')->color('gray')];
                foreach (collect($livewire->batchPreview['blockers'] ?? [])->groupBy('category') as $category => $blockers) {
                    $choices = $blockers->values()->all();
                    $actions[] = Action::make('blocking-'.$category)->label('View '.$category.' ('.$blockers->sum('count').')')->color('gray')->slideOver()->modalSubmitAction(false)->modalCancelActionLabel('Back')->schema([
                        Select::make('blocking_record')->label('Blocked record')->options(array_map(fn (array $blocker): string => $blocker['name'].' #'.$blocker['parent_id'].' ('.$blocker['count'].')', $choices))->default(0)->selectablePlaceholder(false)->live(),
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
                    throw ValidationException::withMessages(['preview' => 'Preview the selected records before applying changes.']);
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
                Notification::make()->title('Selected records updated')->success()->send();
            });
    }
}
