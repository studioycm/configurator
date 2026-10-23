<?php

namespace App\Filament\Resources\Configurators\RelationManagers;

use App\Actions\AddConfiguratorAttributes;
use App\Actions\SaveConfiguratorDefinition;
use App\Actions\SaveConfiguratorOption;
use App\ConfigInputType;
use App\Filament\Resources\Configurators\Actions\AddAttributesAction;
use App\Filament\Resources\Configurators\Concerns\InteractsWithConfiguratorTable;
use App\Filament\Resources\Configurators\Schemas\ConfiguratorAttributeForm;
use App\Filament\Resources\Configurators\Schemas\ConfiguratorFormErrors;
use App\Filament\Resources\Configurators\Schemas\ConfiguratorValueTextFields;
use App\Filament\Resources\DependencyActions;
use App\Filament\Resources\FormOrderControls;
use App\Filament\Resources\InteractsWithScopedTableSearch;
use App\Filament\Resources\ItemCountColumn;
use App\Filament\Resources\TablePresentation;
use App\Models\Configurator;
use App\Models\ConfiguratorAttribute;
use App\Models\ConfiguratorOption;
use App\Services\ConfiguratorDefinitionCompiler;
use App\Services\ConfiguratorDefinitionLoader;
use App\Services\ConfiguratorEditorChanges;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Renderless;

class AttributesRelationManager extends RelationManager
{
    use InteractsWithConfiguratorTable;
    use InteractsWithScopedTableSearch;

    protected static string $relationship = 'attributes';

    protected static bool $isLazy = false;

    public string $catalogCreationTab = 'existing';

    protected string $view = 'filament.resources.configurators.attributes';

    #[Locked]
    public ?int $selectedAttributeId = null;

    #[Locked]
    public ?int $initialAttributeId = null;

    public function mount(): void
    {
        parent::mount();
        if ($this->initialAttributeId !== null) {
            $this->selectAttribute($this->initialAttributeId);
        }
    }

    /** @var array<string, mixed> */
    public array $editorData = [];

    /** @var array<int, array<string, mixed>> */
    #[Locked]
    public array $editorDrafts = [];

    /** @var array<string, array<string, mixed>> */
    #[Locked]
    public array $previewOriginals = [];

    /** @var array<int, string> */
    #[Locked]
    public array $editorOriginals = [];

    /** @var array<int, bool> */
    #[Locked]
    public array $editorStaleRows = [];

    #[Locked]
    public ?int $selectedOptionId = null;

    /** @var array<string, mixed> */
    public array $optionEditorData = [];

    /** @var array<int, array<int, array<string, mixed>>> */
    #[Locked]
    public array $optionEditorDrafts = [];

    /** @var array<int, array<int, string>> */
    #[Locked]
    public array $optionEditorOriginals = [];

    /** @var array<int, int> */
    #[Locked]
    public array $selectedOptions = [];

    /** @var array<int, bool> */
    #[Locked]
    public array $optionEditorStaleRows = [];

    public function selectedOption(): ?ConfiguratorOption
    {
        return $this->selectedOptionId === null ? null : $this->selectedAttribute()?->options()->with('option.value')->findOrFail($this->selectedOptionId);
    }

    public function optionEditorForm(Schema $schema): Schema
    {
        return $schema->statePath('optionEditorData')->columns(1)->components(function (): array {
            $option = $this->selectedOption();
            if ($option === null) {
                return [];
            }

            return [
                View::make('filament.forms.validation-summary'),
                Hidden::make('option_id')->required()->rules(['integer']),
                ...ConfiguratorValueTextFields::fields(fn (Get $get): string => $option->option->value->label),
                TextInput::make('hint')->label(__('configurator.labels.local_hint'))->maxLength(1000),
                Toggle::make('hidden_by_default')->label(__('configurator.labels.initially_hidden')),
                Toggle::make('disabled_by_default')->label(__('configurator.labels.initially_disabled')),
            ];
        });
    }

    public function openPreviewSource(int $attributeId, int $optionId): bool
    {
        Gate::authorize('manage-catalog');
        $attribute = $this->owner()->attributes()->find($attributeId);
        if ($attribute === null || ! $attribute->options()->whereKey($optionId)->exists()) {
            return false;
        }
        $this->selectAttribute($attributeId);
        $this->selectOption($attributeId, $optionId);

        return true;
    }

