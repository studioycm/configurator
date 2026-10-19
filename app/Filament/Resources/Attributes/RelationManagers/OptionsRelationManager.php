<?php

namespace App\Filament\Resources\Attributes\RelationManagers;

use App\Actions\DeleteCanonicalDefinition;
use App\Actions\SaveCanonicalOption;
use App\Filament\Resources\Configurators\Schemas\ConfiguratorFormErrors;
use App\Filament\Resources\DependencyActions;
use App\Filament\Resources\InteractsWithScopedTableSearch;
use App\Filament\Resources\TablePresentation;
use App\Models\Attribute;
use App\Models\Option;
use App\Models\Value;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\View;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class OptionsRelationManager extends RelationManager
{
    use InteractsWithScopedTableSearch;

    protected static string $relationship = 'options';

    protected static bool $isLazy = false;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('catalog.attribute_values.short_plural');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Attribute && Gate::allows('manage-catalog');
    }

    public function table(Table $table): Table
    {
        return TablePresentation::configure($table->heading(__('catalog.attribute_values.headings.for_attribute', ['attribute' => $this->getOwnerRecord()->label]))->recordTitle(fn (Option $record): string => $record->displayName())->modifyQueryUsing(fn ($query) => $query->with(['value', 'attribute']))
            ->columns([
                TextColumn::make('value.label')->label(__('catalog.fields.value_ui_name'))->searchable(),
                TextColumn::make('value.semantic_id')->label(__('catalog.fields.semantic_id'))->searchable()->sortable()->toggleable(),
                TextColumn::make('code')->label(__('catalog.fields.code'))->searchable()->toggleable(),
            ])->headerActions([
                $this->optionAction('create')->label(__('catalog.attributes.labels.add_value')),
            ])->recordActions([
                $this->optionAction('edit')->label(__('ui.actions.edit'))->fillForm(fn (Option $record): array => $record->only('value_id', 'code')),
                DependencyActions::canonical(Action::make('remove'))->label(__('ui.actions.delete'))->color('danger')->authorize('manage-catalog')->requiresConfirmation()
                    ->schema([View::make('filament.forms.validation-summary')])
                    ->modalDescription(__('catalog.attributes.help.used_attribute_values_cannot_be_deleted_repair'))
                    ->action(function (Option $record): void {
                        abort_unless($record->attribute_id === $this->getOwnerRecord()->id, 404);
                        ConfiguratorFormErrors::run(fn () => app(DeleteCanonicalDefinition::class)->handle(auth()->user(), $record), $this->getMountedActionSchema());
                        $this->dispatch('catalog-record-saved');
                    }),
            ]), 'attribute-options', true, ['create']);
    }

    private function optionAction(string $name): Action
    {
        return Action::make($name)->authorize('manage-catalog')->modalHeading(fn (?Option $record): string => $record?->displayName() ?? __('catalog.attributes.labels.add_value'))->schema([
            View::make('filament.forms.validation-summary'),
            Select::make('value_id')->label(__('catalog.fields.master_value'))->required()->searchable()
                ->getSearchResultsUsing(fn (string $search): array => Value::query()->where(fn (Builder $query) => $query->where('label', 'like', '%'.$search.'%')->orWhere('semantic_id', 'like', '%'.$search.'%'))->orderBy('label')->limit(50)->get()->mapWithKeys(fn (Value $value): array => [$value->id => $value->labelWithSemanticId()])->all())
                ->getOptionLabelUsing(fn (mixed $value): ?string => is_scalar($value) ? Value::find($value)?->labelWithSemanticId() : null),
            TextInput::make('code')->label(__('catalog.fields.code'))->required()->length(2)->trim(false)->regex('/\A[A-Za-z0-9]{2}\z/D'),
        ])->action(function (array $data, ?Option $record): void {
            abort_if($record && $record->attribute_id !== $this->getOwnerRecord()->id, 404);
            ConfiguratorFormErrors::run(
                fn () => app(SaveCanonicalOption::class)->handle(auth()->user(), $record, $this->getOwnerRecord()->id, (int) $data['value_id'], $data['code']),
                $this->getMountedActionSchema(),
            );
            Notification::make()->title(__('catalog.attributes.headings.attribute_value_saved'))->success()->send();
            $this->dispatch('catalog-record-saved');
        });
    }
}
