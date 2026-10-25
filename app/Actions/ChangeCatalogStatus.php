<?php

namespace App\Actions;

use App\Models\Attribute;
use App\Models\CatalogContextSettings;
use App\Models\Configurator;
use App\Models\ConfiguratorAttribute;
use App\Models\ConfiguratorRule;
use App\Models\Group;
use App\Models\Option;
use App\Models\Product;
use App\Models\User;
use App\Services\CanonicalUsage;
use App\Services\CatalogIntegrity;
use App\Services\CatalogRevisions;
use App\Services\ConfiguratorDefinitionLoader;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ChangeCatalogStatus
{
    /** @param Collection<int, Model> $records */
    public function handleSelection(User $actor, Collection $records, bool $active, ?string $groupBehavior = null, ?bool $hidden = null): void
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        $ownerIds = $this->reviewOwnerIds($records);
        if (! $records->first() instanceof ConfiguratorAttribute && ! $records->first() instanceof ConfiguratorRule) {
            DB::transaction(function () use ($actor, $records, $active, $groupBehavior, $hidden, $ownerIds): void {
                $locked = $this->lockForReview($records, $ownerIds);
                abort_unless($locked->count() === $records->count(), 404);
                foreach ($locked as $record) {
                    $this->handle($actor, $record, $active, $groupBehavior, $hidden);
                }
            }, attempts: 3);

            return;
        }
        if ($hidden !== null) {
            throw ValidationException::withMessages(['hidden' => __('admin.status.validation.only_shared_attribute_values_support_hidden_status')]);
        }
        DB::transaction(function () use ($actor, $records, $active, $ownerIds): void {
            $locked = $this->lockForReview($records, $ownerIds);
            abort_unless($locked->count() === $records->count(), 404);
            foreach ($locked as $record) {
                abort_unless($record->configurator_id === $records->firstWhere('id', $record->id)->configurator_id, 404);
            }
            foreach ($locked->groupBy('configurator_id')->sortKeys() as $ownerId => $rows) {
                $ids = $rows->pluck('id')->map(fn (int $id): string => (string) $id)->all();
                $bucket = $rows->first() instanceof ConfiguratorAttribute ? 'attributes' : 'rules';
                app(SaveConfiguratorDefinition::class)->change($actor, Configurator::findOrFail($ownerId), function (array $draft) use ($ids, $bucket, $active): array {
                    foreach ($draft[$bucket] as &$row) {
                        if (in_array((string) $row['id'], $ids, true)) {
                            $row['is_active'] = $active;
                        }
                    }
                    unset($row);

                    return $draft;
                });
            }
            app(CatalogRevisions::class)->advance(Group::whereIn('configurator_id', $locked->pluck('configurator_id'))->pluck('id')->all(), descendants: true);
        }, attempts: 3);
    }

    /** @param Collection<int, Model> $records @return list<int> */
    public function reviewOwnerIds(Collection $records): array
    {
        if ($records->isEmpty() || $records->contains(fn (Model $record): bool => $record::class !== $records->first()::class)) {
            throw ValidationException::withMessages(['is_active' => __('admin.status.validation.choose_records_from_one_status_table')]);
        }
        $ownerIds = [];
        foreach ($records as $record) {
            $ownerIds = [...$ownerIds, ...$this->ownerIds($record)];
        }
        sort($ownerIds, SORT_NUMERIC);

        return array_values(array_unique($ownerIds));
    }

    /** @param Collection<int, Model> $records @param list<int> $ownerIds @return Collection<int, Model> */
    public function lockForReview(Collection $records, array $ownerIds): Collection
    {
        $owners = Configurator::whereKey($ownerIds)->orderBy('id')->lockForUpdate()->get();
        $class = $records->first()::class;
        $attributeIds = $class === Attribute::class ? $records->pluck('id')->all() : [];
        $optionIds = $class === Option::class ? $records->pluck('id')->all() : [];
        if (in_array($class, [ConfiguratorAttribute::class, ConfiguratorRule::class], true)) {
            CatalogContextSettings::current(lock: true);
            $loader = app(ConfiguratorDefinitionLoader::class);
            foreach ($owners as $owner) {
                $references = $loader->canonicalReferences($loader->draft($owner));
                $attributeIds = [...$attributeIds, ...$references['attribute_ids']];
                $optionIds = [...$optionIds, ...$references['option_ids']];
            }
        }
        app(CanonicalUsage::class)->lockParents($attributeIds, [], $optionIds);
        if ($class === Attribute::class) {
            Option::whereIn('attribute_id', $attributeIds)->orderBy('id')->lockForUpdate()->get();
        }
        if (in_array($class, [Attribute::class, Option::class], true)) {
            app(CanonicalUsage::class)->assertConfiguratorIds($ownerIds, attributeIds: $class === Attribute::class ? $attributeIds : [], optionIds: $class === Option::class ? $optionIds : []);
        }
        app(CatalogIntegrity::class)->lockGroups();

        return $class::whereKey($records->pluck('id'))->orderBy('id')->lockForUpdate()->get();
    }

    /** @return list<int> */
    private function ownerIds(Model $record): array
    {
        return match (true) {
            $record instanceof Attribute => app(CanonicalUsage::class)->configuratorIds(attributeIds: [$record->id]),
            $record instanceof Option => app(CanonicalUsage::class)->configuratorIds(optionIds: [$record->id]),
            $record instanceof ConfiguratorAttribute, $record instanceof ConfiguratorRule => [$record->configurator_id],
            $record instanceof Configurator => [$record->id],
            default => [],
        };
    }

    public function handle(User $actor, Model $record, bool $active, ?string $groupBehavior = null, ?bool $hidden = null): void
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        if (! in_array($record::class, [Attribute::class, Option::class, Configurator::class, Product::class, Group::class, ConfiguratorAttribute::class, ConfiguratorRule::class], true)) {
            throw ValidationException::withMessages(['status' => __('admin.status.validation.this_record_does_not_support_lifecycle_status')]);
        }
        if ($record instanceof Configurator && ! $active && ! in_array($groupBehavior, ['visible', 'hide', 'unassign'], true)) {
            throw ValidationException::withMessages(['group_behavior' => __('admin.status.validation.choose_what_should_happen_to_assigned_groups')]);
        }
        if ($hidden !== null && ! $record instanceof Option) {
            throw ValidationException::withMessages(['hidden' => __('admin.status.validation.only_shared_attribute_values_support_hidden_status')]);
        }
        $ownerIds = $this->reviewOwnerIds(collect([$record]));
        DB::transaction(function () use ($actor, $record, $active, $groupBehavior, $hidden, $ownerIds): void {
            $fresh = $this->lockForReview(collect([$record]), $ownerIds)->firstOrFail();
            $ownerIds = $this->ownerIds($fresh);
            $groupIds = Group::whereIn('configurator_id', $ownerIds)->pluck('id')->all();
            if ($fresh instanceof ConfiguratorAttribute || $fresh instanceof ConfiguratorRule) {
                abort_unless($fresh->configurator_id === $record->configurator_id, 404);
                app(SaveConfiguratorDefinition::class)->change($actor, $fresh->configurator, function (array $draft) use ($fresh, $active): array {
                    $bucket = $fresh instanceof ConfiguratorAttribute ? 'attributes' : 'rules';
                    foreach ($draft[$bucket] as &$row) {
                        if ((string) $row['id'] === (string) $fresh->id) {
                            $row['is_active'] = $active;
                        }
                    }
                    unset($row);

                    return $draft;
                });
            } else {
                $fresh->is_active = $active;
                if ($fresh instanceof Option && $hidden !== null) {
                    $fresh->is_hidden = $hidden;
                }
                if ($fresh instanceof Configurator) {
                    if (! $active) {
                        $fresh->disabled_group_behavior = $groupBehavior;
                    }
                    if (! $active && $groupBehavior === 'unassign') {
                        $fresh->groups()->update(['configurator_id' => null]);
                    }
                }
                if ($fresh->isDirty()) {
                    $fresh->save();
                }
            }
            if ($fresh instanceof Group) {
                $groupIds[] = $fresh->id;
            } elseif ($fresh instanceof Product) {
                $groupIds[] = $fresh->group_id;
            }
            app(CatalogRevisions::class)->advance($groupIds, descendants: true);
        }, attempts: 3);
    }
}
