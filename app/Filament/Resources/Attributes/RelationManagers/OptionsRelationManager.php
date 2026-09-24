<?php

namespace App\Filament\Resources\Attributes\RelationManagers;

use App\Actions\DeleteCanonicalDefinition;
use App\Actions\SaveCanonicalOption;
use App\Filament\Resources\Configurators\Schemas\ConfiguratorFormErrors;
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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class OptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'options';

    protected static bool $isLazy = false;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Attribute && Gate::allows('manage-catalog');
    }

    public function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn ($query) => $query->with('value'))
            ->columns([
                TextColumn::make('value.label')->label('Master value')->searchable(),
                TextColumn::make('code')->label('Code'),
            ])->headerActions([
                $this->optionAction('create')->label('Add option'),
            ])->recordActions([
                $this->optionAction('edit')->label('Edit')->fillForm(fn (Option $record): array => $record->only('value_id', 'code')),
                Action::make('remove')->label('Delete')->color('danger')->authorize('manage-catalog')->requiresConfirmation()
                    ->schema([View::make('filament.forms.validation-summary')])
                    ->modalDescription('Used options cannot be deleted. Repair their dependencies first.')
                    ->action(function (Option $record): void {
                        abort_unless($record->attribute_id === $this->getOwnerRecord()->id, 404);
                        ConfiguratorFormErrors::run(fn () => app(DeleteCanonicalDefinition::class)->handle(auth()->user(), $record), $this->getMountedActionSchema());
                        $this->dispatch('catalog-record-saved');
                    }),
            ]);
    }

    private function optionAction(string $name): Action
    {
        return Action::make($name)->authorize('manage-catalog')->schema([
            View::make('filament.forms.validation-summary'),
            Select::make('value_id')->label('Master value')->required()->searchable()
                ->getSearchResultsUsing(fn (string $search): array => Value::where('label', 'like', '%'.$search.'%')->orderBy('label')->limit(50)->get()->mapWithKeys(fn (Value $value): array => [$value->id => $value->label.' · #'.$value->id])->all())
                ->getOptionLabelUsing(fn (mixed $value): ?string => is_scalar($value) ? Value::find($value)?->label : null),
            TextInput::make('code')->required()->length(2)->trim(false)->regex('/\A[A-Za-z0-9]{2}\z/D'),
        ])->action(function (array $data, ?Option $record): void {
            abort_if($record && $record->attribute_id !== $this->getOwnerRecord()->id, 404);
            ConfiguratorFormErrors::run(
                fn () => app(SaveCanonicalOption::class)->handle(auth()->user(), $record, $this->getOwnerRecord()->id, (int) $data['value_id'], $data['code']),
                $this->getMountedActionSchema(),
            );
            Notification::make()->title('Option saved')->success()->send();
            $this->dispatch('catalog-record-saved');
        });
    }
}
