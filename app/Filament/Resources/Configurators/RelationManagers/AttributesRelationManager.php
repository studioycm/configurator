<?php

namespace App\Filament\Resources\Configurators\RelationManagers;

use App\Actions\SaveConfiguratorDefinition;
use App\Actions\SaveConfiguratorOption;
use App\ConfigInputType;
use App\Filament\Resources\AttributeSelectionTable;
use App\Filament\Resources\Configurators\Concerns\InteractsWithConfiguratorTable;
use App\Filament\Resources\Configurators\Schemas\ConfiguratorAttributeForm;
use App\Filament\Resources\Configurators\Schemas\ConfiguratorFormErrors;
use App\Filament\Resources\DependencyActions;
use App\Filament\Resources\InteractsWithScopedTableSearch;
use App\Filament\Resources\ItemCountColumn;
use App\Filament\Resources\TablePresentation;
use App\Models\Attribute;
use App\Models\Configurator;
use App\Models\ConfiguratorAttribute;
use App\Models\ConfiguratorOption;
use App\Models\Option;
use App\Services\ConfiguratorDefinitionLoader;
use App\Services\ConfiguratorInclusionDrafts;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TableSelect;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;

class AttributesRelationManager extends RelationManager
{
    use InteractsWithConfiguratorTable;
    use InteractsWithScopedTableSearch;

    protected static string $relationship = 'attributes';

