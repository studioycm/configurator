<?php

namespace App\Filament\Resources\Groups\Pages;

use App\Actions\SaveCatalogGroup;
use App\Actions\SaveGroupAttributes;
use App\Filament\Resources\Groups\GroupResource;
use App\Filament\Resources\SplitCreateRecord;
use App\Services\CatalogRevisions;
use App\Services\GroupAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateGroup extends SplitCreateRecord
{
    protected static string $resource = GroupResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return DB::transaction(fn (): Model => app(CatalogRevisions::class)->batch(function () use ($data): Model {
                $hasAttributes = array_key_exists('catalog_attributes', $data);
                $attributeChanges = $hasAttributes ? $data['catalog_attributes'] : [];
                unset($data['catalog_attributes']);
                if ($hasAttributes && ! is_array($attributeChanges)) {
                    throw ValidationException::withMessages(['catalog_attributes' => __('catalog.group_attributes.invalid_values')]);
                }
                $group = app(SaveCatalogGroup::class)->handle(auth()->user(), null, $data);
                if ($hasAttributes && $attributeChanges !== []) {
                    $group = app(SaveGroupAttributes::class)->handle(auth()->user(), $group, $attributeChanges, app(GroupAttributes::class)->fingerprint($group));
                }

                return $group;
            }), attempts: 3);
        } catch (ValidationException $exception) {
            $errors = [];
            foreach ($exception->errors() as $key => $messages) {
                $errors['data.'.preg_replace('/^group\./', '', $key)] = $messages;
            }
            throw ValidationException::withMessages($errors);
        }
    }
}
