<?php

namespace App\Services;

use App\Actions\SaveConfiguratorDefinition;
use App\Models\Configurator;
use App\Models\ConfiguratorAttribute;
use App\Models\ConfiguratorRule;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ItemListOrdering
{
    public function column(string $key): ?string
    {
        return match ($key) {
            'configurator-attributes', 'inclusion-options' => 'display_order',
            'configurator-rules' => 'priority',
            'rule-mappings' => 'sort_order',
            'group-card-properties' => 'display_order',
            default => null,
        };
    }

    /** @return list<int|string> */
    public function ids(User $actor, string $key, int $parentId): array
    {
        $column = $this->column($key);
        abort_if($column === null, 403);
        $query = app(ItemLists::class)->definition($actor, $key, $parentId)->query;
        if (is_array($query)) {
            return array_keys($query);
        }

        return $query->reorder()->orderBy($column, $key === 'configurator-rules' ? 'desc' : 'asc')->orderBy('id')->pluck('id')->all();
    }

    /** @param list<int|string> $ids */
    public function reorder(User $actor, string $key, int $parentId, array $ids): void
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        abort_if($this->column($key) === null, 403);
        if ($key === 'group-card-properties') {
            DB::transaction(function () use ($parentId, $ids): void {
                $group = app(CatalogIntegrity::class)->lockGroups()->get($parentId);
                abort_if($group === null, 404);
                $settings = $group->result_settings ?? [];
                $expected = CatalogPolicy::resultSettings($settings)['card_properties'];
                $order = $this->completeOrder($ids, $expected);
                if ($order === $expected) {
                    return;
                }
                $settings['card_properties'] = $order;
                $group->result_settings = $settings;
                $group->save();
                app(CatalogRevisions::class)->advance([$group->id]);
            }, attempts: 3);

            return;
        }
        $owner = $this->owner($key, $parentId);
        $save = app(SaveConfiguratorDefinition::class);
        if ($key !== 'rule-mappings') {
            $save->reorder($actor, $owner, match ($key) {
                'configurator-attributes' => 'attributes',
                'configurator-rules' => 'rules',
                'inclusion-options' => 'options:'.$parentId,
            }, $ids);

            return;
        }
        $save->change($actor, $owner, function (array $draft) use ($parentId, $ids): array {
            foreach ($draft['rules'] as &$rule) {
                if ((string) $rule['id'] !== (string) $parentId) {
                    continue;
                }
                $submitted = $this->completeOrder($ids, array_column($rule['sets'], 'id'));
                foreach ($rule['sets'] as &$set) {
                    $set['sort_order'] = array_search((string) $set['id'], $submitted, true);
                }
                unset($set);

                return $draft;
            }
            throw ValidationException::withMessages(['order' => 'This Rule no longer belongs to the Configurator.']);
        });
    }

    public function move(User $actor, string $key, int $parentId, int|string $id, int $direction): void
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        abort_if($this->column($key) === null, 403);
        if (! in_array($direction, [-1, 1], true)) {
            throw ValidationException::withMessages(['order' => 'Choose up or down.']);
        }
        DB::transaction(function () use ($actor, $key, $parentId, $id, $direction): void {
            if ($key === 'group-card-properties') {
                app(CatalogIntegrity::class)->lockGroups();
            } else {
                Configurator::whereKey($this->owner($key, $parentId)->id)->lockForUpdate()->firstOrFail();
            }
            $ids = $this->ids($actor, $key, $parentId);
            $position = array_search((string) $id, array_map('strval', $ids), true);
            if ($position === false) {
                throw ValidationException::withMessages(['order' => 'This row does not belong to this list.']);
            }
            $target = $position + $direction;
            if (! array_key_exists($target, $ids)) {
                return;
            }
            [$ids[$position], $ids[$target]] = [$ids[$target], $ids[$position]];
            $this->reorder($actor, $key, $parentId, $ids);
        });
    }

    private function owner(string $key, int $parentId): Configurator
    {
        return match ($key) {
            'configurator-attributes', 'configurator-rules' => Configurator::findOrFail($parentId),
            'inclusion-options' => ConfiguratorAttribute::findOrFail($parentId)->configurator,
            'rule-mappings' => ConfiguratorRule::findOrFail($parentId)->configurator,
            default => abort(403),
        };
    }

    /** @param list<int|string> $ids
     * @param  list<int|string>  $expected
     * @return list<string>
     */
    private function completeOrder(array $ids, array $expected): array
    {
        if (! array_is_list($ids) || array_filter($ids, fn (mixed $id): bool => ! is_int($id) && ! is_string($id)) !== []) {
            throw ValidationException::withMessages(['order' => 'Submit the complete unique row order.']);
        }
        $submitted = array_map('strval', $ids);
        $sorted = $submitted;
        $expected = array_map('strval', $expected);
        sort($sorted, SORT_STRING);
        sort($expected, SORT_STRING);
        if ($sorted !== $expected) {
            throw ValidationException::withMessages(['order' => 'Submit the complete unique row order, including rows hidden by filters.']);
        }

        return $submitted;
    }
}
