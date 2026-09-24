<?php

namespace App\Filament\Resources\Attributes\Pages;

use App\Actions\DeleteCanonicalDefinition;
use App\Actions\SaveCanonicalDefinition;
use App\Filament\Resources\Attributes\AttributeResource;
use App\Filament\Resources\Configurators\Schemas\ConfiguratorFormErrors;
use App\Services\CanonicalUsage;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditAttribute extends EditRecord
{
    protected static string $resource = AttributeResource::class;

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
            Action::make('usage')->label('View usage')->authorize('manage-catalog')
                ->modalHeading('Shared definition usage')->modalSubmitAction(false)->modalCancelActionLabel('Close')
                ->modalContent(fn () => view('filament.resources.canonical-usage', ['usage' => app(CanonicalUsage::class)->report($this->getRecord())])),
            Action::make('remove')->label('Delete shared attribute')->color('danger')->authorize('manage-catalog')->requiresConfirmation()
                ->modalDescription('Referenced definitions cannot be removed. Use View usage to review and repair dependencies first.')
                ->action(function (): void {
                    ConfiguratorFormErrors::run(fn () => app(DeleteCanonicalDefinition::class)->handle(auth()->user(), $this->getRecord()), $this->getMountedActionSchema());
                    $this->redirect(AttributeResource::getUrl());
                }),
        ];
    }
}
