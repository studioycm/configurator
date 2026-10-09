<?php

namespace App\Filament\Resources\Configurators\Pages;

use App\Filament\Resources\Configurators\ConfiguratorResource;
use App\Filament\Resources\SplitCreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateConfigurator extends SplitCreateRecord
{
    protected static string $resource = ConfiguratorResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(\App\Actions\CreateConfigurator::class)->handle(auth()->user(), $data);
        } catch (ValidationException $exception) {
            $errors = [];
            foreach ($exception->errors() as $key => $messages) {
                $errors['data.'.preg_replace('/^definition\./', '', $key)] = $messages;
            }
            throw ValidationException::withMessages($errors);
        }
    }
}
