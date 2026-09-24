<?php

namespace App\Actions;

use App\Models\Configurator;
use App\Models\Group;
use App\Models\User;
use App\Services\CatalogIntegrity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AssignConfiguratorGroups
{
    public function __construct(private CatalogIntegrity $integrity) {}

    public function assign(User $actor, Configurator $configurator, int $groupId): void
    {
        $this->changeMembership($actor, $configurator, $groupId, true);
    }

    public function unassign(User $actor, Configurator $configurator, int $groupId): void
    {
        $this->changeMembership($actor, $configurator, $groupId, false);
    }

    private function changeMembership(User $actor, Configurator $configurator, int $groupId, bool $assign): void
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        DB::transaction(function () use ($actor, $configurator, $groupId, $assign): void {
            $groups = $this->integrity->lockGroups();
            $record = Configurator::whereKey($configurator->id)->lockForUpdate()->firstOrFail();
            $ids = $groups->where('configurator_id', $record->id)->modelKeys();
            if (! $assign && ! in_array($groupId, $ids, true)) {
                throw ValidationException::withMessages(['groups' => 'This Group is no longer assigned to this Configurator.']);
            }
            $ids = $assign ? array_values(array_unique([...$ids, $groupId])) : array_values(array_diff($ids, [$groupId]));
            $this->handle($actor, $record, $ids);
        });
    }

    /** @param list<int|string> $groupIds */
    public function handle(User $actor, Configurator $configurator, array $groupIds): void
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        $ids = Validator::make(['groups' => $groupIds], ['groups' => ['present', 'array', 'list'], 'groups.*' => ['required', 'integer', 'distinct', 'min:1']])->validate()['groups'];
        $ids = array_map('intval', $ids);
        DB::transaction(function () use ($configurator, $ids): void {
            $groups = $this->integrity->lockGroups();
            $record = Configurator::whereKey($configurator->id)->lockForUpdate()->firstOrFail();
            foreach ($ids as $id) {
                $group = $groups->get($id);
                if ($group === null || ($group->configurator_id !== null && $group->configurator_id !== $record->id)) {
                    throw ValidationException::withMessages(['groups' => 'Choose existing unassigned Groups or this Configurator’s current assignments. Change another assignment explicitly from its Group.']);
                }
                $this->integrity->assertLeaf($group);
            }
            Group::where('configurator_id', $record->id)->whereNotIn('id', $ids)->update(['configurator_id' => null]);
            Group::whereKey($ids)->whereNull('configurator_id')->update(['configurator_id' => $record->id]);
        });
    }
}
