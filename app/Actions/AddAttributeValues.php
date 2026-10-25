<?php

namespace App\Actions;

use App\Models\Attribute;
use App\Models\Configurator;
use App\Models\User;
use App\Services\CanonicalUsage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AddAttributeValues
{
    public function __construct(private CreateMasterValues $createMasterValues) {}

    /** @param list<array{value_id: int, code: string}> $options */
    public function handle(User $actor, Attribute $attribute, array $options): void
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        DB::transaction(function () use ($actor, $attribute, $options): void {
            $parents = app(CanonicalUsage::class)->lockParents([$attribute->getKey()], array_values(array_filter(array_column($options, 'value_id'), fn (mixed $id): bool => is_int($id) || is_string($id))));
            $attribute = $parents['attributes']->findOrFail($attribute->getKey());
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

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{ref:string,label?:?string,semantic_id?:?string,tags?:list<string>,description?:?string}>  $masterValues
     */
    public function saveDefinition(User $actor, Attribute $attribute, array $data, array $masterValues = []): Attribute
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        $attributeIds = $attribute->exists ? [$attribute->id] : [];
        $ownerIds = app(CanonicalUsage::class)->configuratorIds(attributeIds: $attributeIds);
        $valueIds = array_values(array_filter(data_get($data, 'pending_options.*.value_id', []), fn (mixed $id): bool => is_int($id) || is_string($id)));

        return DB::transaction(function () use ($actor, $attribute, $data, $masterValues, $attributeIds, $valueIds, $ownerIds): Attribute {
            Configurator::whereKey($ownerIds)->orderBy('id')->lockForUpdate()->get();
            $parents = app(CanonicalUsage::class)->lockParents($attributeIds, $valueIds);
            app(CanonicalUsage::class)->assertConfiguratorIds($ownerIds, attributeIds: $attributeIds);
            if ($attribute->exists) {
                $attribute = $parents['attributes']->findOrFail($attribute->id);
            }
            $options = $data['pending_options'] ?? [];
            unset($data['pending_options']);
            Validator::make(['pending_options' => $options], [
                'pending_options' => ['array', 'list', 'max:500'],
                'pending_options.*' => ['required', 'array:ref,value_id,value_ref,code'],
                'pending_options.*.ref' => ['sometimes', 'required', 'string', 'distinct:strict', function (string $field, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || ! str_starts_with($value, 'new:') || ! Str::isUuid(substr($value, 4))) {
                        $fail(__('catalog.creation.invalid_ref'));
                    }
                }],
                'pending_options.*.value_ref' => ['sometimes', 'required', 'string'],
                'pending_options.*.value_id' => ['sometimes', 'required', 'integer', 'min:1', function (string $field, mixed $value, \Closure $fail): void {
                    if (! is_int($value) && ! is_string($value)) {
                        $fail(__('validation.integer', ['attribute' => $field]));
                    }
                }],
                'pending_options.*.code' => ['required', 'string'],
            ])->validate();
            foreach ($options as $index => $option) {
                if (array_key_exists('value_id', $option) === array_key_exists('value_ref', $option) || (isset($option['value_ref']) && ! isset($option['ref']))) {
                    throw ValidationException::withMessages(['pending_options.'.$index => __('attribute-options.choose_one_source')]);
                }
            }
            $refs = array_values(array_unique(array_column($options, 'value_ref')));
            $masters = $this->createMasterValues->handle($actor, $masterValues, $refs);
            $resolved = array_map(fn (array $option): array => ['value_id' => isset($option['value_ref']) ? $masters[$option['value_ref']]->id : (int) $option['value_id'], 'code' => $option['code']], $options);
            $saved = app(SaveCanonicalDefinition::class)->handle($actor, $attribute, $data);
            try {
                $this->handle($actor, $saved, $resolved);
            } catch (ValidationException $exception) {
                $errors = [];
                foreach ($exception->errors() as $field => $messages) {
                    $parts = explode('.', $field);
                    if (($parts[0] ?? '') === 'pending_options' && ($parts[2] ?? '') === 'value_id' && isset($options[(int) $parts[1]]['value_ref'])) {
                        $parts[2] = 'value_ref';
                    }
                    $errors[implode('.', $parts)] = $messages;
                }
                throw ValidationException::withMessages($errors);
            }

            return $saved;
        });
    }
}
