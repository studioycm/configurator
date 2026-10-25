<?php

namespace App\Filament\Resources\Configurators\RelationManagers;

use App\Actions\AddConfiguratorValues;
use App\Actions\SaveConfiguratorDefinition;
use App\Actions\SaveConfiguratorOption;
use App\Filament\Resources\Configurators\Actions\AddValuesAction;
use App\Filament\Resources\Configurators\Concerns\InteractsWithConfiguratorTable;
use App\Filament\Resources\Configurators\Schemas\ConfiguratorFormErrors;
use App\Filament\Resources\DependencyActions;
use App\Filament\Resources\HasWorkspaceTable;
use App\Filament\Resources\InteractsWithScopedTableSearch;
use App\Filament\Resources\TablePresentation;
use App\Filament\Resources\ValueTagFilter;
use App\Models\ConfiguratorAttribute;
use App\Models\ConfiguratorOption;
use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\View;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;

class OptionsRelationManager extends RelationManager implements HasWorkspaceTable
{
    /** @return list<string> */
    public function tableProfileControlNames(): array
    {
        return ['tableSearchScope', 'tableSearchFields', 'showSelection', 'showOrderControls'];
    }

    /** @return list<string> */
    public function tableQuickFilterNames(): array
    {
        return ['tags', ...$this->workspaceQuickFilterNames()];
    }

    use InteractsWithConfiguratorTable;
    use InteractsWithScopedTableSearch;

    protected static string $relationship = 'options';

    protected static bool $isLazy = false;

    public string $catalogCreationTab = 'existing';

    #[Reactive]
    public ?int $selectedOptionId = null;

    protected ?ConfiguratorAttribute $workspaceOwner = null;

    #[On('configurator-attribute-updated')]
    public function refreshAttribute(int $attributeId): void
    {
        $this->workspaceOwner = null;
        if ($this->owner()->id === $attributeId) {
            $this->orderedWorkspaceRows = null;
            $this->flushCachedTableRecords();
        }
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('configurator.inclusions.values.for_attribute', ['attribute' => $ownerRecord->label_override ?? $ownerRecord->attribute->label]);
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof ConfiguratorAttribute && Gate::allows('manage-catalog');
    }

    /** @return array{column: string, revision: string} */
    public function tableProfileColumnMigration(): ?array
    {
        return ['column' => 'stored_default', 'revision' => 'saved-default-v1'];
    }