    protected static bool $isLazy = false;

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
                TextInput::make('label_override')->label('Local option label')->maxLength(255)->placeholder($option->option->value->label),
                TextInput::make('display_value_override')->label('Local display value')->maxLength(255)->placeholder($option->option->value->label),
                TextInput::make('hint')->label('Local hint')->maxLength(1000),
                Toggle::make('hidden_by_default')->label('Initially hidden'),
                Toggle::make('disabled_by_default')->label('Initially disabled'),
            ];
        });
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
            $this->optionEditorOriginals[$attributeId][$optionId] = hash('sha256', json_encode($this->optionEditorData, JSON_THROW_ON_ERROR));
        }
        $this->dispatch('configurator-option-editor-filled');
    }

    private function stashOptionDraft(): void
    {
        if ($this->selectedAttributeId === null || $this->selectedOptionId === null) {
            return;
        }
        if (hash('sha256', json_encode($this->optionEditorData, JSON_THROW_ON_ERROR)) !== ($this->optionEditorOriginals[$this->selectedAttributeId][$this->selectedOptionId] ?? '')) {
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
            throw ValidationException::withMessages(['optionEditorData' => 'Batch changes were saved. Reload this Option before saving; your draft was kept.']);
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

    public function editorForm(Schema $schema): Schema
    {
        return $schema->statePath('editorData')->columns(1)->components(fn (): array => $this->selectedAttributeId === null ? [] : ConfiguratorAttributeForm::components($this->owner(), $this->selectedAttribute(), withOptions: false));
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
            if (hash('sha256', json_encode($this->editorData, JSON_THROW_ON_ERROR)) !== ($this->editorOriginals[$this->selectedAttributeId] ?? '')) {
                $this->editorDrafts[$this->selectedAttributeId] = $this->editorData;
            } else {
                unset($this->editorDrafts[$this->selectedAttributeId]);
            }
        }
        if ($reload) {
            unset($this->editorDrafts[$id], $this->editorStaleRows[$id]);
        }
        $this->selectedAttributeId = $attribute->id;
        $this->resetValidation();
        $data = $this->editorDrafts[$id] ?? $this->inclusionDraft($id);
        unset($data['options']);
        $this->cacheSchema('editorForm')->fill($data);
        if ($reload || ! isset($this->editorOriginals[$id])) {
            $this->editorOriginals[$id] = hash('sha256', json_encode($this->editorData, JSON_THROW_ON_ERROR));
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
            throw ValidationException::withMessages(['editorData' => 'Batch changes were saved for this record. Reload the saved record before editing again. Your draft has been kept.']);
        }
        $this->saveInclusion($attribute->id, $this->editorForm->getState());
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
            } elseif (isset($this->editorDrafts[$id]) || ($this->selectedAttributeId === $id && hash('sha256', json_encode($this->editorData, JSON_THROW_ON_ERROR)) !== ($this->editorOriginals[$id] ?? ''))) {
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
                } elseif (isset($this->optionEditorDrafts[$attributeId][$optionId])) {
                    $this->optionEditorStaleRows[$optionId] = true;
                } elseif ($this->selectedOptionId === $optionId) {
                    $this->selectOption($attributeId, $optionId, reload: true);
                } else {
                    unset($this->optionEditorOriginals[$attributeId][$optionId], $this->optionEditorStaleRows[$optionId]);
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
        return TablePresentation::configure($table->modifyQueryUsing(fn ($query) => $query->with(['attribute', 'defaultOption.option.value'])->withCount('options'))
            ->columns([
                TextColumn::make('attribute.label')->label('Attribute')->searchable(['label_override', 'help_text'], query: fn ($query, string $search) => $query->where('label_override', 'like', '%'.$search.'%')->orWhere('help_text', 'like', '%'.$search.'%')->orWhereHas('attribute', fn ($query) => $query->where('label', 'like', '%'.$search.'%')->orWhere('key', 'like', '%'.$search.'%')))->wrap()->formatStateUsing(fn (ConfiguratorAttribute $record): string => $record->label_override ?? $record->attribute->label),
                TextColumn::make('attribute.key')->label('Canonical key')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('label_override')->label('Local label')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('help_text')->label('Help text')->searchable()->toggleable(isToggledHiddenByDefault: true),
                ItemCountColumn::make('options_count', 'Options', 'inclusion-options'),
                TextColumn::make('defaultOption.option.code')->label('Default code'),
                TextColumn::make('defaultOption.option.value.label')->label('Default Value')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('code_order')->label('Code position')->formatStateUsing(fn (int $state): int => $state + 1)->toggleable(isToggledHiddenByDefault: true),
            ])->defaultSort('display_order')->reorderable('display_order')->paginated(false)
            ->recordAction('edit')->recordClasses(fn (ConfiguratorAttribute $record): ?string => $record->id === $this->selectedAttributeId ? 'catalog-selected-row' : null)
            ->headerActions([
                Action::make('includeMany')->label('Include Attributes')->authorize('manage-catalog')->slideOver()
                    ->schema([
                        View::make('filament.forms.configurator-review-errors'),
                        Hidden::make('review_drafts')->default([])->dehydrated(false),
                        TableSelect::make('selected')->label('Shared Attributes')->multiple()->required()->minItems(1)->live()->tableConfiguration(AttributeSelectionTable::class)->tableArguments(fn (): array => ['owner_id' => $this->owner()->id])
                            ->afterStateUpdated(function (Set $set, Get $get, ?array $state): void {
                                $rows = collect($get('review_drafts') ?? [])->replace(collect($get('review') ?? [])->keyBy('attribute_id'));
                                $set('review_drafts', $rows->all());
                                $set('review', array_map(fn ($id): array => $rows->get((int) $id) ?? app(ConfiguratorInclusionDrafts::class)->attribute((int) $id), $state ?? []));
                            }),
                        Repeater::make('review')->label('Review Attributes and choose each stored default')->defaultItems(0)->addable(false)->deletable(false)->reorderable(false)
                            ->collapsible()->itemLabel(function (array $state): string {
                                $attribute = Attribute::find($state['attribute_id'] ?? null);
                                $default = collect($state['options'] ?? [])->firstWhere('id', $state['default_configurator_option_id'] ?? null);
                                $option = $default ? Option::find($default['option_id']) : null;

                                return ($attribute?->label ?? 'Attribute').' · '.($attribute?->key ?? '').' · '.count($state['options'] ?? []).' Options · Default: '.($option?->code ?? 'choose required');
                            })
                            ->schema(fn (): array => ConfiguratorAttributeForm::reviewComponents($this->owner()))->columnSpanFull(),
                    ])->action(fn (array $data) => $this->includeAttributes($data['selected'], $data['review'])),
                Action::make('codeOrder')->label('Code order')->authorize('manage-catalog')
                    ->schema([Repeater::make('order')->label('Configuration code order')->schema([Hidden::make('id'), TextInput::make('label')->readOnly()->dehydrated(false)])->addable(false)->deletable(false)->reorderableWithButtons()])
                    ->fillForm(fn (): array => ['order' => $this->owner()->attributes()->with('attribute')->orderBy('code_order')->get()->map(fn (ConfiguratorAttribute $attribute): array => ['id' => $attribute->id, 'label' => $attribute->label_override ?? $attribute->attribute->label])->all()])
                    ->action(fn (array $data) => $this->saveCodeOrder(array_column($data['order'], 'id'))),
            ])->recordActions([
                ...$this->workspaceOrderActions(fn (int $id, int $direction) => $this->moveAttribute($id, $direction)),
                Action::make('edit')->label('Edit')->authorize('manage-catalog')
                    ->extraAttributes(['data-editor-switch' => true])
                    ->action(fn (ConfiguratorAttribute $record) => $this->selectAttribute($record->id)),
                DependencyActions::local(Action::make('remove')->label('Remove')->color('danger')->authorize('manage-catalog')->requiresConfirmation()
                    ->schema([View::make('filament.forms.validation-summary')])
                    ->modalDescription('Remove this local inclusion and its local options. Referencing rules must be repaired first; shared canonical records remain available.')
                    ->action(fn (ConfiguratorAttribute $record) => $this->removeInclusion($record->id))),
            ]), 'configurator-attributes', true, ['includeMany']);
    }

    /** @param list<int|string> $ids @param list<array<string, mixed>> $rows */
    public function includeAttributes(array $ids, array $rows): void
    {
        $ids = Validator::make(['selected' => $ids], ['selected' => ['required', 'array', 'list', 'min:1'], 'selected.*' => ['required', 'integer', 'distinct', 'min:1']])->validate()['selected'];
        if (array_map('intval', $ids) !== array_map('intval', array_column($rows, 'attribute_id'))) {
            throw ValidationException::withMessages(['review' => 'Review every selected Attribute before including it.']);
        }
        $firstReviewIndex = null;
        try {
            app(SaveConfiguratorDefinition::class)->change(auth()->user(), $this->owner(), function (array $draft) use ($rows, &$firstReviewIndex): array {
                $firstReviewIndex = count($draft['attributes']);
                $displayOrder = ($draft['attributes'] === [] ? -1 : max(array_column($draft['attributes'], 'display_order'))) + 1;
                $codeOrder = ($draft['attributes'] === [] ? -1 : max(array_column($draft['attributes'], 'code_order'))) + 1;
                foreach ($rows as $reviewIndex => $row) {
                    if (empty($row['default_configurator_option_id'])) {
                        throw ValidationException::withMessages(['review.'.$reviewIndex.'.default_configurator_option_id' => 'Choose a stored default for every new Attribute.']);
                    }
                    if (($row['input_type'] ?? null) instanceof ConfigInputType) {
                        $row['input_type'] = $row['input_type']->value;
                    }
                    $row['options'] = array_values($row['options'] ?? []);
                    foreach ($row['options'] as $order => &$option) {
                        $option['display_order'] = $order;
                    }
                    unset($option);
                    $draft['attributes'][] = [...$row, 'display_order' => $displayOrder++, 'code_order' => $codeOrder++];
                }

                return $draft;
            });
        } catch (\Throwable $exception) {
            if ($exception instanceof ValidationException && $firstReviewIndex !== null) {
                $errors = [];
                foreach ($exception->errors() as $path => $messages) {
                    if (preg_match('/^attributes\.(\d+)(.*)$/', $path, $matches) && (int) $matches[1] >= $firstReviewIndex) {
                        $path = 'review.'.((int) $matches[1] - $firstReviewIndex).$matches[2];
                    }
                    $errors[$path] = $messages;
                }
                $exception = ValidationException::withMessages($errors);
            }
            ConfiguratorFormErrors::rethrow($exception, $this->getMountedActionSchema());
        }
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
                    throw ValidationException::withMessages(['attribute' => 'This inclusion does not belong to this Configurator.']);
                }
                if (array_diff(array_keys($data), ['id', 'attribute_id', 'input_type', 'label_override', 'help_text', 'default_configurator_option_id', 'options']) !== []) {
                    throw ValidationException::withMessages(['attribute' => 'Unsupported inclusion fields.']);
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
                throw ValidationException::withMessages(['attribute' => 'This inclusion does not belong to this Configurator.']);
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
        $this->resetTable();
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
                throw ValidationException::withMessages(['order' => 'Choose move up or move down.']);
            }
            usort($draft['attributes'], fn (array $a, array $b): int => $a['display_order'] <=> $b['display_order']);
            $index = array_search((string) $id, array_column($draft['attributes'], 'id'), true);
            if ($index === false) {
                throw ValidationException::withMessages(['order' => 'This inclusion does not belong to this Configurator.']);
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
        Notification::make()->title('Local inclusion saved')->success()->send();
    }
}
