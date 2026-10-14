<?php

namespace App\Filament\Resources\Configurators\RelationManagers;

use App\Actions\SaveConfiguratorDefinition;
use App\Filament\Resources\Configurators\Concerns\InteractsWithConfiguratorTable;
use App\Filament\Resources\Configurators\Schemas\ConfiguratorFormErrors;
use App\Filament\Resources\Configurators\Schemas\ConfiguratorRuleForm;
use App\Filament\Resources\InteractsWithScopedTableSearch;
use App\Filament\Resources\ItemCountColumn;
use App\Filament\Resources\TablePresentation;
use App\Models\Configurator;
use App\Models\ConfiguratorRule;
use App\Services\ConfiguratorDefinitionLoader;
use App\Services\ConfiguratorRuleDraft;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;

class RulesRelationManager extends RelationManager
{
    use InteractsWithConfiguratorTable;
    use InteractsWithScopedTableSearch;

    protected static string $relationship = 'rules';

    protected static bool $isLazy = false;

    protected string $view = 'filament.resources.configurators.rules';

    #[Locked]
    public ?int $selectedRuleId = null;

    #[Locked]
    public ?int $initialRuleId = null;

    public function mount(): void
    {
        parent::mount();
        if ($this->initialRuleId !== null) {
            $this->selectRule($this->initialRuleId);
        }
    }

    #[Locked]
    public ?string $editorKind = null;

    /** @var array<string, mixed> */
    public array $editorData = [];

    /** @var array<string, array<string, mixed>> */
    #[Locked]
    public array $editorDrafts = [];

    /** @var array<string, string> */
    #[Locked]
    public array $editorOriginals = [];

    /** @var array<string, bool> */
    #[Locked]
    public array $editorStaleRows = [];

    public function editorForm(Schema $schema): Schema
    {
        return $schema->statePath('editorData')->columns(1)->components(fn (): array => $this->editorKind === null ? [] : ConfiguratorRuleForm::components($this->owner(), $this->editorKind));
    }

    public function selectRule(int $id, bool $reload = false): void
    {
        $rule = $this->owner()->rules()->findOrFail($id);
        $this->stashEditorDraft();
        if ($reload) {
            unset($this->editorDrafts['rule:'.$id], $this->editorStaleRows['rule:'.$id]);
        }
        $this->selectedRuleId = $rule->id;
        $this->editorKind = $rule->kind;
        $this->resetValidation();
        $this->cacheSchema('editorForm')->fill($this->editorDrafts['rule:'.$id] ?? $this->ruleDraft($id));
        if ($reload || ! isset($this->editorOriginals['rule:'.$id])) {
            $this->editorOriginals['rule:'.$id] = hash('sha256', json_encode($this->editorData, JSON_THROW_ON_ERROR));
        }
        $this->dispatch('configurator-editor-filled');
    }

    public function createRule(string $kind): void
    {
        Gate::authorize('manage-catalog');
        abort_unless(in_array($kind, ['Mapping', 'Advanced'], true), 422);
        $this->stashEditorDraft();
        $this->selectedRuleId = null;
        $this->editorKind = $kind;
        $this->resetValidation();
        $this->cacheSchema('editorForm')->fill($this->editorDrafts['new:'.$kind] ?? app(ConfiguratorRuleDraft::class)->fromRule(['id' => 'new:'.Str::uuid(), 'label' => '', 'kind' => $kind, 'is_active' => true, 'priority' => 0, 'driver_configurator_attribute_id' => null, 'target_configurator_attribute_id' => null, 'conditions' => [], 'effects' => [], 'sets' => []]));
        $this->editorOriginals['new:'.$kind] ??= hash('sha256', json_encode($this->editorData, JSON_THROW_ON_ERROR));
        $this->dispatch('configurator-editor-filled');
    }