    #[On('configurator-option-selected')]
    public function selectOption(int $attributeId, int $optionId, bool $reload = false): void
    {
        abort_unless($this->selectedAttributeId === $attributeId, 404);
        $option = $this->selectedAttribute()->options()->findOrFail($optionId);
        $this->stashOptionDraft();
        if ($reload) {
            unset($this->optionEditorDrafts[$attributeId][$optionId], $this->optionEditorStaleRows[$optionId]);
        }
        $this->selectedOptionId = $optionId;
        $this->selectedOptions[$attributeId] = $optionId;
        $this->resetValidation();
        $this->cacheSchema('optionEditorForm')->fill($this->optionEditorDrafts[$attributeId][$optionId] ?? $option->only(SaveConfiguratorOption::FIELDS));
        if ($reload || ! isset($this->optionEditorOriginals[$attributeId][$optionId])) {
            $this->optionEditorOriginals[$attributeId][$optionId] = hash('sha256', json_encode($this->optionEditorState($this->optionEditorData), JSON_THROW_ON_ERROR));
            $this->previewOriginals['value:'.$optionId] = $this->optionEditorState($this->optionEditorData);
        }
        $this->dispatch('configurator-option-editor-filled');
    }

    /** @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function optionEditorState(array $state): array
    {
        $persisted = [];
        foreach (ConfiguratorEditorChanges::VALUE_FIELDS as $field) {
            if (array_key_exists($field, $state)) {
                $persisted[$field] = $state[$field];
            }
        }

        return $persisted;
    }

    /** @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function attributeEditorState(array $state): array
    {
        unset($state['default_configurator_option_id'], $state['options']);

        return $state;
    }

    private function stashOptionDraft(): void
    {
        if ($this->selectedAttributeId === null || $this->selectedOptionId === null) {
            return;
        }
        if (hash('sha256', json_encode($this->optionEditorState($this->optionEditorData), JSON_THROW_ON_ERROR)) !== ($this->optionEditorOriginals[$this->selectedAttributeId][$this->selectedOptionId] ?? '') || filled($this->optionEditorData['_subtitle_custom_draft'] ?? null)) {
            $this->optionEditorDrafts[$this->selectedAttributeId][$this->selectedOptionId] = $this->optionEditorData;
        } else {
            unset($this->optionEditorDrafts[$this->selectedAttributeId][$this->selectedOptionId]);
        }
    }

    public function saveOptionEditor(): void
    {
        $attribute = $this->selectedAttribute();
        $option = $this->selectedOption();
        abort_unless($attribute && $option, 404);
        if (isset($this->optionEditorStaleRows[$option->id])) {
            throw ValidationException::withMessages(['optionEditorData' => __('configurator.validation.batch_changes_were_saved_reload_this_value')]);
        }
        ConfiguratorFormErrors::run(fn () => app(SaveConfiguratorOption::class)->handle(auth()->user(), $attribute, $option->id, $this->optionEditorForm->getState()), $this->optionEditorForm, '/^attributes\.\d+\.options\.\d+\./');
        $this->selectOption($attribute->id, $option->id, reload: true);
        $this->dispatch('configurator-options-updated');
        $this->saved();
    }

    public function closeOptionEditor(): void
    {
        Gate::authorize('manage-catalog');
        foreach (array_keys($this->optionEditorOriginals[$this->selectedAttributeId] ?? []) as $optionId) {
            unset($this->optionEditorStaleRows[$optionId]);
        }
        unset($this->selectedOptions[$this->selectedAttributeId], $this->optionEditorDrafts[$this->selectedAttributeId], $this->optionEditorOriginals[$this->selectedAttributeId]);
        $this->selectedOptionId = null;
        $this->optionEditorData = [];
        $this->dispatch('configurator-option-editor-filled');
    }

    public function reloadOptionEditor(): void
    {
        abort_unless($this->selectedAttributeId && $this->selectedOptionId, 404);
        $this->selectOption($this->selectedAttributeId, $this->selectedOptionId, reload: true);
    }

    #[Locked]
    public ?string $lastAppliedPreviewSave = null;

    /** @param list<array<string, mixed>> $captures @param array<string, array<string, mixed>> $newRules */
    #[On('configurator-preview-saved')]
    public function acceptPreviewSave(int $configuratorId, array $captures, array $newRules = [], ?string $operationId = null): void
    {
        Gate::authorize('manage-catalog');
        if ($configuratorId !== $this->owner()->id) {
            return;
        }
        if ($operationId !== null && $this->lastAppliedPreviewSave === $operationId) {
            $this->dispatch('configurator-preview-editor-reconciled', configuratorId: $configuratorId, operationId: $operationId, componentId: $this->getId());

            return;
        }
        $changes = app(ConfiguratorEditorChanges::class);
        foreach ($changes->savedRows($configuratorId, $captures) as $row) {
            $id = (int) $row['id'];
            if ($row['type'] === 'attribute') {
                $current = $this->selectedAttributeId === $id ? $this->editorData : ($this->editorDrafts[$id] ?? null);
                $savedState = $this->attributeEditorState([...($this->previewOriginals['attribute:'.$id] ?? []), ...$row['after']]);
                $this->previewOriginals['attribute:'.$id] = $savedState;
                $this->editorOriginals[$id] = hash('sha256', json_encode($savedState, JSON_THROW_ON_ERROR));
                if ($current !== null && $changes->normalize('attribute', $this->attributeEditorState($current)) == $changes->normalize('attribute', $savedState)) {
                    unset($this->editorDrafts[$id], $this->editorStaleRows[$id]);
                    if ($this->selectedAttributeId === $id) {
                        $this->selectAttribute($id, reload: true);
                    }
                }
            } elseif ($row['type'] === 'value') {
                $value = ConfiguratorOption::whereHas('configuratorAttribute', fn ($query) => $query->where('configurator_id', $configuratorId))->findOrFail($id);
                $attributeId = $value->configurator_attribute_id;
                $current = $this->selectedOptionId === $id ? $this->optionEditorData : ($this->optionEditorDrafts[$attributeId][$id] ?? null);
                $savedState = $this->optionEditorState($value->only(ConfiguratorEditorChanges::VALUE_FIELDS));
                $this->previewOriginals['value:'.$id] = $savedState;
                $this->optionEditorOriginals[$attributeId][$id] = hash('sha256', json_encode($savedState, JSON_THROW_ON_ERROR));
                if ($current !== null && $changes->normalize('value', $current) == $changes->normalize('value', $savedState)) {
                    unset($this->optionEditorDrafts[$attributeId][$id], $this->optionEditorStaleRows[$id]);
                    $customDraft = $current['_subtitle_custom_draft'] ?? null;
                    if ($this->selectedOptionId === $id) {
                        $this->selectOption($attributeId, $id, reload: true);
                        $this->optionEditorData['_subtitle_custom_draft'] = $customDraft;
                    } elseif (filled($customDraft)) {
                        $this->optionEditorDrafts[$attributeId][$id] = [...$current, ...$savedState];
                    }
                }
            }
        }
        $this->flushCachedTableRecords();
        $this->dispatch('configurator-options-updated');
        if ($operationId !== null) {
            $this->lastAppliedPreviewSave = $operationId;
            $this->dispatch('configurator-preview-editor-reconciled', configuratorId: $configuratorId, operationId: $operationId, componentId: $this->getId());
        }
    }

