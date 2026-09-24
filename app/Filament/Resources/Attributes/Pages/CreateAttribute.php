<?php

namespace App\Filament\Resources\Attributes\Pages;

use App\Actions\SaveCanonicalDefinition;
use App\Filament\Resources\Attributes\AttributeResource;
use App\Models\Attribute;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateAttribute extends CreateRecord
{
    protected static string $resource = AttributeResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $record = new Attribute;
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
}
