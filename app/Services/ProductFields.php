<?php

namespace App\Services;

use App\Models\Group;
use App\Models\GroupFilter;
use App\Models\RuleCondition;
use App\Models\SubGroup;
use Closure;
use Illuminate\Support\Facades\DB;

class ProductFields
{
    public static function validKey(mixed $key): bool
    {
        return is_string($key) && $key !== '' && mb_check_encoding($key, 'UTF-8')
            && mb_strlen($key) <= 100 && ! str_contains($key, "\0");
    }

    public static function referenceRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (! self::validKey($value)) {
                $fail(__('validation.string', ['attribute' => $attribute]));
            }
        };
    }

    /** @return list<string> */
    public static function mappedKeys(): array
    {
        return CatalogImportParser::profileKeys();
    }

    /**
     * @param  list<string>|null  $savedRuleKeys
     * @return list<string>
     */
    public static function keys(?int $groupId = null, ?int $configuratorId = null, ?array $savedRuleKeys = null): array
    {
        $groups = Group::query()->when($groupId !== null, fn ($query) => $query->whereKey($groupId))
            ->when($configuratorId !== null, fn ($query) => $query->where('configurator_id', $configuratorId))
            ->get(['id', 'configurator_id', 'result_settings']);
        $groupIds = $groups->modelKeys();
        $query = DB::table('products')->whereIn('products.group_id', $groupIds);
        $observed = [];
        if (DB::getDriverName() === 'mysql') {
            foreach ($query->selectRaw('DISTINCT JSON_KEYS(profile_fields) AS field_keys')->get() as $row) {
                $observed = [...$observed, ...json_decode($row->field_keys ?? '[]', true, flags: JSON_THROW_ON_ERROR)];
            }
        } else {
            $observed = $query->crossJoin(DB::raw('json_each(products.profile_fields) AS profile_field'))
                ->distinct()->pluck('profile_field.key')->map(fn (mixed $key): string => (string) $key)->all();
        }
        $saved = [...GroupFilter::whereIn('group_id', $groupIds)->pluck('property_key')->all(),
            ...SubGroup::whereIn('group_id', $groupIds)->pluck('property_key')->all()];
        foreach ($groups as $group) {
            $saved = [...$saved, ...($group->result_settings['card_properties'] ?? [])];
        }
        $configuratorIds = $configuratorId !== null ? [$configuratorId] : $groups->pluck('configurator_id')->filter()->unique()->all();
        $savedRuleKeys ??= RuleCondition::query()->whereHas('rule', fn ($query) => $query->whereIn('configurator_id', $configuratorIds))
            ->whereNotNull('property_key')->pluck('property_key')->all();
        $saved = [...$saved, ...$savedRuleKeys];
        $keys = array_values(array_unique(array_filter([...self::mappedKeys(), ...$observed, ...$saved], self::validKey(...)), SORT_STRING));
        sort($keys, SORT_STRING);

        return $keys;
    }

    /** @return array<string, string> */
    public static function options(?int $groupId = null, ?int $configuratorId = null): array
    {
        $keys = self::keys($groupId, $configuratorId);

        return array_combine($keys, array_map(fn (string $key): string => str_replace('_', ' ', $key), $keys));
    }
}