    private function stashEditorDraft(): void
    {
        if ($this->editorKind !== null) {
            $key = $this->selectedRuleId === null ? 'new:'.$this->editorKind : 'rule:'.$this->selectedRuleId;
            if (hash('sha256', json_encode($this->editorData, JSON_THROW_ON_ERROR)) !== ($this->editorOriginals[$key] ?? '')) {
                $this->editorDrafts[$key] = $this->editorData;
            } else {
                unset($this->editorDrafts[$key]);
            }
        }
    }

    public function reloadEditor(): void
    {
        abort_if($this->selectedRuleId === null, 404);
        $this->selectRule($this->selectedRuleId, reload: true);
    }

    /** @param list<int> $ids */
    public function reconcileBatchChanges(array $ids, bool $removed): void
    {
        Gate::authorize('manage-catalog');
        foreach ($ids as $id) {
            $key = 'rule:'.$id;
            if ($removed) {
                unset($this->editorDrafts[$key], $this->editorOriginals[$key], $this->editorStaleRows[$key]);
                if ($this->selectedRuleId === $id) {
                    $this->selectedRuleId = null;
                    $this->editorKind = null;
                    $this->editorData = [];
                    $this->resetValidation();
                    $this->dispatch('configurator-editor-filled');
                }
            } elseif (isset($this->editorDrafts[$key]) || ($this->selectedRuleId === $id && hash('sha256', json_encode($this->editorData, JSON_THROW_ON_ERROR)) !== ($this->editorOriginals[$key] ?? ''))) {
                $this->editorStaleRows[$key] = true;
            } elseif ($this->selectedRuleId === $id) {
                $this->selectRule($id, reload: true);
            } else {
                unset($this->editorOriginals[$key]);
            }
        }
    }

    public function closeEditor(): void
    {
        Gate::authorize('manage-catalog');
        $this->selectedRuleId = null;
        $this->editorKind = null;
        $this->editorData = [];
        $this->editorDrafts = [];
        $this->editorOriginals = [];
        $this->editorStaleRows = [];
        $this->resetValidation();
        $this->dispatch('configurator-editor-filled');
    }

    public function saveEditor(): void
    {
        Gate::authorize('manage-catalog');
        abort_if($this->editorKind === null, 404);
        if ($this->selectedRuleId !== null && isset($this->editorStaleRows['rule:'.$this->selectedRuleId])) {
            throw ValidationException::withMessages(['editorData' => __('configurator.validation.batch_changes_were_saved_for_this_record')]);
        }
        $this->editorForm->getState();
        $state = $this->editorForm->getRawState();
        $this->saveRule($this->selectedRuleId, $this->editorKind, $state instanceof Arrayable ? $state->toArray() : $state);
        if ($this->selectedRuleId === null) {
            unset($this->editorDrafts['new:'.$this->editorKind], $this->editorOriginals['new:'.$this->editorKind]);
            $this->editorKind = null;
            $this->editorData = [];
            $this->dispatch('configurator-editor-filled');
        } else {
            $this->selectRule($this->selectedRuleId, reload: true);
        }
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Configurator && Gate::allows('manage-catalog');
    }

    #[On('configurator-updated')]
    public function refreshDefinition(): void
    {
        Gate::authorize('manage-catalog');
        $this->cacheSchema('editorForm');
        $this->flushCachedTableRecords();
    }