    /** @return array<string, mixed> */
    #[Renderless]
    public function exportPreviewChanges(): array
    {
        Gate::authorize('manage-catalog');
        $attributes = $this->editorDrafts;
        if ($this->selectedAttributeId !== null) {
            $attributes[$this->selectedAttributeId] = $this->editorData;
        }
        $values = $this->optionEditorDrafts;
        if ($this->selectedAttributeId !== null && $this->selectedOptionId !== null) {
            $values[$this->selectedAttributeId][$this->selectedOptionId] = $this->optionEditorData;
        }
        $rows = [];
        foreach ($attributes as $id => $state) {
            $state = $this->attributeEditorState($state);
            $before = $this->attributeEditorState($this->previewOriginals['attribute:'.$id] ?? $state);
            if ($state != $before) {
                $rows[] = ['type' => 'attribute', 'id' => (string) $id, 'before' => $before, 'after' => $state];
            }
        }
        foreach ($values as $attributeValues) {
            foreach ($attributeValues as $id => $state) {
                $state = $this->optionEditorState($state);
                $before = $this->optionEditorState($this->previewOriginals['value:'.$id] ?? $state);
                if ($state != $before) {
                    $rows[] = ['type' => 'value', 'id' => (string) $id, 'before' => $before, 'after' => $state];
                }
            }
        }

        return app(ConfiguratorEditorChanges::class)->capture($this->owner()->id, $rows);
    }

