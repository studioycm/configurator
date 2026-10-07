<?php

namespace App\Filament\Resources;

use App\Actions\ChangeCatalogStatus;
use App\Filament\Resources\Configurators\Schemas\ConfiguratorFormErrors;
use App\Models\Attribute;
use App\Models\Configurator;
use App\Models\ConfiguratorAttribute;
use App\Models\ConfiguratorRule;
use App\Models\Group;
use App\Models\Option;
use App\Models\Product;
use App\Services\CanonicalUsage;
use App\Services\CatalogRevisions;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StatusActions
{
    public static function configure(Table $table, string $key): void
    {
        $class = match ($key) {
            'attributes' => Attribute::class, 'options', 'attribute-options' => Option::class,
            'configurators' => Configurator::class, 'groups' => Group::class, 'products' => Product::class,
            'configurator-attributes' => ConfiguratorAttribute::class, 'configurator-rules' => ConfiguratorRule::class,
            default => null,
        };
        if ($class === null) {
            return;
        }
        $columns = $table->getColumns();
        $columns['is_active'] = IconColumn::make('is_active')->label('Active')->boolean()->sortable()->toggleable()
            ->tooltip(fn (Model $record): string => $record->is_active ? 'Active — change status' : 'Disabled — change status')
            ->action(self::make(Action::make('changeStatus'), $class));
        if ($class === Option::class) {
            $columns['is_hidden'] = IconColumn::make('is_hidden')->label('Hidden')->boolean()->toggleable()
                ->trueIcon(Heroicon::OutlinedEyeSlash)->falseIcon(Heroicon::OutlinedEye)->trueColor('gray')->falseColor('success')
                ->tooltip(fn (Option $record): string => $record->is_hidden ? 'Hidden — change visibility' : 'Visible — change visibility')
                ->action(self::make(Action::make('changeVisibility'), $class));
        }
        $table->columns(array_values($columns));
        $table->pushFilters([SelectFilter::make('is_active')->label('Status')->options(['1' => 'Active', '0' => 'Disabled'])]);
        if ($class === Option::class) {
            $table->pushFilters([SelectFilter::make('is_hidden')->label('Visibility')->options(['0' => 'Visible', '1' => 'Hidden'])]);
        }
        $table->pushToolbarActions([BulkActionGroup::make([self::make(BulkAction::make('batchStatus'), $class)->deselectRecordsAfterCompletion()])->label('Status')]);
    }

    /** @param class-string<Model> $class */
    public static function make(Action $action, string $class): Action
    {
        $schema = [Hidden::make('review_token')->required(), ToggleButtons::make('is_active')->label('Status')->options(['1' => 'Active', '0' => 'Disabled'])->inline()->required()->live()];
        if ($class === Option::class) {
            $schema[] = Select::make('visibility')->options(['unchanged' => 'Keep each visibility', 'visible' => 'Visible', 'hidden' => 'Hidden'])->default('unchanged')->required()
                ->hintAction(FormHints::make('Hidden and Disabled both prevent selection. Existing inclusions and rule references remain stored. An unavailable stored default requires repair.'));
        }
        if ($class === Configurator::class) {
            $schema[] = Select::make('group_behavior')->label('Assigned Groups and Products')->options([
                'visible' => 'Keep visible, without configuration', 'hide' => 'Hide while this Configurator is disabled', 'unassign' => 'Unassign Groups; keep Products visible without configuration',
            ])->visible(fn (Get $get): bool => (string) $get('is_active') === '0')->required(fn (Get $get): bool => (string) $get('is_active') === '0')
                ->hintAction(FormHints::make('Hiding preserves Group and Product flags and assignments. Re-enabling restores visibility according to their own flags. Unassigning keeps stored definitions but does not restore assignments when re-enabled.'));
        }
        $schema[] = View::make('filament.resources.status-impact')->viewData(fn (): array => ['impacts' => self::impacts(self::selection($action))]);

        return $action->label('Change status')->authorize('manage-catalog')->slideOver()->schema($schema)->modalSubmitActionLabel('Apply status')->stickyModalHeader()->stickyModalFooter()
            ->fillForm(fn (Action $action, ?Model $record): array => ['is_active' => $record === null ? null : ($record->is_active ? '1' : '0'), 'visibility' => $record instanceof Option ? ($record->is_hidden ? 'hidden' : 'visible') : 'unchanged', 'review_token' => self::reviewToken(self::selection($action))])
            ->modalDescription('Review the affected records before applying. Related records keep their own status and stored references.')
            ->extraModalFooterActions(fn (Action $action): array => self::inspectionActions(self::selection($action)))
            ->action(function (Action $action, array $data, $livewire): void {
                Validator::make($data, ['is_active' => ['required', 'boolean'], 'visibility' => ['sometimes', Rule::in(['unchanged', 'visible', 'hidden'])]])->validate();
                $selection = self::selection($action)->sortBy('id');
                ConfiguratorFormErrors::run(fn () => DB::transaction(function () use ($selection, $data): void {
                    app(CatalogRevisions::class)->batch(function () use ($selection, $data): void {
                        $locked = app(ChangeCatalogStatus::class)->lockForReview($selection);
                        if ($locked->count() !== $selection->count() || ! hash_equals(self::reviewToken($locked, locked: true), $data['review_token'] ?? '')) {
                            throw ValidationException::withMessages(['is_active' => 'These records or their assignments changed. Close and reopen the status dialog to review the current impact.']);
                        }
                        app(ChangeCatalogStatus::class)->handleSelection(auth()->user(), $locked, (bool) $data['is_active'], $data['group_behavior'] ?? null,
                            hidden: isset($data['visibility']) && $data['visibility'] !== 'unchanged' ? $data['visibility'] === 'hidden' : null);
                    });
                }, attempts: 3), $livewire->getSchema($livewire->getMountedActionSchemaName()));
                if (method_exists($livewire, 'flushCachedTableRecords')) {
                    $livewire->flushCachedTableRecords();
                }
                $livewire->dispatch('catalog-batch-applied', model: $selection->first()::class, ids: $selection->pluck('id')->all());
                $livewire->dispatch('configurator-updated');
                $livewire->dispatch('refresh-sidebar');
                $livewire->dispatch('catalog-record-saved');
                Notification::make()->title('Status updated')->success()->send();
            });
    }

    private static function selection(Action $action): Collection
    {
        return $action instanceof BulkAction ? $action->getSelectedRecords() : collect($action->getRecord() === null ? [] : [$action->getRecord()]);
    }

    private static function reviewToken(Collection $records, bool $locked = false): string
    {
        $states = $records->sortBy('id')->map(function (Model $record) use ($locked): array {
            $fresh = $locked ? $record : $record->fresh();
            if ($fresh === null) {
                return [$record::class, $record->id, null];
            }
            $related = match (true) {
                $fresh instanceof Configurator => [
                    'groups' => $fresh->groups()->orderBy('id')->get(['id', 'is_active', 'configurator_id', 'catalog_revision'])->toArray(),
                    'products' => Product::whereIn('group_id', $fresh->groups()->select('id'))->orderBy('id')->get(['id', 'group_id', 'is_active', 'updated_at'])->toArray(),
                ],
                $fresh instanceof Attribute, $fresh instanceof Option => app(CanonicalUsage::class)->counts($fresh),
                default => [],
            };

            return [$fresh::class, $fresh->getAttributes(), $related];
        })->values()->all();

        return hash_hmac('sha256', json_encode([auth()->id(), $states], JSON_THROW_ON_ERROR), config('app.key'));
    }

    /** @return list<array{name: string, groups: int, products: int, configurators: int}> */
    private static function impacts(Collection $records): array
    {
        return $records->map(fn (Model $record): array => [
            'name' => $record->name ?? $record->label ?? $record->code ?? '#'.$record->id,
            'groups' => $record instanceof Configurator ? $record->groups()->count() : 0,
            'products' => $record instanceof Configurator ? Product::whereIn('group_id', $record->groups()->select('id'))->count() : 0,
            'configurators' => $record instanceof Attribute || $record instanceof Option ? app(CanonicalUsage::class)->configurators($record)->count() : 0,
        ])->values()->all();
    }

    /** @return array<Action> */
    private static function inspectionActions(Collection $records): array
    {
        if (! $records->first() instanceof Configurator) {
            return [];
        }
        $choices = $records->pluck('name', 'id')->all();

        return array_map(fn (string $type): Action => Action::make('status-'.$type)->label('View affected '.ucfirst($type))->color('gray')->slideOver()->modalSubmitAction(false)->modalCancelActionLabel('Back')->schema([
            Select::make('configurator')->options($choices)->default(array_key_first($choices))->selectablePlaceholder(false)->live(),
            View::make('filament.resources.item-list-content')->viewData(fn (Get $get): array => ['listKey' => 'configurator-'.$type, 'parentId' => array_key_exists($get('configurator'), $choices) ? (int) $get('configurator') : (int) array_key_first($choices)]),
        ]), ['groups', 'products']);
    }
}
