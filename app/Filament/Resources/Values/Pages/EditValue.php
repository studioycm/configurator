<?php

namespace App\Filament\Resources\Values\Pages;

use App\Actions\DeleteCanonicalDefinition;
use App\Actions\SaveCanonicalDefinition;
use App\Filament\Resources\Configurators\Schemas\ConfiguratorFormErrors;
use App\Filament\Resources\DependencyActions;
use App\Filament\Resources\InteractsWithBatchEditor;
use App\Filament\Resources\Values\ValueResource;
use App\Services\CanonicalUsage;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditValue extends EditRecord
{
    use InteractsWithBatchEditor;

    protected string $view = 'filament.resources.record-editor';

    protected static string $resource = ValueResource::class;

    protected function afterSave(): void
    {
        $this->rememberBatchEditor();
        $this->dispatch('catalog-record-saved');
        $this->dispatch('catalog-editor-saved');
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {

        try {
            return app(SaveCanonicalDefinition::class)->handle(auth()->user(), $record, $data);
        } catch (ValidationException $exception) {
            $errors = [];
            foreach ($exception->errors() as $key => $messages) {
                $errors['data.'.preg_replace('/^definition\./', '', $key)] = $messages;
            }
            throw ValidationException::withMessages($errors);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('usage')->label(__('catalog.usage.actions.view'))->authorize('manage-catalog')
                ->modalHeading(__('catalog.master_values.headings.shared_definition_usage'))->modalSubmitAction(false)->modalCancelActionLabel(__('ui.actions.close'))
                ->modalContent(fn () => view('filament.resources.canonical-usage', ['usage' => app(CanonicalUsage::class)->report($this->getRecord())])),
            DependencyActions::canonical(Action::make('remove'), $this->getRecord())->label(__('catalog.master_values.labels.delete_master_value'))->color('danger')->authorize('manage-catalog')->requiresConfirmation()
                ->modalDescription(__('catalog.master_values.help.referenced_definitions_cannot_be_removed_use_view'))
                ->action(function (): void {
                    ConfiguratorFormErrors::run(fn () => app(DeleteCanonicalDefinition::class)->handle(auth()->user(), $this->getRecord()), $this->getMountedActionSchema());
                    $this->redirect(ValueResource::getUrl());
                }),
        ];
    }
}
