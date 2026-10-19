<?php

namespace App\Actions;

use App\Models\Attribute;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AddAttributeValues
{
    /** @param list<array{value_id: int, code: string}> $options */
    public function handle(User $actor, Attribute $attribute, array $options): void
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        DB::transaction(function () use ($actor, $attribute, $options): void {
            $attribute = Attribute::whereKey($attribute->getKey())->lockForUpdate()->firstOrFail();
            Validator::make(['pending_options' => $options], [
                'pending_options' => ['array', 'list', 'max:500'],
                'pending_options.*' => ['array:value_id,code'],
                'pending_options.*.value_id' => ['required', 'integer', 'distinct:strict', 'exists:values,id', Rule::unique('options', 'value_id')->where('attribute_id', $attribute->id)],
                'pending_options.*.code' => ['required', 'string', 'distinct:strict', 'regex:/\A[A-Za-z0-9]{2}\z/D', Rule::unique('options', 'code')],
            ], attributes: ['pending_options.*.value_id' => __('catalog.resources.master_values.singular'), 'pending_options.*.code' => __('catalog.fields.code')])->validate();
            foreach ($options as $index => $option) {
                try {
                    app(SaveCanonicalOption::class)->handle($actor, null, $attribute->id, (int) $option['value_id'], $option['code']);
                } catch (ValidationException $exception) {
                    $errors = [];
                    foreach ($exception->errors() as $field => $messages) {
                        $errors['pending_options.'.$index.'.'.$field] = $messages;
                    }
                    throw ValidationException::withMessages($errors);
                }
            }
        });
    }

    /** @param array<string, mixed> $data */
    public function saveDefinition(User $actor, Attribute $attribute, array $data): Attribute
    {
        Gate::forUser($actor)->authorize('manage-catalog');

        return DB::transaction(function () use ($actor, $attribute, $data): Attribute {
            $options = $data['pending_options'] ?? [];
            unset($data['pending_options']);
            $saved = app(SaveCanonicalDefinition::class)->handle($actor, $attribute, $data);
            $this->handle($actor, $saved, $options);

            return $saved;
        });
    }
}
