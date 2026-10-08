<?php

namespace App\Filament\Resources\Values\Pages;

use App\Actions\SaveCanonicalDefinition;
use App\Filament\Resources\SplitCreateRecord;
use App\Filament\Resources\Values\ValueResource;
use App\Models\Value;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateValue extends SplitCreateRecord
{
    protected static string $resource = ValueResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $record = new Value;
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
