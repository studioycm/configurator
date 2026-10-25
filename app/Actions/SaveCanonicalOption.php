<?php

namespace App\Actions;

use App\Models\Configurator;
use App\Models\ConfiguratorOption;
use App\Models\Option;
use App\Models\User;
use App\Services\CanonicalUsage;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaveCanonicalOption
{
    public function __construct(private CanonicalUsage $usage) {}

    public function handle(User $actor, ?Option $option, int $attributeId, int $valueId, string $code, bool $preserveIdentity = false): Option
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        $ownerIds = $option === null ? [] : $this->usage->configuratorIds(optionIds: [$option->id]);

        try {
            return DB::transaction(function () use ($option, $attributeId, $valueId, $code, $preserveIdentity, $ownerIds): Option {
                if ($option !== null) {
                    Configurator::whereKey($ownerIds)->orderBy('id')->lockForUpdate()->get();
                }
                $parents = $this->usage->lockParents([$attributeId], [$valueId], $option === null ? [] : [$option->id]);
                if ($option !== null) {
                    $this->usage->assertConfiguratorIds($ownerIds, optionIds: [$option->id]);
                }
                $record = $option === null ? new Option : $parents['options']->findOrFail($option->id);
                if ($preserveIdentity && $record->exists && ($record->attribute_id !== $attributeId || $record->value_id !== $valueId || $record->code !== $code)) {
                    throw ValidationException::withMessages(['code' => __('data_exchange.validation.existing_code')]);
                }
                $data = Validator::make(['attribute_id' => $attributeId, 'value_id' => $valueId, 'code' => $code], [
                    'attribute_id' => ['required', 'integer', 'exists:attributes,id'],
                    'value_id' => ['required', 'integer', 'exists:values,id', Rule::unique('options')->where('attribute_id', $attributeId)->ignore($record->id)],
                    'code' => ['required', 'string', 'regex:/\A[A-Za-z0-9]{2}\z/D', Rule::unique('options')->ignore($record->id)],
                ])->validate();
                if ($record->exists && ($record->attribute_id !== $attributeId || $record->value_id !== $valueId) && ConfiguratorOption::where('option_id', $record->id)->orderBy('id')->lockForUpdate()->limit(1)->get(['id'])->isNotEmpty()) {
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
