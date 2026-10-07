<?php

namespace App\Actions;

use App\Models\Attribute;
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
        if (! $records->first() instanceof ConfiguratorAttribute && ! $records->first() instanceof ConfiguratorRule) {
            DB::transaction(function () use ($actor, $records, $active, $groupBehavior, $hidden): void {
                foreach ($records as $record) {
                    $this->handle($actor, $record, $active, $groupBehavior, $hidden);
                }
            }, attempts: 3);

            return;
        }
        if ($hidden !== null) {
            throw ValidationException::withMessages(['hidden' => 'Only shared Options support hidden status.']);
        }
        DB::transaction(function () use ($actor, $records, $active): void {
            $locked = $this->lockForReview($records);
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

    /** @param Collection<int, Model> $records @return Collection<int, Model> */
    public function lockForReview(Collection $records): Collection
    {
        if ($records->isEmpty() || $records->contains(fn (Model $record): bool => $record::class !== $records->first()::class)) {
            throw ValidationException::withMessages(['is_active' => 'Choose records from one status table.']);
        }
        app(CatalogIntegrity::class)->lockGroups();
        $ownerIds = [];
        foreach ($records as $record) {
            $ownerIds = [...$ownerIds, ...$this->ownerIds($record, lock: true)];
        }
        Configurator::whereKey(array_unique($ownerIds))->orderBy('id')->lockForUpdate()->get();
        $class = $records->first()::class;

        return $class::whereKey($records->pluck('id'))->orderBy('id')->lockForUpdate()->get();
    }

    /** @return list<int> */
    private function ownerIds(Model $record, bool $lock = false): array
    {
        return match (true) {
            $record instanceof Attribute, $record instanceof Option => app(CanonicalUsage::class)->configurators($record)->orderBy('id')->when($lock, fn ($query) => $query->lockForUpdate())->pluck('id')->all(),
            $record instanceof ConfiguratorAttribute, $record instanceof ConfiguratorRule => [$record->configurator_id],
            $record instanceof Configurator => [$record->id],
            default => [],
        };
    }

    public function handle(User $actor, Model $record, bool $active, ?string $groupBehavior = null, ?bool $hidden = null): void
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        if (! in_array($record::class, [Attribute::class, Option::class, Configurator::class, Product::class, Group::class, ConfiguratorAttribute::class, ConfiguratorRule::class], true)) {
            throw ValidationException::withMessages(['status' => 'This record does not support lifecycle status.']);
        }
        if ($record instanceof Configurator && ! $active && ! in_array($groupBehavior, ['visible', 'hide', 'unassign'], true)) {
            throw ValidationException::withMessages(['group_behavior' => 'Choose what should happen to assigned Groups and their Products.']);
        }
        if ($hidden !== null && ! $record instanceof Option) {
            throw ValidationException::withMessages(['hidden' => 'Only shared Options support hidden status.']);
        }
        DB::transaction(function () use ($actor, $record, $active, $groupBehavior, $hidden): void {
            $fresh = $this->lockForReview(collect([$record]))->firstOrFail();
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
