<?php

namespace App\Filament\Resources\Configurators\RelationManagers;

use App\Actions\SaveConfiguratorDefinition;
use App\ConfigInputType;
use App\Filament\Resources\Configurators\Schemas\ConfiguratorAttributeForm;
use App\Filament\Resources\Configurators\Schemas\ConfiguratorFormErrors;
use App\Models\Configurator;
use App\Models\ConfiguratorAttribute;
use App\Services\ConfiguratorDefinitionLoader;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
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

class AttributesRelationManager extends RelationManager
{
    protected static string $relationship = 'attributes';

    protected static bool $isLazy = false;

    protected string $view = 'filament.resources.configurators.attributes';

    #[Locked]
    public ?int $selectedAttributeId = null;

    /** @var array<string, mixed> */
    public array $editorData = [];

    public function editorForm(Schema $schema): Schema
    {
        return $schema->statePath('editorData')->columns(1)->components(fn (): array => $this->selectedAttributeId === null ? [] : ConfiguratorAttributeForm::components($this->owner(), $this->selectedAttribute(), withOptions: false));
    }

    public function selectedAttribute(): ?ConfiguratorAttribute
    {
        return $this->selectedAttributeId === null ? null : $this->owner()->attributes()->with('attribute')->findOrFail($this->selectedAttributeId);
    }

    public function selectAttribute(int $id): void
    {
        $attribute = $this->owner()->attributes()->findOrFail($id);
        $this->selectedAttributeId = $attribute->id;
        $this->resetValidation();
        $data = $this->inclusionDraft($id);
        unset($data['options']);
        $this->cacheSchema('editorForm')->fill($data);
        $this->dispatch('configurator-editor-filled');
    }

    public function closeEditor(): void
    {
        Gate::authorize('manage-catalog');
        $this->selectedAttributeId = null;
        $this->editorData = [];
        $this->resetValidation();
        $this->dispatch('configurator-editor-filled');
    }

    public function saveEditor(): void
    {
        $attribute = $this->selectedAttribute();
        abort_unless($attribute, 404);
        $this->saveInclusion($attribute->id, $this->editorForm->getState());
        $this->selectAttribute($attribute->id);
        $this->dispatch('configurator-attribute-updated', attributeId: $attribute->id);
    }

    #[On('configurator-options-updated')]
    public function refreshOptions(): void
    {
        Gate::authorize('manage-catalog');
        $this->flushCachedTableRecords();
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Configurator && Gate::allows('manage-catalog');
    }

    public function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn ($query) => $query->with(['attribute', 'defaultOption.option.value'])->withCount('options'))
            ->columns([
                TextColumn::make('attribute.label')->label('Attribute')->wrap()->formatStateUsing(fn (ConfiguratorAttribute $record): string => $record->label_override ?? $record->attribute->label),
                TextColumn::make('attribute.key')->label('Canonical key')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('input_type')->label('Input')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('options_count')->label('Options'),
                TextColumn::make('defaultOption.option.code')->label('Default code'),
                TextColumn::make('defaultOption.option.value.label')->label('Default Value')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('code_order')->label('Code position')->formatStateUsing(fn (int $state): int => $state + 1)->toggleable(isToggledHiddenByDefault: true),
            ])->defaultSort('display_order')->reorderable('display_order')->paginated(false)
            ->recordAction('edit')->recordClasses(fn (ConfiguratorAttribute $record): ?string => $record->id === $this->selectedAttributeId ? 'catalog-selected-row' : null)
            ->headerActions([
                Action::make('include')->label('Include attribute')->authorize('manage-catalog')
                    ->schema(fn (): array => ConfiguratorAttributeForm::components($this->owner()))
                    ->fillForm(fn (): array => ['id' => 'new:'.Str::uuid(), 'attribute_id' => null, 'input_type' => 'toggle', 'label_override' => null, 'help_text' => null, 'default_configurator_option_id' => null, 'options' => []])
                    ->action(fn (array $data) => $this->saveInclusion(null, $data)),
                Action::make('codeOrder')->label('Code order')->authorize('manage-catalog')
                    ->schema([Repeater::make('order')->label('Configuration code order')->schema([Hidden::make('id'), TextInput::make('label')->readOnly()->dehydrated(false)])->addable(false)->deletable(false)->reorderableWithButtons()])
                    ->fillForm(fn (): array => ['order' => $this->owner()->attributes()->with('attribute')->orderBy('code_order')->get()->map(fn (ConfiguratorAttribute $attribute): array => ['id' => $attribute->id, 'label' => $attribute->label_override ?? $attribute->attribute->label])->all()])
                    ->action(fn (array $data) => $this->saveCodeOrder(array_column($data['order'], 'id'))),
            ])->recordActions([
                Action::make('edit')->label('Edit')->authorize('manage-catalog')
                    ->extraAttributes(['data-editor-switch' => true])
                    ->action(fn (ConfiguratorAttribute $record) => $this->selectAttribute($record->id)),
                ActionGroup::make([
                    Action::make('moveUp')->label('Move up')->authorize('manage-catalog')->action(fn (ConfiguratorAttribute $record) => $this->moveAttribute($record->id, -1)),
                    Action::make('moveDown')->label('Move down')->authorize('manage-catalog')->action(fn (ConfiguratorAttribute $record) => $this->moveAttribute($record->id, 1)),
                    Action::make('remove')->label('Remove')->color('danger')->authorize('manage-catalog')->requiresConfirmation()
                        ->schema([View::make('filament.forms.validation-summary')])
                        ->modalDescription('Remove this local inclusion and its local options. Referencing rules must be repaired first; shared canonical records remain available.')
                        ->action(fn (ConfiguratorAttribute $record) => $this->removeInclusion($record->id)),
                ]),
            ]);
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
        $this->resetTable();
        $this->dispatch('configurator-updated');
        Notification::make()->title('Local inclusion saved')->success()->send();
    }
}