    public function editorForm(Schema $schema): Schema
    {
        return $schema->statePath('editorData')->columns(1)->components(fn (): array => $this->selectedAttributeId === null ? [] : ConfiguratorAttributeForm::components($this->owner(), $this->selectedAttribute(), withOptions: false, withDefault: false));
    }

    public function selectedAttribute(): ?ConfiguratorAttribute
    {
        return $this->selectedAttributeId === null ? null : $this->owner()->attributes()->with('attribute')->findOrFail($this->selectedAttributeId);
    }

    public function selectAttribute(int $id, bool $reload = false): void
    {
        $attribute = $this->owner()->attributes()->findOrFail($id);
        $attributeChanged = $this->selectedAttributeId !== $id;
        if ($attributeChanged) {
            $this->stashOptionDraft();
            $this->selectedOptionId = null;
            $this->optionEditorData = [];
        }
        if (! $reload && $this->selectedAttributeId !== null) {
            if (hash('sha256', json_encode($this->attributeEditorState($this->editorData), JSON_THROW_ON_ERROR)) !== ($this->editorOriginals[$this->selectedAttributeId] ?? '')) {
                $this->editorDrafts[$this->selectedAttributeId] = $this->attributeEditorState($this->editorData);
            } else {
                unset($this->editorDrafts[$this->selectedAttributeId]);
            }
        }
        if ($reload) {
            unset($this->editorDrafts[$id], $this->editorStaleRows[$id]);
        }
        $this->selectedAttributeId = $attribute->id;
        $this->resetValidation();
        $data = $this->attributeEditorState($this->editorDrafts[$id] ?? $this->inclusionDraft($id));
        $this->cacheSchema('editorForm')->fill($data);
        if ($reload || ! isset($this->editorOriginals[$id])) {
            $this->editorOriginals[$id] = hash('sha256', json_encode($this->attributeEditorState($this->editorData), JSON_THROW_ON_ERROR));
            $this->previewOriginals['attribute:'.$id] = $this->attributeEditorState($this->editorData);
        }
        $this->dispatch('configurator-editor-filled');
        if ($this->selectedOptionId === null && isset($this->selectedOptions[$id])) {
            if ($attribute->options()->whereKey($this->selectedOptions[$id])->exists()) {
                $this->selectOption($id, $this->selectedOptions[$id]);
            } else {
                unset($this->selectedOptions[$id], $this->optionEditorDrafts[$id]);
            }
        }
        if ($attributeChanged) {
            $this->dispatch('configurator-option-editor-filled');
        }
    }

    public function closeEditor(): void
    {
        Gate::authorize('manage-catalog');
        $this->selectedAttributeId = null;
        $this->editorData = [];
        $this->editorDrafts = [];
        $this->editorOriginals = [];
        $this->editorStaleRows = [];
        $this->selectedOptionId = null;
        $this->optionEditorData = [];
        $this->optionEditorDrafts = [];
        $this->optionEditorOriginals = [];
        $this->selectedOptions = [];
        $this->optionEditorStaleRows = [];
        $this->resetValidation();
        $this->dispatch('configurator-editor-filled');
        $this->dispatch('configurator-option-editor-filled');
    }

