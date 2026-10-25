<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\Configurator;
use App\Models\ConfiguratorAttribute;
use App\Models\ConfiguratorOption;
use App\Models\ConfiguratorRule;
use App\Models\Group;
use App\Models\Option;
use App\Models\Product;
use App\Models\User;
use App\Models\Value;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class ItemListMembers
{
    public function query(User $actor, string $key, int $parentId): Builder
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        if (preg_match('/\Acanonical-(attribute|value|option)-(options|inclusions|defaults|rules)\z/', $key, $parts)) {
            $record = match ($parts[1]) {
                'attribute' => Attribute::findOrFail($parentId),
                'value' => Value::findOrFail($parentId),
                'option' => Option::findOrFail($parentId),
            };
            $usage = app(CanonicalUsage::class);

            return match ($parts[2]) {
                'options' => $usage->options($record),
                'defaults' => $usage->defaults($record),
                'rules' => $usage->rules($record),
                'inclusions' => $this->query($actor, match ($parts[1]) {
                    'attribute' => 'attribute-inclusions',
                    'value' => 'value-option-inclusions',
                    'option' => 'option-inclusions',
                }, $parentId),
            };
        }

        return match ($key) {
            'inclusion-blocking-rules' => app(InclusionUsage::class)->rules(ConfiguratorAttribute::findOrFail($parentId)),
            'local-option-blocking-rules' => app(InclusionUsage::class)->rules(ConfiguratorOption::findOrFail($parentId)),
            'local-option-defaults' => app(InclusionUsage::class)->defaults(ConfiguratorOption::findOrFail($parentId)),
            'inclusion-options' => ConfiguratorAttribute::findOrFail($parentId)->options()->getQuery(),
            'rule-mappings' => ConfiguratorRule::findOrFail($parentId)->mappingSets()->getQuery(),
            'rule-effects' => ConfiguratorRule::findOrFail($parentId)->effects()->getQuery(),
            'attribute-options' => Attribute::findOrFail($parentId)->options()->getQuery(),
            'value-options' => Value::findOrFail($parentId)->options()->getQuery(),
            'attribute-inclusions' => Attribute::findOrFail($parentId)->inclusions()->getQuery(),
            'value-option-inclusions' => ConfiguratorOption::whereIn('option_id', Value::findOrFail($parentId)->options()->select('id')),
            'option-inclusions' => Option::findOrFail($parentId)->inclusions()->getQuery(),
            'configurator-groups' => Configurator::findOrFail($parentId)->groups()->getQuery(),
            'configurator-products' => Product::whereIn('group_id', Configurator::findOrFail($parentId)->groups()->select('id')),
            'configurator-attributes' => Configurator::findOrFail($parentId)->attributes()->getQuery(),
            'configurator-rules' => Configurator::findOrFail($parentId)->rules()->getQuery(),
            'group-products' => Group::findOrFail($parentId)->products()->getQuery(),
            default => abort(404, __('catalog.validation.unknown_item_list')),
        };
    }

    /** @return list<string> */
    public function propertyKeys(User $actor, int $groupId): array
    {
        Gate::forUser($actor)->authorize('manage-catalog');

        return CatalogPolicy::resultSettings(Group::findOrFail($groupId)->result_settings)['card_properties'];
    }
}
