<?php

namespace App\Filament\Resources\Groups\Pages;

use App\Actions\SaveCatalogGroup;
use App\Filament\Resources\Groups\GroupResource;
use App\Filament\Resources\SplitCreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateGroup extends SplitCreateRecord
{
    protected static string $resource = GroupResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(SaveCatalogGroup::class)->handle(auth()->user(), null, $data);
        } catch (ValidationException $exception) {
            $errors = [];
            foreach ($exception->errors() as $key => $messages) {
                $errors['data.'.preg_replace('/^group\./', '', $key)] = $messages;
            }
            throw ValidationException::withMessages($errors);
        }
    }
}
