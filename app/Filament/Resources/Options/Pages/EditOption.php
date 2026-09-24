<?php

namespace App\Filament\Resources\Options\Pages;

use App\Actions\DeleteCanonicalDefinition;
use App\Actions\SaveCanonicalOption;
use App\Filament\Resources\Configurators\Schemas\ConfiguratorFormErrors;
use App\Filament\Resources\Options\OptionResource;
use App\Services\CanonicalUsage;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditOption extends EditRecord
{
    protected static string $resource = OptionResource::class;

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
        return [
            Action::make('usage')->label('View usage')->authorize('manage-catalog')
                ->modalHeading('Shared definition usage')->modalSubmitAction(false)->modalCancelActionLabel('Close')
                ->modalContent(fn () => view('filament.resources.canonical-usage', ['usage' => app(CanonicalUsage::class)->report($this->getRecord())])),
            Action::make('remove')->label('Delete shared option')->color('danger')->authorize('manage-catalog')->requiresConfirmation()
                ->modalDescription('Referenced definitions cannot be removed. Use View usage to review and repair dependencies first.')
                ->action(function (): void {
                    ConfiguratorFormErrors::run(fn () => app(DeleteCanonicalDefinition::class)->handle(auth()->user(), $this->getRecord()), $this->getMountedActionSchema());
                    $this->redirect(OptionResource::getUrl());
                }),
        ];
    }
}