    public function table(Table $table): Table
    {
        return TablePresentation::configure($table->heading('')->modifyQueryUsing(fn ($query) => $query->with(['driverAttribute.attribute', 'targetAttribute.attribute', 'mappingSets', 'conditionGroups', 'conditions.sourceAttribute.attribute', 'conditions.optionReferences.configuratorOption.option', 'effects.targetAttribute.attribute', 'effects.optionReferences.configuratorOption.option'])->withCount(['mappingSets', 'effects']))
            ->columns([
                TextColumn::make('label')->label(__('ui.fields.label'))->wrap()->searchable(fn (): bool => ! $this->isTableReordering), TextColumn::make('kind')->label(__('ui.fields.kind'))->badge()->searchable(),
                TextColumn::make('summary')->label(__('configurator.labels.when_then'))->state(fn (ConfiguratorRule $record): string => $record->workspaceSummary())
                    ->tooltip(fn (ConfiguratorRule $record): string => $record->workspaceSummary())->wrap()->toggleable(),
                TextColumn::make('scope')->label(__('configurator.labels.scope'))->state(fn (ConfiguratorRule $record): string => $record->isFuturePublicOnly() ? __('configurator.rules.scope.public') : 'Dashboard')
                    ->badge()->color(fn (ConfiguratorRule $record): string => $record->isFuturePublicOnly() ? 'warning' : 'gray')->toggleable(),
                ItemCountColumn::make('mapping_sets_count', __('configurator.labels.sets'), 'rule-mappings')->toggleable(isToggledHiddenByDefault: true),
                ItemCountColumn::make('effects_count', __('configurator.labels.effects'), 'rule-effects')->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')->label(__('ui.status.enabled'))->boolean(),
            ])->filters([SelectFilter::make('scope')->label(__('configurator.labels.scope'))->options(['dashboard' => __('configurator.labels.dashboard'), 'public' => __('configurator.labels.future_public_only')])
            ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? '') {
                'dashboard' => $query->whereDoesntHave('conditions', fn (Builder $query): Builder => $query->whereIn('source_kind', ['Territory', 'Application'])),
                'public' => $query->whereHas('conditions', fn (Builder $query): Builder => $query->whereIn('source_kind', ['Territory', 'Application'])),
                default => $query,
            })])->defaultSort('priority', 'desc')->reorderable('priority', direction: 'desc')->paginated(false)
            ->recordAction('edit')->recordClasses(fn (ConfiguratorRule $record): ?string => $record->id === $this->selectedRuleId ? 'catalog-selected-row' : null)
            ->headerActions([ActionGroup::make([$this->createAction('Mapping'), $this->createAction('Advanced')])->label(__('configurator.labels.add_rule'))->icon(Heroicon::OutlinedPlus)->tooltip(__('configurator.help.add_rule'))])
            ->recordActions([
                ...$this->workspaceOrderActions(fn (int $id, int $direction) => $this->moveRule($id, $direction)),
                Action::make('edit')->label(__('ui.actions.edit'))->authorize('manage-catalog')
                    ->extraAttributes(['data-editor-switch' => true])
                    ->action(fn (ConfiguratorRule $record) => $this->selectRule($record->id)),
                Action::make('remove')->label(__('ui.actions.remove'))->color('danger')->authorize('manage-catalog')->requiresConfirmation()
                    ->schema([View::make('filament.forms.validation-summary')])
                    ->modalDescription(__('configurator.help.remove_this_rule_and_its_owned_conditions'))
                    ->action(fn (ConfiguratorRule $record) => $this->removeRule($record->id)),
            ]), 'configurator-rules', true, ['addMapping', 'addAdvanced']);
    }

    private function createAction(string $kind): Action
    {
        return Action::make('add'.$kind)->label($kind === 'Mapping' ? __('configurator.labels.add_mapping') : __('configurator.labels.add_advanced_rule'))->authorize('manage-catalog')->icon(Heroicon::OutlinedPlus)
            ->extraAttributes(['data-editor-switch' => true])
            ->action(fn () => $this->createRule($kind));
    }

    /** @param array<string, mixed> $data */
    public function saveRule(?int $id, string $kind, array $data): void
    {
        $owner = $this->owner();
        try {
            app(SaveConfiguratorDefinition::class)->change(auth()->user(), $owner, function (array $draft) use ($id, $kind, $data): array {
                $index = array_search((string) $id, array_column($draft['rules'], 'id'), true);
                if ($id !== null && $index === false) {
                    throw ValidationException::withMessages(['rule' => __('configurator.validation.this_rule_does_not_belong_to_this')]);
                }
                $expected = $id === null ? $kind : $draft['rules'][$index]['kind'];
                if (! in_array($expected, ['Mapping', 'Advanced'], true) || ($data['kind'] ?? null) !== $expected || $kind !== $expected) {
                    throw ValidationException::withMessages(['kind' => __('configurator.validation.rule_kind_is_fixed_create_a_separate')]);
                }
                $rule = app(ConfiguratorRuleDraft::class)->toRule($data);
                if ($id !== null) {
                    $rule['id'] = (string) $id;
                    $rule['priority'] = $draft['rules'][$index]['priority'];
                    $draft['rules'][$index] = $rule;
                } else {
                    if (! str_starts_with((string) $rule['id'], 'new:')) {
                        throw ValidationException::withMessages(['id' => __('configurator.validation.new_rules_require_a_new_staging_key')]);
                    }
                    $rule['priority'] = $draft['rules'] === [] ? 0 : max(array_column($draft['rules'], 'priority')) + 1;
                    $draft['rules'][] = $rule;
                }

                return $draft;
            });
        } catch (\Throwable $exception) {
            ConfiguratorFormErrors::rethrow($exception, $this->editorKind === null ? null : $this->editorForm, '/^rules\.\d+\./');
        }
        $this->saved();
    }

    public function removeRule(int $id): void
    {
        ConfiguratorFormErrors::run(fn () => app(SaveConfiguratorDefinition::class)->change(auth()->user(), $this->owner(), function (array $draft) use ($id): array {
            if (! in_array((string) $id, array_column($draft['rules'], 'id'), true)) {
                throw ValidationException::withMessages(['rule' => __('configurator.validation.this_rule_does_not_belong_to_this')]);
            }
            $draft['rules'] = array_values(array_filter($draft['rules'], fn (array $rule): bool => (string) $rule['id'] !== (string) $id));

            return $draft;
        }), $this->getMountedActionSchema());
        if ($this->selectedRuleId === $id) {
            $this->closeEditor();
        }
        $this->saved();
    }

    public function reorderTable(array $order, int|string|null $draggedRecordKey = null): void
    {
        ConfiguratorFormErrors::run(fn () => app(SaveConfiguratorDefinition::class)->reorder(auth()->user(), $this->owner(), 'rules', $order), $this->getMountedActionSchema());
        $this->saved();
    }

    public function moveRule(int $id, int $direction): void
    {
        $this->assertWorkspaceOrder();
        ConfiguratorFormErrors::run(fn () => app(SaveConfiguratorDefinition::class)->change(auth()->user(), $this->owner(), function (array $draft) use ($id, $direction): array {
            if (! in_array($direction, [-1, 1], true)) {
                throw ValidationException::withMessages(['order' => __('configurator.validation.choose_move_up_or_move_down')]);
            }
            usort($draft['rules'], fn (array $a, array $b): int => $b['priority'] <=> $a['priority']);
            $index = array_search((string) $id, array_column($draft['rules'], 'id'), true);
            if ($index === false) {
                throw ValidationException::withMessages(['order' => __('configurator.validation.this_rule_does_not_belong_to_this')]);
            }
            $next = $index + $direction;
            if (isset($draft['rules'][$next])) {
                [$draft['rules'][$index], $draft['rules'][$next]] = [$draft['rules'][$next], $draft['rules'][$index]];
            }
            foreach ($draft['rules'] as $rank => &$rule) {
                $rule['priority'] = count($draft['rules']) - 1 - $rank;
            }

            return $draft;
        }), $this->getMountedActionSchema());
        $this->saved();
    }

    /** @return array<string, mixed> */
    private function ruleDraft(int $id): array
    {
        foreach (app(ConfiguratorDefinitionLoader::class)->draft($this->owner())['rules'] as $rule) {
            if ((string) $rule['id'] === (string) $id) {
                return app(ConfiguratorRuleDraft::class)->fromRule($rule);
            }
        }
        abort(404);
    }

    private function owner(): Configurator
    {
        Gate::authorize('manage-catalog');

        return Configurator::findOrFail($this->getOwnerRecord()->getKey());
    }

    private function saved(): void
    {
        $this->orderedWorkspaceRows = null;
        $this->flushCachedTableRecords();
        $this->dispatch('configurator-updated');
        Notification::make()->title(__('configurator.headings.rules_saved'))->success()->send();
    }
}
