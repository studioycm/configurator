<?php

namespace App\Filament\Resources\Configurators\RelationManagers;

use App\Actions\SaveConfiguratorDefinition;
use App\Filament\Resources\Configurators\Schemas\ConfiguratorFormErrors;
use App\Models\ConfiguratorAttribute;
use App\Models\ConfiguratorOption;
use App\Models\Option;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\View;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;

class OptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'options';

    protected static ?string $title = 'Included options';

    protected static bool $isLazy = false;

    #[On('configurator-attribute-updated')]
    public function refreshAttribute(int $attributeId): void
    {
        if ($this->owner()->id === $attributeId) {
            $this->resetTable();
        }
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof ConfiguratorAttribute && Gate::allows('manage-catalog');
    }

    public function table(Table $table): Table
    {
        $default = $this->owner()->default_configurator_option_id;

        return $table->modifyQueryUsing(fn ($query) => $query->with('option.value'))
            ->description('Options for this attribute in this configurator. Shared definitions stay unchanged.')
            ->columns([
                TextColumn::make('option.code')->label('Code'),
                TextColumn::make('option.value.label')->label('Option')->wrap()->formatStateUsing(fn (ConfiguratorOption $record): string => $record->label_override ?? $record->option->value->label),
                IconColumn::make('stored_default')->label('Default')->boolean()->state(fn (ConfiguratorOption $record): bool => $record->id === $default),
                IconColumn::make('hidden_by_default')->label('Hidden')->boolean()->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('disabled_by_default')->label('Disabled')->boolean()->toggleable(isToggledHiddenByDefault: true),
            ])->defaultSort('display_order')->reorderable('display_order')->paginated(false)->recordAction('edit')
            ->headerActions([
                Action::make('include')->label('Include option')->authorize('manage-catalog')->schema(fn (): array => $this->optionFields())
                    ->action(fn (array $data) => $this->saveOption(null, $data)),
            ])->recordActions([
                Action::make('edit')->label('Edit')->authorize('manage-catalog')->schema(fn (ConfiguratorOption $record): array => $this->optionFields($record))
                    ->fillForm(fn (ConfiguratorOption $record): array => $record->only(['option_id', 'label_override', 'display_value_override', 'hint', 'hidden_by_default', 'disabled_by_default']))
                    ->action(fn (ConfiguratorOption $record, array $data) => $this->saveOption($record->id, $data)),
                Action::make('remove')->label('Remove')->color('danger')->authorize('manage-catalog')->requiresConfirmation()
                    ->schema([View::make('filament.forms.validation-summary')])
                    ->modalDescription('Choose another stored default and repair rule references before removing an option.')
                    ->action(fn (ConfiguratorOption $record) => $this->removeOption($record->id)),
            ]);
    }

    /** @return array<Component> */
    private function optionFields(?ConfiguratorOption $option = null): array
    {
        $owner = $this->owner();

        return [
            View::make('filament.forms.validation-summary'),
            Select::make('option_id')->label('Shared option')->required()->rules(['integer'])->searchable()->disabled($option !== null)->dehydrated()
                ->options(fn (): array => Option::where('attribute_id', $owner->attribute_id)
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
        $this->changeOptions(function (array $attribute) use ($id, $data): array {
            if (array_diff(array_keys($data), ['option_id', 'label_override', 'display_value_override', 'hint', 'hidden_by_default', 'disabled_by_default']) !== []) {
                throw ValidationException::withMessages(['option' => 'Unsupported option fields.']);
            }
            $index = array_search((string) $id, array_column($attribute['options'], 'id'), true);
            if ($id !== null && $index === false) {
                throw ValidationException::withMessages(['option' => 'This option does not belong to this attribute.']);
            }
            $row = $id === null ? ['id' => 'new:'.Str::uuid(), 'label_override' => null, 'display_value_override' => null, 'hint' => null, 'hidden_by_default' => false, 'disabled_by_default' => false] : $attribute['options'][$index];
            $row = [...$row, ...$data];
            if ($id === null) {
                $attribute['options'][] = $row;
            } else {
                $attribute['options'][$index] = $row;
            }

            return $attribute;
        });
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

        return ConfiguratorAttribute::findOrFail($this->getOwnerRecord()->getKey());
    }

    private function saved(): void
    {
        $this->resetTable();
        $this->dispatch('configurator-options-updated');
        $this->dispatch('configurator-updated');
        Notification::make()->title('Options saved')->success()->send();
    }
}
