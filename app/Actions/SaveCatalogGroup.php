<?php

namespace App\Actions;

use App\Models\Group;
use App\Models\User;
use App\Services\CatalogIntegrity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class SaveCatalogGroup
{
    public function __construct(private CatalogIntegrity $integrity) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, ?Group $group, array $data): Group
    {
        Gate::forUser($actor)->authorize('manage-catalog');

        return DB::transaction(function () use ($group, $data): Group {
            $groups = $this->integrity->lockGroups();
            $record = $group === null ? new Group : $groups->get($group->id);
            abort_if($record === null, 404);
            $validated = Validator::make(['group' => $data], [
                'group' => ['array:name,description,parent_id,sort_order,configurator_id'],
                'group.name' => ['required', 'string', 'max:255'],
                'group.description' => ['nullable', 'string', 'max:5000'],
                'group.parent_id' => ['nullable', 'integer', 'min:1'],
                'group.sort_order' => ['sometimes', 'integer'],
                'group.configurator_id' => ['nullable', 'integer', 'exists:configurators,id'],
            ])->validate()['group'];
            $record->fill($validated);
            $this->integrity->validateGroup($record, $groups);
            $record->save();

            return $record;
        }, attempts: 3);
    }
}
