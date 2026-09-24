<?php

namespace App\Filament\Resources\Options\Pages;

use App\Actions\SaveCanonicalOption;
use App\Filament\Resources\Options\OptionResource;
use App\Models\Option;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateOption extends CreateRecord
{
    protected static string $resource = OptionResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $record = new Option;
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
}
