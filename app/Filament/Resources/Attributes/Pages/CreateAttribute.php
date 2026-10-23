<?php

namespace App\Filament\Resources\Attributes\Pages;

use App\Actions\AddAttributeValues;
use App\Filament\Resources\Attributes\AttributeResource;
use App\Filament\Resources\Attributes\Schemas\AttributeForm;
use App\Filament\Resources\Configurators\Schemas\ConfiguratorFormErrors;
use App\Filament\Resources\SplitCreateRecord;
use App\Models\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreateAttribute extends SplitCreateRecord
{
    protected static string $resource = AttributeResource::class;

    protected function beforeValidate(): void
    {
        Gate::authorize('manage-catalog');
        AttributeForm::assertValuesStaged($this->form);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $record = new Attribute;
        try {
            $pool = array_values($data['master_values'] ?? []);
            unset($data['master_values']);
            $data['pending_options'] = array_map(fn (array $row): array => array_intersect_key($row, array_flip(['ref', 'value_id', 'value_ref', 'code'])), $data['pending_options'] ?? []);

            return app(AddAttributeValues::class)->saveDefinition(auth()->user(), $record, $data, $pool);
        } catch (ValidationException $exception) {
            ConfiguratorFormErrors::rethrow($exception, $this->form, '/^definition\./');
        }
    }
}
