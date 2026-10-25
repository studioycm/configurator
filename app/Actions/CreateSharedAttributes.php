<?php

namespace App\Actions;

use App\Models\Attribute;
use App\Models\User;
use App\Services\CanonicalUsage;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateSharedAttributes
{
    public function __construct(private CreateMasterValues $createMasterValues, private SaveCanonicalDefinition $saveCanonicalDefinition, private AddAttributeValues $addAttributeValues) {}

    /**
     * @param  list<array{ref:string,label?:?string,key?:?string,values?:list<array{ref:string,value_id?:int,value_ref?:string,code:string}>}>  $attributes
     * @param  list<array{ref:string,label?:?string,semantic_id?:?string,tags?:list<string>,description?:?string}>  $masterValues
     * @return array<string, Attribute>
     */
    public function handle(User $actor, array $attributes, array $masterValues = []): array
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        Validator::make(['attributes' => $attributes], [
            'attributes' => ['present', 'array', 'list', 'max:500'],
            'attributes.*' => ['required', 'array:ref,label,key,values'],
            'attributes.*.ref' => ['required', 'string', 'distinct:strict', self::refRule()],
            'attributes.*.label' => ['sometimes', 'nullable', 'string'],
            'attributes.*.key' => ['sometimes', 'nullable', 'string'],
            'attributes.*.values' => ['sometimes', 'array', 'list', 'max:500'],
            'attributes.*.values.*' => ['required', 'array:ref,value_id,value_ref,code'],
            'attributes.*.values.*.ref' => ['required', 'string', 'distinct:strict', self::refRule()],
            'attributes.*.values.*.value_id' => ['sometimes', 'required', 'integer', 'min:1', function (string $field, mixed $value, Closure $fail): void {
                if (! is_int($value) && ! is_string($value)) {
                    $fail(__('validation.integer', ['attribute' => $field]));
                }
            }],
            'attributes.*.values.*.value_ref' => ['sometimes', 'required', 'string', self::refRule()],
            'attributes.*.values.*.code' => ['required', 'string'],
        ])->validate();
        $references = [];
        foreach ($attributes as $index => $attribute) {
            foreach ($attribute['values'] ?? [] as $valueIndex => $value) {
                if (array_key_exists('value_id', $value) === array_key_exists('value_ref', $value)) {
                    throw ValidationException::withMessages(['attributes.'.$index.'.values.'.$valueIndex => __('attribute-options.choose_one_source')]);
                }
                if (isset($value['value_ref'])) {
                    $references[] = $value['value_ref'];
                }
            }
        }
        if (! array_any($attributes, self::isPopulated(...))) {
            throw ValidationException::withMessages(['attributes' => __('catalog.creation.required')]);
        }

        return DB::transaction(function () use ($actor, $attributes, $masterValues, $references): array {
            app(CanonicalUsage::class)->lockParents([], array_values(array_filter(data_get($attributes, '*.values.*.value_id', []), fn (mixed $id): bool => is_int($id) || is_string($id))));
            $masters = $this->createMasterValues->handle($actor, $masterValues, array_values(array_unique($references)));
            $created = [];
            foreach ($attributes as $index => $row) {
                if (! self::isPopulated($row)) {
                    continue;
                }
                try {
                    $attribute = $this->saveCanonicalDefinition->handle($actor, new Attribute, ['label' => $row['label'] ?? null, 'key' => $row['key'] ?? null]);
                } catch (ValidationException $exception) {
                    $errors = [];
                    foreach ($exception->errors() as $field => $messages) {
                        $field = preg_replace('/^definition\.?/', '', $field);
                        $errors['attributes.'.$index.($field === '' ? '' : '.'.$field)] = $messages;
                    }
                    throw ValidationException::withMessages($errors);
                }
                $resolved = array_map(fn (array $value): array => ['value_id' => isset($value['value_ref']) ? $masters[$value['value_ref']]->id : (int) $value['value_id'], 'code' => $value['code']], $row['values'] ?? []);
                try {
                    $this->addAttributeValues->handle($actor, $attribute, $resolved);
                } catch (ValidationException $exception) {
                    $errors = [];
                    foreach ($exception->errors() as $field => $messages) {
                        $parts = explode('.', $field);
                        if (($parts[0] ?? '') === 'pending_options') {
                            $parts[0] = 'values';
                            if (($parts[2] ?? '') === 'value_id' && isset($row['values'][(int) $parts[1]]['value_ref'])) {
                                $parts[2] = 'value_ref';
                            }
                        }
                        $errors['attributes.'.$index.'.'.implode('.', $parts)] = $messages;
                    }
                    throw ValidationException::withMessages($errors);
                }
                $created[$row['ref']] = $attribute;
            }

            return $created;
        });
    }

    /** @param array<string,mixed> $row */
    public static function isPopulated(array $row): bool
    {
        return (is_string($row['label'] ?? null) && trim($row['label']) !== '')
            || (is_string($row['key'] ?? null) && trim($row['key']) !== '')
            || ! empty($row['values']);
    }

    private static function refRule(): Closure
    {
        return function (string $field, mixed $value, Closure $fail): void {
            if (! is_string($value) || ! str_starts_with($value, 'new:') || ! Str::isUuid(substr($value, 4))) {
                $fail(__('catalog.creation.invalid_ref'));
            }
        };
    }
}
