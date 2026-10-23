<?php

namespace App\Filament\Resources\Values\Actions;

use App\Actions\CreateMasterValues;
use App\Filament\Resources\Configurators\Schemas\ConfiguratorFormErrors;
use App\Filament\Resources\Values\Schemas\ValueForm;
use App\Models\Value;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class CreateValuesAction
{
    public static function make(): Action
    {
        return Action::make('createMany')->label(__('catalog.creation.create_values'))->authorize('manage-catalog')->slideOver()->stickyModalFooter()->databaseTransaction(false)
            ->modalSubmitActionLabel(__('catalog.creation.create'))->modalCancelAction(false)->modalCloseButton(false)->closeModalByClickingAway(false)->closeModalByEscaping(false)
            ->beforeFormValidated(fn () => Gate::authorize('manage-catalog'))
            ->schema(function (Schema $schema): Schema {
                Gate::authorize('manage-catalog');

                return $schema->columns(1)->components([ValueForm::batch('master_values', tagOptions: array_values(Value::tagOptions()))]);
            })
            ->extraModalFooterActions(fn (Action $action): array => [Action::make('discardDraft')->label(__('catalog.creation.cancel'))->color('gray')
                ->requiresConfirmation(fn (): bool => array_any($action->getLivewire()->getSchema('mountedActionSchema'.$action->getNestingIndex())->getRawState()['master_values'] ?? [], CreateMasterValues::isPopulated(...)))
                ->modalHidden(fn (): bool => ! array_any($action->getLivewire()->getSchema('mountedActionSchema'.$action->getNestingIndex())->getRawState()['master_values'] ?? [], CreateMasterValues::isPopulated(...)))
                ->modalHeading(__('catalog.creation.discard_heading'))->modalSubmitActionLabel(__('catalog.creation.discard'))->modalCancelActionLabel(__('catalog.creation.keep_editing'))
                ->cancelParentActions('createMany')->action(fn () => null)])
            ->action(function (array $data, Action $action, CreateMasterValues $createMasterValues, Component $livewire): void {
                $rows = array_map(fn (array $row): array => array_intersect_key($row, array_flip(['ref', 'label', 'semantic_id', 'tags', 'description'])), $data['master_values'] ?? []);
                $created = ConfiguratorFormErrors::run(fn (): array => $createMasterValues->handle(auth()->user(), $rows), $action->getLivewire()->getSchema('mountedActionSchema'.$action->getNestingIndex()));
                $livewire->dispatch('catalog-record-saved');
                Notification::make()->success()->title(__('catalog.creation.created', ['count' => count($created)]))->send();
            });
    }
}
