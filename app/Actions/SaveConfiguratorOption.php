<?php

namespace App\Actions;

use App\Models\ConfiguratorAttribute;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaveConfiguratorOption
{
    public const FIELDS = ['option_id', 'label_override', 'display_value_override', 'hint', 'hidden_by_default', 'disabled_by_default'];

    public function __construct(private SaveConfiguratorDefinition $save) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, ConfiguratorAttribute $owner, ?int $id, array $data): void
    {
        $this->save->change($actor, $owner->configurator, function (array $draft) use ($owner, $id, $data): array {
            if (array_diff(array_keys($data), self::FIELDS) !== []) {
                throw ValidationException::withMessages(['option' => 'Unsupported Option fields.']);
            }
            $attributeIndex = array_search((string) $owner->id, array_column($draft['attributes'], 'id'), true);
            abort_if($attributeIndex === false, 404);
            $attribute = $draft['attributes'][$attributeIndex];
            $index = array_search((string) $id, array_column($attribute['options'], 'id'), true);
            if ($id !== null && $index === false) {
                throw ValidationException::withMessages(['option' => 'This Option does not belong to this Attribute.']);
            }
            foreach (['label_override', 'display_value_override', 'hint'] as $field) {
                if (array_key_exists($field, $data) && blank($data[$field])) {
                    $data[$field] = null;
                }
            }
            if ($id === null) {
                $attribute['options'][] = [...['id' => 'new:'.Str::uuid(), 'label_override' => null, 'display_value_override' => null, 'hint' => null, 'hidden_by_default' => false, 'disabled_by_default' => false,
                    'display_order' => ($attribute['options'] === [] ? -1 : max(array_column($attribute['options'], 'display_order'))) + 1], ...$data];
            } else {
                $attribute['options'][$index] = [...$attribute['options'][$index], ...$data];
            }
            $draft['attributes'][$attributeIndex] = $attribute;

            return $draft;
        });
    }
}
