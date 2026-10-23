<?php

namespace App\Actions;

use App\Models\Attribute;
use App\Models\Configurator;
use App\Models\ConfiguratorAttribute;
use App\Models\Option;
use App\Models\User;
use App\Services\ConfiguratorInclusionDrafts;
use Closure;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AddConfiguratorValues
{
    public function __construct(private SaveConfiguratorDefinition $saveConfiguratorDefinition, private CreateMasterValues $createMasterValues, private AddAttributeValues $addAttributeValues) {}

    /**
     * @param  array{existing_option_ids?:list<int|string>,new_values?:list<array{ref:string,value_id?:int|string,value_ref?:string,code:string}>,master_values?:list<array{ref:string,label?:?string,semantic_id?:?string,tags?:list<string>,description?:?string}>}  $payload
     */
    public function handle(User $actor, Configurator $owner, int $inclusionId, array $payload): Configurator
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        $inclusion = ConfiguratorAttribute::where('configurator_id', $owner->id)->findOrFail($inclusionId);
        if (array_diff(array_keys($payload), ['existing_option_ids', 'new_values', 'master_values'])) {
            throw ValidationException::withMessages(['save' => __('configurator.creation.invalid_shape')]);
        }
        $payload += ['existing_option_ids' => [], 'new_values' => [], 'master_values' => []];
        $id = function (string $field, mixed $value, Closure $fail): void {
            if ((! is_int($value) && ! is_string($value)) || ! preg_match('/\A[1-9][0-9]*\z/D', (string) $value) || filter_var($value, FILTER_VALIDATE_INT) === false) {
                $fail(__('validation.integer', ['attribute' => $field]));
            }
        };
        $ref = function (string $field, mixed $value, Closure $fail): void {
            if (! is_string($value) || ! str_starts_with($value, 'new:') || ! Str::isUuid(substr($value, 4))) {
                $fail(__('catalog.creation.invalid_ref'));
            }
        };
        Validator::make($payload, [
            'existing_option_ids' => ['array', 'list', 'max:500'],
            'existing_option_ids.*' => ['required', 'distinct', $id],
            'new_values' => ['array', 'list', 'max:500'],
            'new_values.*' => ['required', 'array:ref,value_id,value_ref,code'],
            'new_values.*.ref' => ['required', 'string', 'distinct:strict', $ref],
            'new_values.*.value_id' => ['sometimes', 'required', $id],
            'new_values.*.value_ref' => ['sometimes', 'required', 'string', $ref],
            'new_values.*.code' => ['required', 'string'],
            'master_values' => ['array', 'list', 'max:500'],
        ])->validate();
        $refs = [];
        foreach ($payload['new_values'] as $index => $row) {
            if (array_key_exists('value_id', $row) === array_key_exists('value_ref', $row)) {
                throw ValidationException::withMessages(['new_values.'.$index => __('attribute-options.choose_one_source')]);
            }
            if (isset($row['value_ref'])) {
                $refs[] = $row['value_ref'];
            }
        }
        if ($payload['existing_option_ids'] === [] && $payload['new_values'] === []) {
            throw ValidationException::withMessages(['existing_option_ids' => __('configurator.creation.choose_values')]);
        }
        $aggregateAttribute = null;
        $sources = [];
        $readyForAggregate = false;
        try {
            return $this->saveConfiguratorDefinition->change($actor, $owner, function (array $draft) use ($actor, $inclusion, $payload, $refs, &$aggregateAttribute, &$sources, &$readyForAggregate): array {
                $aggregateAttribute = array_search((string) $inclusion->id, array_column($draft['attributes'], 'id'), true);
                if ($aggregateAttribute === false) {
                    abort(404);
                }
                $attribute = &$draft['attributes'][$aggregateAttribute];
                $shared = Attribute::findOrFail($attribute['attribute_id']);
                $masters = $this->createMasterValues->handle($actor, $payload['master_values'], array_values(array_unique($refs)));
                $resolved = array_map(fn (array $row): array => ['value_id' => isset($row['value_ref']) ? $masters[$row['value_ref']]->id : (int) $row['value_id'], 'code' => $row['code']], $payload['new_values']);
                try {
                    $this->addAttributeValues->handle($actor, $shared, $resolved);
                } catch (ValidationException $exception) {
                    $errors = [];
                    foreach ($exception->errors() as $path => $messages) {
                        $path = preg_replace('/^pending_options/', 'new_values', $path);
                        if (preg_match('/^new_values\.(\d+)\.value_id$/', $path, $match) && isset($payload['new_values'][(int) $match[1]]['value_ref'])) {
                            $path = 'new_values.'.$match[1].'.value_ref';
                        }
                        $errors[$path] = $messages;
                    }
                    throw ValidationException::withMessages($errors);
                }
                $created = Option::where('attribute_id', $shared->id)->whereIn('value_id', array_column($resolved, 'value_id'))->get()->keyBy('value_id');
                $requested = [];
                foreach ($payload['existing_option_ids'] as $optionId) {
                    $requested[] = ['id' => (int) $optionId, 'source' => 'existing_option_ids'];
                }
                foreach ($resolved as $index => $row) {
                    $option = $created[$row['value_id']] ?? null;
                    if (! $option || $option->code !== $row['code']) {
                        throw ValidationException::withMessages(['new_values.'.$index => __('catalog.creation.missing_ref')]);
                    }
                    $requested[] = ['id' => $option->id, 'source' => 'new_values.'.$index];
                }
                $options = Option::where('attribute_id', $shared->id)->whereIn('id', array_column($requested, 'id'))->get()->keyBy('id');
                $included = array_column($attribute['options'], 'option_id');
                $order = $attribute['options'] === [] ? 0 : max(array_column($attribute['options'], 'display_order')) + 1;
                foreach ($requested as $request) {
                    $option = $options[$request['id']] ?? null;
                    if (! $option || ! $option->is_active || $option->is_hidden || in_array($request['id'], $included, true)) {
                        throw ValidationException::withMessages([$request['source'] => __('configurator.validation.choose_current_not_already_included_attribute_values')]);
                    }
                    $sources[count($attribute['options'])] = $request['source'];
                    $attribute['options'][] = [...app(ConfiguratorInclusionDrafts::class)->option($option), 'display_order' => $order++];
                    $included[] = $option->id;
                }
                $readyForAggregate = true;

                return $draft;
            });
        } catch (ValidationException $exception) {
            if (! $readyForAggregate) {
                throw $exception;
            }
            $errors = [];
            foreach ($exception->errors() as $path => $messages) {
                $target = 'save';
                if (preg_match('/^attributes\.(\d+)\.options\.(\d+)(?:\..*)?$/', $path, $parts) && (int) $parts[1] === $aggregateAttribute && isset($sources[(int) $parts[2]])) {
                    $target = $sources[(int) $parts[2]];
                }
                $errors[$target] = $messages;
            }
            throw ValidationException::withMessages($errors);
        }
    }
}