    public function saveEditor(): void
    {
        $attribute = $this->selectedAttribute();
        abort_unless($attribute, 404);
        if (isset($this->editorStaleRows[$attribute->id])) {
            throw ValidationException::withMessages(['editorData' => __('configurator.validation.batch_changes_were_saved_for_this_record')]);
        }
        $this->saveInclusion($attribute->id, $this->attributeEditorState($this->editorForm->getState()));
        $this->selectAttribute($attribute->id, reload: true);
        $this->dispatch('configurator-attribute-updated', attributeId: $attribute->id);
    }

    public function reloadEditor(): void
    {
        abort_if($this->selectedAttributeId === null, 404);
        $this->selectAttribute($this->selectedAttributeId, reload: true);
    }

    /** @param list<int> $ids */
    public function reconcileBatchChanges(array $ids, bool $removed): void
    {
        Gate::authorize('manage-catalog');
        foreach ($ids as $id) {
            if ($removed) {
                unset($this->editorDrafts[$id], $this->editorOriginals[$id], $this->editorStaleRows[$id]);
                unset($this->optionEditorDrafts[$id], $this->optionEditorOriginals[$id], $this->selectedOptions[$id]);
                if ($this->selectedAttributeId === $id) {
                    $this->selectedAttributeId = null;
                    $this->editorData = [];
                    $this->selectedOptionId = null;
                    $this->optionEditorData = [];
                    $this->resetValidation();
                    $this->dispatch('configurator-editor-filled');
                }
            } elseif (isset($this->editorDrafts[$id]) || ($this->selectedAttributeId === $id && hash('sha256', json_encode($this->attributeEditorState($this->editorData), JSON_THROW_ON_ERROR)) !== ($this->editorOriginals[$id] ?? ''))) {
                $this->editorStaleRows[$id] = true;
            } elseif ($this->selectedAttributeId === $id) {
                $this->selectAttribute($id, reload: true);
            } else {
                unset($this->editorOriginals[$id]);
            }
        }
    }

    #[On('configurator-options-updated')]
    public function refreshOptions(): void
    {
        Gate::authorize('manage-catalog');
        if ($this->selectedOptionId !== null && ! $this->selectedAttribute()?->options()->whereKey($this->selectedOptionId)->exists()) {
            unset($this->optionEditorDrafts[$this->selectedAttributeId][$this->selectedOptionId], $this->selectedOptions[$this->selectedAttributeId]);
            $this->selectedOptionId = null;
            $this->optionEditorData = [];
            $this->dispatch('configurator-option-editor-filled');
        }
        $this->flushCachedTableRecords();
    }

    /** @param list<int> $ids */
    #[On('catalog-batch-applied')]
    public function reconcileOptionBatch(string $model, array $ids): void
    {
        Gate::authorize('manage-catalog');
        if ($model !== ConfiguratorOption::class) {
            return;
        }
        $this->stashOptionDraft();
        $options = ConfiguratorOption::whereIn('configurator_attribute_id', $this->owner()->attributes()->select('id'))->whereIn('id', $ids)->get()->keyBy('id');
        foreach ($this->optionEditorOriginals as $attributeId => $originals) {
            foreach (array_keys($originals) as $optionId) {
                if (! in_array($optionId, $ids, true)) {
                    continue;
                }
                if (! isset($options[$optionId])) {
                    unset($this->optionEditorDrafts[$attributeId][$optionId], $this->optionEditorOriginals[$attributeId][$optionId], $this->optionEditorStaleRows[$optionId]);
                    if (($this->selectedOptions[$attributeId] ?? null) === $optionId) {
                        unset($this->selectedOptions[$attributeId]);
                    }
                    if ($this->selectedOptionId === $optionId) {
                        $this->selectedOptionId = null;
                        $this->optionEditorData = [];
                        $this->dispatch('configurator-option-editor-filled');
                    }
                } elseif (isset($this->optionEditorDrafts[$attributeId][$optionId]) && $this->optionEditorState($this->optionEditorDrafts[$attributeId][$optionId]) != ($this->previewOriginals['value:'.$optionId] ?? [])) {
                    $this->optionEditorStaleRows[$optionId] = true;
                } else {
                    $customDraft = $this->optionEditorDrafts[$attributeId][$optionId]['_subtitle_custom_draft'] ?? null;
                    unset($this->optionEditorDrafts[$attributeId][$optionId], $this->optionEditorStaleRows[$optionId]);
                    if ($this->selectedOptionId === $optionId) {
                        $this->selectOption($attributeId, $optionId, reload: true);
                        $this->optionEditorData['_subtitle_custom_draft'] = $customDraft;
                    } elseif (filled($customDraft)) {
                        $fresh = $options[$optionId]->only(SaveConfiguratorOption::FIELDS);
                        $this->optionEditorDrafts[$attributeId][$optionId] = [...$fresh, '_subtitle_custom_draft' => $customDraft];
                        $this->previewOriginals['value:'.$optionId] = $this->optionEditorState($fresh);
                        $this->optionEditorOriginals[$attributeId][$optionId] = hash('sha256', json_encode($this->optionEditorState($fresh), JSON_THROW_ON_ERROR));
                    } else {
                        unset($this->optionEditorOriginals[$attributeId][$optionId]);
                    }
                }
            }
        }
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Configurator && Gate::allows('manage-catalog');
    }

