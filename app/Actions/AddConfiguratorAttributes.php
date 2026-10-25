<?php

namespace App\Actions;

use App\Models\Attribute;
use App\Models\Configurator;
use App\Models\Option;
use App\Models\User;
use App\Services\ConfiguratorDefinitionCompiler;
use App\Services\ConfiguratorInclusionDrafts;
use Closure;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AddConfiguratorAttributes
{
    public function __construct(private SaveConfiguratorDefinition $saveConfiguratorDefinition, private CreateSharedAttributes $createSharedAttributes, private ConfiguratorInclusionDrafts $inclusionDrafts) {}

    /**
     * @param  array{master_values?:list<array{ref:string,label?:?string,semantic_id?:?string,tags?:list<string>,description?:?string}>,attributes?:list<array{ref:string,label?:?string,key?:?string,values?:list<array{ref:string,value_id?:int|string,value_ref?:string,code:string}>}>,existing_attribute_ids?:list<int|string>,reviews?:list<array{ref:string,attribute_id?:int|string,attribute_ref?:string,settings?:array{input_type:string,label_override:?string,help_text:?string},values:list<array{ref:string,option_id?:int|string,option_ref?:string,local_fields?:array{label_override:?string,subtitle_mode:string,display_value_override:?string,hint:?string,hidden_by_default:bool,disabled_by_default:bool}}>,default_ref:string}>}  $payload
     */
    public function handle(User $actor, Configurator $owner, array $payload): Configurator
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        if (array_diff(array_keys($payload), ['master_values', 'attributes', 'existing_attribute_ids', 'reviews'])) {
            throw ValidationException::withMessages(['save' => __('configurator.creation.invalid_shape')]);
        }
        $payload += ['master_values' => [], 'attributes' => [], 'existing_attribute_ids' => [], 'reviews' => []];
        $id = self::idRule();
        $ref = self::refRule();
        Validator::make($payload, [
            'master_values' => ['array', 'list', 'max:500'],
            'master_values.*' => ['required', 'array:ref,label,semantic_id,tags,description'],
            'master_values.*.ref' => ['required', 'string', 'distinct:strict', $ref],
            'master_values.*.label' => ['sometimes', 'nullable', 'string'],
            'master_values.*.semantic_id' => ['sometimes', 'nullable', 'string'],
            'master_values.*.description' => ['sometimes', 'nullable', 'string'],
            'master_values.*.tags' => ['sometimes', 'array', 'list'],
            'master_values.*.tags.*' => ['string'],
            'attributes' => ['array', 'list', 'max:500'],
            'attributes.*' => ['required', 'array:ref,label,key,values'],
            'attributes.*.ref' => ['required', 'string', 'distinct:strict', $ref],
            'attributes.*.label' => ['sometimes', 'nullable', 'string'],
            'attributes.*.key' => ['sometimes', 'nullable', 'string'],
            'attributes.*.values' => ['sometimes', 'array', 'list', 'max:500'],
            'attributes.*.values.*' => ['required', 'array:ref,value_id,value_ref,code'],
            'attributes.*.values.*.ref' => ['required', 'string', 'distinct:strict', $ref],
            'attributes.*.values.*.value_id' => ['sometimes', 'required', $id],
            'attributes.*.values.*.value_ref' => ['sometimes', 'required', 'string', $ref],
            'attributes.*.values.*.code' => ['required', 'string'],
            'existing_attribute_ids' => ['array', 'list', 'max:500'],
            'existing_attribute_ids.*' => ['required', 'distinct', $id],
            'reviews' => ['array', 'list', 'max:500'],
            'reviews.*' => ['required', 'array:ref,attribute_id,attribute_ref,settings,values,default_ref'],
            'reviews.*.ref' => ['required', 'string', 'distinct:strict', $ref],
            'reviews.*.attribute_id' => ['sometimes', 'required', $id],
            'reviews.*.attribute_ref' => ['sometimes', 'required', 'string', $ref],
            'reviews.*.settings' => ['sometimes', 'array:input_type,label_override,help_text', 'required_array_keys:input_type,label_override,help_text'],
            'reviews.*.settings.input_type' => ['bail', 'required_with:reviews.*.settings', 'string', 'in:toggle,select'],
            'reviews.*.settings.label_override' => ['nullable', 'string', 'max:255'],
            'reviews.*.settings.help_text' => ['nullable', 'string', 'max:5000'],
            'reviews.*.values' => ['required', 'array', 'list', 'min:1', 'max:500'],
            'reviews.*.values.*' => ['required', 'array:ref,option_id,option_ref,local_fields'],
            'reviews.*.values.*.ref' => ['required', 'string', 'distinct:strict', $ref],
            'reviews.*.values.*.option_id' => ['sometimes', 'required', $id],
            'reviews.*.values.*.option_ref' => ['sometimes', 'required', 'string', $ref],
            'reviews.*.values.*.local_fields' => ['sometimes', 'array:label_override,subtitle_mode,display_value_override,hint,hidden_by_default,disabled_by_default', 'required_array_keys:label_override,subtitle_mode,display_value_override,hint,hidden_by_default,disabled_by_default'],
            'reviews.*.values.*.local_fields.label_override' => ['nullable', 'string', 'max:255'],
            'reviews.*.values.*.local_fields.hint' => ['nullable', 'string', 'max:1000'],
            'reviews.*.values.*.local_fields.hidden_by_default' => ['sometimes', self::booleanRule()],
            'reviews.*.values.*.local_fields.disabled_by_default' => ['sometimes', self::booleanRule()],
            'reviews.*.default_ref' => ['required', 'string', $ref],
        ])->validate();
        $new = [];
        foreach ($payload['attributes'] as $index => $attribute) {
            foreach ($attribute['values'] ?? [] as $valueIndex => $value) {
                self::oneSource($value, 'value_id', 'value_ref', "attributes.$index.values.$valueIndex");
            }
            if (CreateSharedAttributes::isPopulated($attribute)) {
                $new[$attribute['ref']] = $index;
            }
        }
        $selected = array_map(intval(...), $payload['existing_attribute_ids']);
        $claimed = [];
        foreach ($payload['reviews'] as $index => &$review) {
            self::oneSource($review, 'attribute_id', 'attribute_ref', "reviews.$index");
            $identity = isset($review['attribute_id']) ? 'id:'.(int) $review['attribute_id'] : $review['attribute_ref'];
            if (isset($claimed[$identity]) || (isset($review['attribute_id']) ? ! in_array((int) $review['attribute_id'], $selected, true) : ! isset($new[$identity]))) {
                throw ValidationException::withMessages(["reviews.$index" => __('configurator.validation.review_every_selected_attribute_before_including_it')]);
            }
            $claimed[$identity] = true;
            if (! in_array($review['default_ref'], array_column($review['values'], 'ref'), true)) {
                throw ValidationException::withMessages(["reviews.$index.default_ref" => __('configurator.validation.choose_a_stored_default_for_every_new')]);
            }
            foreach ($review['values'] as $valueIndex => &$value) {
                self::oneSource($value, 'option_id', 'option_ref', "reviews.$index.values.$valueIndex");
                if (isset($value['local_fields'])) {
                    $value['local_fields'] = [...$value['local_fields'], ...app(ConfiguratorDefinitionCompiler::class)->normalizeSubtitle($value['local_fields'], "reviews.$index.values.$valueIndex.local_fields")];
                }
            }
            unset($value);
        }
        unset($review);
        if ($claimed === [] || count($claimed) !== count($selected) + count($new)) {
            throw ValidationException::withMessages(['reviews' => __('configurator.validation.review_every_selected_attribute_before_including_it')]);
        }
        $aggregateMap = [];
        $readyForAggregate = false;
        try {
            return $this->saveConfiguratorDefinition->change($actor, $owner, function (array $draft) use ($actor, $payload, $selected, $new, &$aggregateMap, &$readyForAggregate): array {
                $existing = Attribute::whereIn('id', $selected)->where('is_active', true)->get()->keyBy('id');
                if ($existing->count() !== count($selected) || array_intersect($selected, array_column($draft['attributes'], 'attribute_id'))) {
                    throw ValidationException::withMessages(['existing_attribute_ids' => __('configurator.validation.choose_current_not_already_included_attribute_values')]);
                }
                $created = $new === [] ? [] : $this->createSharedAttributes->handle($actor, $payload['attributes'], $payload['master_values']);
                $options = Option::whereIn('attribute_id', [...$selected, ...array_map(fn (Attribute $attribute): int => $attribute->id, $created)])->get()->keyBy('id');
                $associationIds = [];
                foreach ($payload['attributes'] as $attribute) {
                    if (! isset($created[$attribute['ref']])) {
                        continue;
                    }
                    foreach ($attribute['values'] ?? [] as $association) {
                        $matches = $options->filter(fn (Option $option): bool => $option->attribute_id === $created[$attribute['ref']]->id && $option->code === $association['code']);
                        if ($matches->count() !== 1) {
                            throw ValidationException::withMessages(['attributes' => __('catalog.creation.missing_ref')]);
                        }
                        $associationIds[$association['ref']] = $matches->sole()->id;
                    }
                }
                $displayOrder = $draft['attributes'] === [] ? 0 : max(array_column($draft['attributes'], 'display_order')) + 1;
                $codeOrder = $draft['attributes'] === [] ? 0 : max(array_column($draft['attributes'], 'code_order')) + 1;
                foreach ($payload['reviews'] as $index => $review) {
                    $attributeId = isset($review['attribute_id']) ? (int) $review['attribute_id'] : $created[$review['attribute_ref']]->id;
                    $local = [];
                    foreach ($review['values'] as $valueIndex => $value) {
                        $optionId = isset($value['option_id']) ? (int) $value['option_id'] : ($associationIds[$value['option_ref']] ?? null);
                        $option = $options[$optionId] ?? null;
                        if (! $option || $option->attribute_id !== $attributeId || ! $option->is_active || $option->is_hidden) {
                            throw ValidationException::withMessages(["reviews.$index.values.$valueIndex.".(isset($value['option_id']) ? 'option_id' : 'option_ref') => __('configurator.validation.disabled_or_hidden_attribute_values_cannot_be')]);
                        }
                        $local[] = [...$this->inclusionDrafts->option($option), ...($value['local_fields'] ?? []), 'id' => $value['ref'], 'display_order' => $valueIndex];
                    }
                    $aggregateMap[count($draft['attributes'])] = $index;
                    $draft['attributes'][] = ['id' => $review['ref'], 'attribute_id' => $attributeId, ...($review['settings'] ?? ['input_type' => 'toggle', 'label_override' => null, 'help_text' => null]), 'display_order' => $displayOrder++, 'code_order' => $codeOrder++, 'default_configurator_option_id' => $review['default_ref'], 'options' => $local];
                }
                $readyForAggregate = true;

                return $draft;
            }, additionalParents: [
                'attribute_ids' => $selected,
                'option_ids' => array_values(array_filter(data_get($payload, 'reviews.*.values.*.option_id', []), fn (mixed $id): bool => is_int($id) || is_string($id))),
                'value_ids' => array_values(array_filter(data_get($payload, 'attributes.*.values.*.value_id', []), fn (mixed $id): bool => is_int($id) || is_string($id))),
            ]);
        } catch (ValidationException $exception) {
            if (! $readyForAggregate) {
                throw $exception;
            }
            $errors = [];
            foreach ($exception->errors() as $path => $messages) {
                if (! preg_match('/^attributes\.(\d+)(?:\.(.*))?$/', $path, $parts) || ! array_key_exists((int) $parts[1], $aggregateMap)) {
                    $errors['save'] = $messages;

                    continue;
                }
                $reviewIndex = $aggregateMap[(int) $parts[1]];
                $field = $parts[2] ?? '';
                $target = 'reviews.'.$reviewIndex;
                if (preg_match('/^options\.(\d+)\.(.*)$/', $field, $optionParts)) {
                    $value = $payload['reviews'][$reviewIndex]['values'][(int) $optionParts[1]];
                    $target .= '.values.'.$optionParts[1];
                    $target .= match ($optionParts[2]) {
                        'id' => '.ref',
                        'option_id' => isset($value['option_id']) ? '.option_id' : '.option_ref',
                        'label_override', 'subtitle_mode', 'display_value_override', 'hint', 'hidden_by_default', 'disabled_by_default' => '.local_fields.'.$optionParts[2],
                        default => '',
                    };
                } else {
                    $target .= match ($field) {
                        'id' => '.ref',
                        'attribute_id' => isset($payload['reviews'][$reviewIndex]['attribute_id']) ? '.attribute_id' : '.attribute_ref',
                        'default_configurator_option_id' => '.default_ref',
                        'input_type', 'label_override', 'help_text' => '.settings.'.$field,
                        default => '',
                    };
                }
                $errors[$target] = $messages;
            }
            throw ValidationException::withMessages($errors);
        }
    }

    private static function oneSource(array $row, string $id, string $ref, string $path): void
    {
        if (array_key_exists($id, $row) === array_key_exists($ref, $row)) {
            throw ValidationException::withMessages([$path => __('attribute-options.choose_one_source')]);
        }
    }

    private static function idRule(): Closure
    {
        return function (string $field, mixed $value, Closure $fail): void {
            if ((! is_int($value) && ! is_string($value)) || ! preg_match('/\A[1-9][0-9]*\z/D', (string) $value) || filter_var($value, FILTER_VALIDATE_INT) === false) {
                $fail(__('validation.integer', ['attribute' => $field]));
            }
        };
    }

    private static function booleanRule(): Closure
    {
        return function (string $field, mixed $value, Closure $fail): void {
            if (! is_bool($value)) {
                $fail(__('validation.boolean', ['attribute' => $field]));
            }
        };
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
