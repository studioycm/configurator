<?php

namespace App\Filament\Resources\Configurators\RelationManagers;

use App\Actions\SaveConfiguratorDefinition;
use App\Actions\SaveConfiguratorOption;
use App\Filament\Resources\Configurators\Concerns\InteractsWithConfiguratorTable;
use App\Filament\Resources\Configurators\Schemas\ConfiguratorFormErrors;
use App\Filament\Resources\DependencyActions;
use App\Filament\Resources\InteractsWithScopedTableSearch;
use App\Filament\Resources\OptionSelectionTable;
use App\Filament\Resources\TablePresentation;
use App\Filament\Resources\Values\Tables\ValuesTable;
use App\Models\ConfiguratorAttribute;
use App\Models\ConfiguratorOption;
use App\Models\Option;
use App\Services\ConfiguratorInclusionDrafts;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TableSelect;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\View;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;

class OptionsRelationManager extends RelationManager
{
    use InteractsWithConfiguratorTable;
    use InteractsWithScopedTableSearch;

    protected static string $relationship = 'options';

    protected static ?string $title = 'Included options';

    protected static bool $isLazy = false;

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

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof ConfiguratorAttribute && Gate::allows('manage-catalog');
    }

    public function table(Table $table): Table
    {
        return TablePresentation::configure($table->modifyQueryUsing(fn ($query) => $query->with('option.value'))
            ->columns([
                TextColumn::make('label_override')->label('Local label')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('display_value_override')->label('Display value')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('hint')->label('Hint')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('option.code')->label('Code')->searchable(),
                TextColumn::make('option.value.label')->label('Option')->searchable()->wrap()->formatStateUsing(fn (ConfiguratorOption $record): string => $record->label_override ?? $record->option->value->label),
                TextColumn::make('option.value.tags')->label('Tags')->badge(),
                IconColumn::make('stored_default')->label('Default')->boolean()->state(fn (ConfiguratorOption $record): bool => $record->id === $this->owner()->default_configurator_option_id),
                IconColumn::make('hidden_by_default')->label('Hidden')->boolean()->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('disabled_by_default')->label('Disabled')->boolean()->toggleable(isToggledHiddenByDefault: true),
            ])->filters([
                SelectFilter::make('availability')->label('Initial availability')->options(['visible' => 'Visible', 'hidden' => 'Hidden', 'disabled' => 'Disabled'])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? '') {
                        'visible' => $query->where('hidden_by_default', false)->where('disabled_by_default', false),
                        'hidden' => $query->where('hidden_by_default', true),
                        'disabled' => $query->where('disabled_by_default', true),
                        default => $query,
                    }),
                Filter::make('tags')->schema(fn (): array => [ValuesTable::tagFilterField()])
                    ->indicateUsing(fn (array $data): ?string => empty($data['values']) ? null : 'Tags: '.implode(', ', $data['values']))
                    ->query(fn (Builder $query, array $data): Builder => $query->when(! empty($data['values']), fn (Builder $query): Builder => $query->whereHas('option.value', function (Builder $query) use ($data): void {
                        $query->where(function (Builder $query) use ($data): void {
                            foreach (array_filter($data['values'], 'is_string') as $tag) {
                                $query->orWhereJsonContains('tags', $tag);
                            }
                        });
                    }))),
            ])->defaultSort('display_order')->reorderable('display_order')->paginated(false)->recordAction('edit')
            ->recordClasses(fn (ConfiguratorOption $record): ?string => $record->id === $this->selectedOptionId ? 'catalog-selected-row' : null)
            ->headerActions([
                Action::make('includeMany')->label('Include Options')->authorize('manage-catalog')->slideOver()
                    ->schema([TableSelect::make('selected')->label('Shared Options')->multiple()->required()->minItems(1)->tableConfiguration(OptionSelectionTable::class)->tableArguments(fn (): array => ['owner_id' => $this->owner()->id])])
                    ->action(fn (array $data) => $this->includeOptions($data['selected'])),
                Action::make('include')->label('Include option')->icon(Heroicon::OutlinedPlus)->authorize('manage-catalog')->schema(fn (): array => $this->optionFields())
                    ->action(fn (array $data) => $this->saveOption(null, $data)),
            ])->recordActions([
                ...$this->workspaceOrderActions(fn (int $id, int $direction) => $this->moveOption($id, $direction)),
                Action::make('edit')->label('Edit')->authorize('manage-catalog')->extraAttributes(['data-option-editor-switch' => true])
                    ->action(fn (ConfiguratorOption $record) => $this->dispatch('configurator-option-selected', attributeId: $this->owner()->id, optionId: $record->id)->to(AttributesRelationManager::class)),
                DependencyActions::local(Action::make('remove')->label('Remove')->color('danger')->authorize('manage-catalog')->requiresConfirmation()
                    ->schema([View::make('filament.forms.validation-summary')])
                    ->modalDescription('Choose another stored default and repair rule references before removing an option.')
                    ->action(fn (ConfiguratorOption $record) => $this->removeOption($record->id))),
            ]), 'configurator-options', true, ['includeMany']);
    }

    /** @param list<int|string> $ids */
    public function includeOptions(array $ids): void
    {
        $ids = Validator::make(['selected' => $ids], ['selected' => ['required', 'array', 'list', 'min:1'], 'selected.*' => ['required', 'integer', 'distinct', 'min:1']])->validate()['selected'];
        $this->changeOptions(function (array $attribute) use ($ids): array {
            $existing = array_column($attribute['options'], 'option_id');
            foreach ($ids as $id) {
                $option = Option::where('attribute_id', $attribute['attribute_id'])->find($id);
                if (! $option || in_array((int) $id, $existing, true)) {
                    throw ValidationException::withMessages(['selected' => 'Choose current, not already included Options belonging to this Attribute.']);
                }
                $attribute['options'][] = app(ConfiguratorInclusionDrafts::class)->option($option);
            }

            return $attribute;
        });
    }

    /** @return array<Component> */
    private function optionFields(?ConfiguratorOption $option = null): array
    {
        $owner = $this->owner();

        return [
            View::make('filament.forms.validation-summary'),
            Select::make('option_id')->label('Shared option')->required()->rules(['integer'])->searchable()->disabled($option !== null)->dehydrated()
                ->options(fn (): array => Option::where('attribute_id', $owner->attribute_id)
                    ->when($option === null, fn ($query) => $query->where('is_active', true)->where('is_hidden', false))
                    ->whereNotIn('id', $owner->options()->when($option, fn ($query) => $query->whereKeyNot($option->id))->pluck('option_id'))
                    ->with('value')->orderBy('code')->get()->mapWithKeys(fn (Option $row): array => [$row->id => $row->code.' · '.$row->value->label])->all()),
            TextInput::make('label_override')->label('Local option label')->maxLength(255),
            TextInput::make('display_value_override')->label('Local display value')->maxLength(255),
            TextInput::make('hint')->label('Local hint')->maxLength(1000),
            Toggle::make('hidden_by_default')->label('Initially hidden')->default(false),
            Toggle::make('disabled_by_default')->label('Initially disabled')->default(false),
        ];
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
                throw ValidationException::withMessages(['option' => 'This option does not belong to this attribute.']);
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
                throw ValidationException::withMessages(['order' => 'Choose move up or move down.']);
            }
            usort($attribute['options'], fn (array $a, array $b): int => $a['display_order'] <=> $b['display_order']);
            $index = array_search((string) $id, array_column($attribute['options'], 'id'), true);
            if ($index === false) {
                throw ValidationException::withMessages(['order' => 'This Option does not belong to this Attribute.']);
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
        Notification::make()->title('Options saved')->success()->send();
    }
}