    public function table(Table $table): Table
    {
        return TablePresentation::configure($table->modifyQueryUsing(fn ($query) => $query->with('option.value'))
            ->columns([
                IconColumn::make('stored_default')->label(__('ui.fields.default'))->boolean()
                    ->state(fn (ConfiguratorOption $record): bool => $record->id === $this->owner()->default_configurator_option_id)
                    ->tooltip(fn (ConfiguratorOption $record): string => $this->defaultLabel($record))
                    ->action(Action::make('setDefault')->authorize('manage-catalog')
                        ->label(fn (ConfiguratorOption $record): string => $this->defaultLabel($record))
                        ->action(function (ConfiguratorOption $record): void {
                            $this->setDefault($record->id);
                        })),
                TextColumn::make('label_override')->label(__('configurator.inclusions.values.fields.local_ui_name'))->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('display_value_override')->label(__('configurator.subtitles.local'))->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('hint')->label(__('configurator.labels.hint'))->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('option.code')->label(__('catalog.fields.code'))->searchable(),
                TextColumn::make('option.value.label')->label(__('catalog.fields.value_ui_name'))->tooltip(__('configurator.inclusions.values.fields.name_source'))->searchable()->wrap()->formatStateUsing(fn (ConfiguratorOption $record): string => $record->label_override ?? $record->option->value->label),
                TextColumn::make('option.value.semantic_id')->label(__('catalog.fields.semantic_id'))->searchable()->toggleable()->copyable(),
                TextColumn::make('option.value.tags')->label(__('catalog.fields.tags'))->badge(),
                IconColumn::make('hidden_by_default')->label(__('ui.status.hidden'))->boolean()->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('disabled_by_default')->label(__('ui.status.disabled'))->boolean()->toggleable(isToggledHiddenByDefault: true),
            ])->filters([
                SelectFilter::make('availability')->label(__('configurator.labels.initial_availability'))->options(['visible' => __('ui.status.visible'), 'hidden' => __('ui.status.hidden'), 'disabled' => __('ui.status.disabled')])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? '') {
                        'visible' => $query->where('hidden_by_default', false)->where('disabled_by_default', false),
                        'hidden' => $query->where('hidden_by_default', true),
                        'disabled' => $query->where('disabled_by_default', true),
                        default => $query,
                    }),
                ValueTagFilter::make('option.value'),
            ])->defaultSort('display_order')->reorderable('display_order')->paginated(false)->recordAction('edit')
            ->recordClasses(fn (ConfiguratorOption $record): ?string => $record->id === $this->selectedOptionId ? 'catalog-selected-row' : null)
            ->headerActions([
                AddValuesAction::make($this->owner()),
            ])->recordActions([
                ...$this->workspaceOrderActions(fn (int $id, int $direction) => $this->moveOption($id, $direction)),
                Action::make('edit')->label(__('ui.actions.edit'))->authorize('manage-catalog')->extraAttributes(['data-option-editor-switch' => true])
                    ->action(fn (ConfiguratorOption $record) => $this->dispatch('configurator-option-selected', attributeId: $this->owner()->id, optionId: $record->id)->to(AttributesRelationManager::class)),
                DependencyActions::local(Action::make('remove')->label(__('ui.actions.remove'))->color('danger')->authorize('manage-catalog')->requiresConfirmation()
                    ->schema([View::make('filament.forms.validation-summary')])
                    ->modalDescription(__('configurator.help.choose_another_stored_default_and_repair_rule'))
                    ->action(fn (ConfiguratorOption $record) => $this->removeOption($record->id))),
            ]), 'configurator-options', true, ['includeMany'], tagRelationship: 'option.value', selectionVisible: fn (): bool => $this->showSelection, workspaceActions: fn (): array => $this->workspaceVisibilityActions(), rowActions: fn (): array => $this->rowOrderingActions());
    }

    public function setDefault(int $id): void
    {
        Gate::authorize('manage-catalog');
        $this->workspaceOwner = null;
        $owner = $this->owner();
        ConfiguratorFormErrors::run(function () use ($owner, $id): void {
            if (! $owner->options()->whereKey($id)->exists()) {
                throw ValidationException::withMessages(['option' => __('configurator.validation.this_included_value_does_not_belong_to')]);
            }
            if ($owner->default_configurator_option_id === $id) {
                return;
            }
            app(SaveConfiguratorDefinition::class)->change(auth()->user(), $owner->configurator, function (array $draft) use ($owner, $id): array {
                $index = array_search((string) $owner->id, array_column($draft['attributes'], 'id'), true);
                abort_if($index === false, 404);
                if (! in_array((string) $id, array_column($draft['attributes'][$index]['options'], 'id'), true)) {
                    throw ValidationException::withMessages(['option' => __('configurator.validation.this_included_value_does_not_belong_to')]);
                }
                $draft['attributes'][$index]['default_configurator_option_id'] = (string) $id;

                return $draft;
            });
            $this->saved();
        }, $this->getMountedActionSchema());
    }

    private function defaultLabel(ConfiguratorOption $record): string
    {
        return __($record->id === $this->owner()->default_configurator_option_id ? 'configurator.inclusions.values.current_default' : 'configurator.creation.set_default', ['value' => $record->label_override ?? $record->option->value->label]);
    }

    /** @param list<int|string> $ids */
    public function includeOptions(array $ids): void
    {
        Gate::authorize('manage-catalog');
        $owner = $this->owner();
        ConfiguratorFormErrors::run(fn () => app(AddConfiguratorValues::class)->handle(auth()->user(), $owner->configurator, $owner->id, ['existing_option_ids' => $ids]), $this->getMountedActionSchema());
        $this->saved();
    }

    public function creationSaved(): void
    {
        Gate::authorize('manage-catalog');
        $this->saved();
    }

    /** @param array<string, mixed> $data */
    public function saveOption(?int $id, array $data): void
    {
        ConfiguratorFormErrors::run(fn () => app(SaveConfiguratorOption::class)->handle(auth()->user(), $this->owner(), $id, $data), $this->getMountedActionSchema(), '/^attributes\.\d+\.(options\.\d+\.)?/');
        $this->saved();
    }

    public function removeOption(int $id): void
    {
        $this->changeOptions(function (array $attribute) use ($id): array {
            if (! in_array((string) $id, array_column($attribute['options'], 'id'), true)) {
                throw ValidationException::withMessages(['option' => __('configurator.validation.this_included_value_does_not_belong_to')]);
            }
            $attribute['options'] = array_values(array_filter($attribute['options'], fn (array $row): bool => (string) $row['id'] !== (string) $id));

            return $attribute;
        });
    }

    public function moveOption(int $id, int $direction): void
    {
        $this->assertWorkspaceOrder();
        $this->changeOptions(function (array $attribute) use ($id, $direction): array {
            if (! in_array($direction, [-1, 1], true)) {
                throw ValidationException::withMessages(['order' => __('configurator.validation.choose_move_up_or_move_down')]);
            }
            usort($attribute['options'], fn (array $a, array $b): int => $a['display_order'] <=> $b['display_order']);
            $index = array_search((string) $id, array_column($attribute['options'], 'id'), true);
            if ($index === false) {
                throw ValidationException::withMessages(['order' => __('configurator.validation.this_included_value_does_not_belong_to_2')]);
            }
            $next = $index + $direction;
            if (isset($attribute['options'][$next])) {
                [$attribute['options'][$index], $attribute['options'][$next]] = [$attribute['options'][$next], $attribute['options'][$index]];
            }

            return $attribute;
        });
    }

    public function reorderTable(array $order, int|string|null $draggedRecordKey = null): void
    {
        $owner = $this->owner();
        ConfiguratorFormErrors::run(fn () => app(SaveConfiguratorDefinition::class)->reorder(auth()->user(), $owner->configurator, 'options:'.$owner->id, $order), $this->getMountedActionSchema());
        $this->saved();
    }

    /** @param Closure(array<string, mixed>): array<string, mixed> $change */
    private function changeOptions(Closure $change): void
    {
        $owner = $this->owner();
        try {
            app(SaveConfiguratorDefinition::class)->change(auth()->user(), $owner->configurator, function (array $draft) use ($owner, $change): array {
                $index = array_search((string) $owner->id, array_column($draft['attributes'], 'id'), true);
                abort_if($index === false, 404);
                $attribute = $change($draft['attributes'][$index]);
                foreach ($attribute['options'] as $position => &$option) {
                    $option['display_order'] = $position;
                }
                unset($option);
                $draft['attributes'][$index] = $attribute;

                return $draft;
            });
        } catch (\Throwable $exception) {
            ConfiguratorFormErrors::rethrow($exception, $this->getMountedActionSchema(), '/^attributes\.\d+\.(options\.\d+\.)?/');
        }
        $this->saved();
    }

    private function owner(): ConfiguratorAttribute
    {
        Gate::authorize('manage-catalog');

        return $this->workspaceOwner ??= ConfiguratorAttribute::findOrFail($this->getOwnerRecord()->getKey());
    }

    private function saved(): void
    {
        $this->workspaceOwner = null;
        $this->orderedWorkspaceRows = null;
        $this->flushCachedTableRecords();
        $this->dispatch('configurator-options-updated');
        $this->dispatch('configurator-updated');
        Notification::make()->title(__('configurator.headings.values_saved'))->success()->send();
    }
}
