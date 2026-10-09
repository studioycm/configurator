<?php

namespace App\Filament\Resources\Options\Pages;

use App\Actions\DeleteCanonicalDefinition;
use App\Actions\SaveCanonicalOption;
use App\Filament\Resources\Configurators\Schemas\ConfiguratorFormErrors;
use App\Filament\Resources\DependencyActions;
use App\Filament\Resources\InteractsWithBatchEditor;
use App\Filament\Resources\InteractsWithItemDrawerEditor;
use App\Filament\Resources\Options\OptionResource;
use App\Services\CanonicalUsage;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditOption extends EditRecord
{
    use InteractsWithBatchEditor {
        beforeSave as protected beforeSaveBatchEditor;
    }
    use InteractsWithItemDrawerEditor;

    protected string $view = 'filament.resources.record-editor';

    protected static string $resource = OptionResource::class;

    protected function afterSave(): void
    {
        $this->rememberBatchEditor();
        $this->dispatch('catalog-record-saved');
        $this->dispatch('catalog-editor-saved')->self();
        $this->notifyItemDrawerSaved();
    }

    protected function beforeSave(): void
    {
        $this->assertItemDrawerMembership($this->getRecord()->getKey());
        $this->beforeSaveBatchEditor();
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {

        try {
            return app(SaveCanonicalOption::class)->handle(auth()->user(), $record->exists ? $record : null, (int) $data['attribute_id'], (int) $data['value_id'], $data['code']);
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
        $actions = [
            Action::make('usage')->label('View usage')->authorize('manage-catalog')
                ->modalHeading('Shared definition usage')->modalSubmitAction(false)->modalCancelActionLabel('Close')
                ->modalContent(fn () => view('filament.resources.canonical-usage', ['usage' => app(CanonicalUsage::class)->report($this->getRecord())])),
        ];
        if ($this->isItemDrawerEditor()) {
            return $actions;
        }

        return [
            ...$actions,
            DependencyActions::canonical(Action::make('remove'), $this->getRecord())->label('Delete shared option')->color('danger')->authorize('manage-catalog')->requiresConfirmation()
                ->modalDescription('Referenced definitions cannot be removed. Use View usage to review and repair dependencies first.')
                ->action(function (): void {
                    ConfiguratorFormErrors::run(fn () => app(DeleteCanonicalDefinition::class)->handle(auth()->user(), $this->getRecord()), $this->getMountedActionSchema());
                    $this->redirect(OptionResource::getUrl());
                }),
        ];
    }
}