    public function table(Table $table): Table
    {
        return TablePresentation::configure($table->heading('')->modifyQueryUsing(fn ($query) => $query->with(['attribute', 'defaultOption.option.value'])->withCount('options'))
            ->columns([
                TextColumn::make('attribute.label')->label(__('catalog.resources.attributes.singular'))->searchable(['label_override', 'help_text'], query: fn ($query, string $search) => $query->where('label_override', 'like', '%'.$search.'%')->orWhere('help_text', 'like', '%'.$search.'%')->orWhereHas('attribute', fn ($query) => $query->where('label', 'like', '%'.$search.'%')->orWhere('key', 'like', '%'.$search.'%')))->wrap()->formatStateUsing(fn (ConfiguratorAttribute $record): string => $record->label_override ?? $record->attribute->label),
                TextColumn::make('attribute.key')->label(__('configurator.labels.canonical_key'))->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('label_override')->label(__('configurator.labels.local_label'))->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('help_text')->label(__('configurator.labels.help_text'))->searchable()->toggleable(isToggledHiddenByDefault: true),
                ItemCountColumn::make('options_count', __('catalog.attribute_values.short_plural'), 'inclusion-options'),
                TextColumn::make('defaultOption.option.code')->label(__('configurator.inclusions.fields.default_code')),
                TextColumn::make('defaultOption.option.value.label')->label(__('configurator.inclusions.fields.default_ui_name'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('code_order')->label(__('configurator.labels.code_position'))->formatStateUsing(fn (int $state): int => $state + 1)->toggleable(isToggledHiddenByDefault: true),
            ])->defaultSort('display_order')->reorderable('display_order')->paginated(false)
            ->recordAction('edit')->recordClasses(fn (ConfiguratorAttribute $record): ?string => $record->id === $this->selectedAttributeId ? 'catalog-selected-row' : null)
            ->headerActions([
                AddAttributesAction::make($this->owner()),
                Action::make('codeOrder')->label(__('configurator.labels.code_order'))->authorize('manage-catalog')
                    ->schema([FormOrderControls::make('order')->label(__('configurator.labels.configuration_code_order'))->schema([Hidden::make('id'), TextInput::make('label')->label(__('ui.fields.label'))->readOnly()->dehydrated(false)])->addable(false)->deletable(false)->reorderableWithButtons()])
                    ->fillForm(fn (): array => ['order' => $this->owner()->attributes()->with('attribute')->orderBy('code_order')->get()->map(fn (ConfiguratorAttribute $attribute): array => ['id' => $attribute->id, 'label' => $attribute->label_override ?? $attribute->attribute->label])->all()])
                    ->action(fn (array $data) => $this->saveCodeOrder(array_column($data['order'], 'id'))),
            ])->recordActions([
                ...$this->workspaceOrderActions(fn (int $id, int $direction) => $this->moveAttribute($id, $direction)),
                Action::make('edit')->label(__('ui.actions.edit'))->authorize('manage-catalog')
                    ->extraAttributes(['data-editor-switch' => true])
                    ->action(fn (ConfiguratorAttribute $record) => $this->selectAttribute($record->id)),
                DependencyActions::local(Action::make('remove')->label(__('ui.actions.remove'))->color('danger')->authorize('manage-catalog')->requiresConfirmation()
                    ->schema([View::make('filament.forms.validation-summary')])
                    ->modalDescription(__('configurator.help.remove_this_local_inclusion_and_its_included'))
                    ->action(fn (ConfiguratorAttribute $record) => $this->removeInclusion($record->id))),
            ]), 'configurator-attributes', true, ['includeMany']);
    }

    /** @param list<int|string> $ids @param list<array<string, mixed>> $rows */
    public function includeAttributes(array $ids, array $rows): void
    {
        Gate::authorize('manage-catalog');
        ConfiguratorFormErrors::run(function () use ($ids, $rows): Configurator {
            $reviews = [];
            foreach (array_values($rows) as $reviewIndex => $row) {
                $refs = [];
                foreach ([$row, ...($row['options'] ?? [])] as $entry) {
                    $ref = $entry['id'] ?? null;
                    if (is_string($ref) && str_starts_with($ref, 'new:')) {
                        $refs[$ref] = Str::isUuid(substr($ref, 4)) ? $ref : 'new:'.Str::uuid();
                    }
                }
                $settings = array_intersect_key($row, array_flip(['input_type', 'label_override', 'help_text']));
                $settings += ['input_type' => 'toggle', 'label_override' => null, 'help_text' => null];
                if ($settings['input_type'] instanceof ConfigInputType) {
                    $settings['input_type'] = $settings['input_type']->value;
                }
                $values = [];
                foreach (array_values($row['options'] ?? []) as $valueIndex => $value) {
                    $subtitle = app(ConfiguratorDefinitionCompiler::class)->normalizeSubtitle(array_intersect_key($value, array_flip(['subtitle_mode', 'display_value_override'])), 'reviews.'.$reviewIndex.'.values.'.$valueIndex.'.local_fields');
                    $values[] = ['ref' => $refs[$value['id'] ?? ''] ?? ($value['id'] ?? null), 'option_id' => $value['option_id'] ?? null,
                        'local_fields' => ['label_override' => null, 'hint' => null, 'hidden_by_default' => false, 'disabled_by_default' => false, ...array_intersect_key($value, array_flip(['label_override', 'hint', 'hidden_by_default', 'disabled_by_default'])), ...$subtitle]];
                }
                $reviews[] = ['ref' => $refs[$row['id'] ?? ''] ?? ($row['id'] ?? null), 'attribute_id' => $row['attribute_id'] ?? null, 'settings' => $settings, 'default_ref' => $refs[$row['default_configurator_option_id'] ?? ''] ?? ($row['default_configurator_option_id'] ?? null), 'values' => $values];
            }

            return app(AddConfiguratorAttributes::class)->handle(auth()->user(), $this->owner(), ['existing_attribute_ids' => $ids, 'reviews' => $reviews]);
        }, $this->getMountedActionSchema());
        $this->saved();
    }

    public function creationSaved(): void
    {
        Gate::authorize('manage-catalog');
        $this->saved();
    }

    /** @param array<string, mixed> $data */
    public function saveInclusion(?int $id, array $data): void
    {
        if (($data['input_type'] ?? null) instanceof ConfigInputType) {
            $data['input_type'] = $data['input_type']->value;
        }
        try {
            app(SaveConfiguratorDefinition::class)->change(auth()->user(), $this->owner(), function (array $draft) use ($id, $data): array {
                $index = null;
                foreach ($draft['attributes'] as $key => $row) {
                    if ((string) $row['id'] === (string) $id) {
                        $index = $key;
                    }
                }
                if ($id !== null && $index === null) {
                    throw ValidationException::withMessages(['attribute' => __('configurator.validation.this_inclusion_does_not_belong_to_this')]);
                }
                if (array_diff(array_keys($data), ['id', 'attribute_id', 'input_type', 'label_override', 'help_text', 'default_configurator_option_id', 'options']) !== []) {
                    throw ValidationException::withMessages(['attribute' => __('configurator.validation.unsupported_inclusion_fields')]);
                }
                $old = $index === null ? ['display_order' => ($draft['attributes'] === [] ? -1 : max(array_column($draft['attributes'], 'display_order'))) + 1, 'code_order' => ($draft['attributes'] === [] ? -1 : max(array_column($draft['attributes'], 'code_order'))) + 1] : $draft['attributes'][$index];
                $row = [...$old, ...$data];
                if ($id !== null) {
                    $row['id'] = (string) $id;
                }
                $row['options'] = array_values($row['options'] ?? []);
                foreach ($row['options'] as $order => &$option) {
                    $option['display_order'] = $order;
                }
                unset($option);
                if ($index === null) {
                    $draft['attributes'][] = $row;
                } else {
                    $draft['attributes'][$index] = $row;
                }

                return $draft;
            });
        } catch (\Throwable $exception) {
            ConfiguratorFormErrors::rethrow($exception, $this->getMountedActionSchema() ?? ($this->selectedAttributeId !== null ? $this->editorForm : null), '/^attributes\.\d+\./');
        }
        $this->saved();
    }

    public function removeInclusion(int $id): void
    {
        ConfiguratorFormErrors::run(fn () => app(SaveConfiguratorDefinition::class)->change(auth()->user(), $this->owner(), function (array $draft) use ($id): array {
            if (! in_array((string) $id, array_column($draft['attributes'], 'id'), true)) {
                throw ValidationException::withMessages(['attribute' => __('configurator.validation.this_inclusion_does_not_belong_to_this')]);
            }
            $draft['attributes'] = array_values(array_filter($draft['attributes'], fn (array $row): bool => (string) $row['id'] !== (string) $id));

            return $draft;
        }), $this->getMountedActionSchema());
        if ($this->selectedAttributeId === $id) {
            $this->closeEditor();
        }
        $this->saved();
    }

    public function reorderTable(array $order, int|string|null $draggedRecordKey = null): void
    {
        ConfiguratorFormErrors::run(fn () => app(SaveConfiguratorDefinition::class)->reorder(auth()->user(), $this->owner(), 'attributes', $order), $this->getMountedActionSchema());
        $this->saved();
    }

    /** @param list<int|string> $ids */
    public function saveCodeOrder(array $ids): void
    {
        ConfiguratorFormErrors::run(fn () => app(SaveConfiguratorDefinition::class)->reorder(auth()->user(), $this->owner(), 'code', $ids), $this->getMountedActionSchema());
        $this->saved();
    }

    public function moveAttribute(int $id, int $direction): void
    {
        $this->assertWorkspaceOrder();
        $actor = auth()->user();
        $owner = $this->owner();
        ConfiguratorFormErrors::run(fn () => app(SaveConfiguratorDefinition::class)->change($actor, $owner, function (array $draft) use ($id, $direction): array {
            if (! in_array($direction, [-1, 1], true)) {
                throw ValidationException::withMessages(['order' => __('configurator.validation.choose_move_up_or_move_down')]);
            }
            usort($draft['attributes'], fn (array $a, array $b): int => $a['display_order'] <=> $b['display_order']);
            $index = array_search((string) $id, array_column($draft['attributes'], 'id'), true);
            if ($index === false) {
                throw ValidationException::withMessages(['order' => __('configurator.validation.this_inclusion_does_not_belong_to_this')]);
            }
            $next = $index + $direction;
            if (isset($draft['attributes'][$next])) {
                [$draft['attributes'][$index], $draft['attributes'][$next]] = [$draft['attributes'][$next], $draft['attributes'][$index]];
            }
            foreach ($draft['attributes'] as $order => &$row) {
                $row['display_order'] = $order;
            }

            return $draft;
        }), $this->getMountedActionSchema());
        $this->saved();
    }

    /** @return array<string, mixed> */
    private function inclusionDraft(int $id): array
    {
        foreach (app(ConfiguratorDefinitionLoader::class)->draft($this->owner())['attributes'] as $row) {
            if ((string) $row['id'] === (string) $id) {
                unset($row['display_order'], $row['code_order']);
                foreach ($row['options'] as &$option) {
                    unset($option['display_order']);
                }

                return $row;
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
        Notification::make()->title(__('configurator.headings.local_inclusion_saved'))->success()->send();
    }
}
