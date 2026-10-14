<?php

namespace App\Actions;

use App\Models\Configurator;
use App\Models\ConfiguratorOption;
use App\Models\Option;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaveCanonicalOption
{
    public function handle(User $actor, ?Option $option, int $attributeId, int $valueId, string $code): Option
    {
        Gate::forUser($actor)->authorize('manage-catalog');

        try {
            return DB::transaction(function () use ($option, $attributeId, $valueId, $code): Option {
                if ($option !== null) {
                    Configurator::whereHas('attributes.options', fn ($query) => $query->where('option_id', $option->id))->orderBy('id')->lockForUpdate()->get();
                }
                $record = $option === null ? new Option : Option::whereKey($option->id)->lockForUpdate()->firstOrFail();
                $data = Validator::make(['attribute_id' => $attributeId, 'value_id' => $valueId, 'code' => $code], [
                    'attribute_id' => ['required', 'integer', 'exists:attributes,id'],
                    'value_id' => ['required', 'integer', 'exists:values,id', Rule::unique('options')->where('attribute_id', $attributeId)->ignore($record->id)],
                    'code' => ['required', 'string', 'regex:/\A[A-Za-z0-9]{2}\z/D', Rule::unique('options')->ignore($record->id)],
                ])->validate();
                if ($record->exists && ($record->attribute_id !== $attributeId || $record->value_id !== $valueId) && ConfiguratorOption::where('option_id', $record->id)->exists()) {
                    throw ValidationException::withMessages(['option' => __('catalog.attribute_values.validation.this_attribute_value_is_included_in_configurators')]);
                }
                $record->fill($data)->save();

                return $record;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['code' => __('catalog.attribute_values.validation.this_code_or_attributevalue_pair_was_saved')]);
        }
    }
}
