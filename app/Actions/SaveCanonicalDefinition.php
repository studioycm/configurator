<?php

namespace App\Actions;

use App\Models\Attribute;
use App\Models\Configurator;
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
        $attributeIds = $record->exists && $record instanceof Attribute ? [$record->id] : [];
        $valueIds = $record->exists && $record instanceof Value ? [$record->id] : [];
        $ownerIds = $this->usage->configuratorIds($attributeIds, $valueIds);
        try {
            return DB::transaction(function () use ($record, $data, $attributeIds, $valueIds, $ownerIds): Attribute|Value {
                if ($record->exists) {
                    Configurator::whereKey($ownerIds)->orderBy('id')->lockForUpdate()->get();
                    $parents = $this->usage->lockParents($attributeIds, $valueIds);
                    $record = $parents[$record instanceof Attribute ? 'attributes' : 'values']->findOrFail($record->id);
                }
                $rules = ['definition' => ['required', $record instanceof Attribute ? 'array:key,label' : 'array:label,semantic_id,description,tags'], 'definition.label' => ['required', 'string', 'max:255']];
                if ($record instanceof Attribute) {
                    $rules['definition.key'] = ['required', 'string', 'max:100', Rule::unique('attributes', 'key')->ignore($record->id)];
                } else {
                    if (! array_key_exists('semantic_id', $data)) {
                        $data['semantic_id'] = $record->exists ? $record->semantic_id : Value::suggestSemanticId(is_string($data['label'] ?? null) ? $data['label'] : '');
                    }
                    $rules['definition.semantic_id'] = ['required', 'string', 'max:100', 'regex:/\A[a-z0-9]+(?:_[a-z0-9]+)*\z/D', Rule::unique('values', 'semantic_id')->ignore($record->id)];
                    $rules['definition.description'] = ['nullable', 'string', 'max:5000'];
                    $rules['definition.tags'] = ['sometimes', 'array', 'list', 'max:30'];
                    $rules['definition.tags.*'] = ['required', 'string', 'max:80', 'distinct:strict'];
                }
                $validated = Validator::make(['definition' => $data], $rules)->validate()['definition'];
                $changingKey = $record instanceof Attribute && $record->exists && $record->key !== $validated['key'];
                $hasOptions = $changingKey && $record->options()->orderBy('id')->lockForUpdate()->limit(1)->get(['id'])->isNotEmpty();
                if ($record->exists) {
                    $this->usage->assertConfiguratorIds($ownerIds, $attributeIds, $valueIds);
                }
                if ($changingKey && ($hasOptions || $record->inclusions()->orderBy('id')->lockForUpdate()->limit(1)->get(['id'])->isNotEmpty())) {
                    throw ValidationException::withMessages(['key' => __('catalog.validation.this_attribute_key_is_in_use_repair')]);
                }
                $record->fill($validated)->save();

                return $record;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages($record instanceof Value
                ? ['semantic_id' => __('catalog.validation.this_value_semantic_id_was_saved_elsewhere_choose')]
                : ['key' => __('catalog.validation.this_attribute_key_was_saved_elsewhere_choose')]);
        }
    }
}
