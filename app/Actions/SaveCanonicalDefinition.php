<?php

namespace App\Actions;

use App\Models\Attribute;
use App\Models\User;
use App\Models\Value;
use App\Services\CanonicalUsage;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaveCanonicalDefinition
{
    public function __construct(private CanonicalUsage $usage) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, Attribute|Value $record, array $data): Attribute|Value
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        try {
            return DB::transaction(function () use ($record, $data): Attribute|Value {
                if ($record->exists) {
                    $this->usage->configurators($record)->orderBy('id')->lockForUpdate()->get();
                    $record = $record->newQuery()->whereKey($record->id)->lockForUpdate()->firstOrFail();
                }
                $rules = ['definition' => ['required', $record instanceof Attribute ? 'array:key,label' : 'array:label,description,tags'], 'definition.label' => ['required', 'string', 'max:255']];
                if ($record instanceof Attribute) {
                    $rules['definition.key'] = ['required', 'string', 'max:100', Rule::unique('attributes', 'key')->ignore($record->id)];
                } else {
                    $rules['definition.description'] = ['nullable', 'string', 'max:5000'];
                    $rules['definition.tags'] = ['sometimes', 'array', 'list', 'max:30'];
                    $rules['definition.tags.*'] = ['required', 'string', 'max:80', 'distinct'];
                }
                $validated = Validator::make(['definition' => $data], $rules)->validate()['definition'];
                if ($record instanceof Attribute && $record->exists && $record->key !== $validated['key'] && ($record->options()->exists() || $record->inclusions()->exists())) {
                    throw ValidationException::withMessages(['key' => 'This Attribute key is in use. Repair its Options and Configurator inclusions before changing its identity.']);
                }
                $record->fill($validated)->save();

                return $record;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['key' => 'This Attribute key was saved elsewhere. Choose a unique key.']);
        }
    }
}
