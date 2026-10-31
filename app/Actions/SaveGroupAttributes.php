<?php

namespace App\Actions;

use App\Models\Group;
use App\Models\User;
use App\Services\CatalogRevisions;
use App\Services\GroupAttributes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class SaveGroupAttributes
{
    public function __construct(private GroupAttributes $attributes, private CatalogRevisions $revisions) {}

    /** @param array<string, list<string>> $changes */
    public function handle(User $actor, Group $group, array $changes, string $expectedFingerprint): Group
    {
        Gate::forUser($actor)->authorize('manage-catalog');

        return DB::transaction(function () use ($group, $changes, $expectedFingerprint): Group {
            $record = Group::query()->whereKey($group->getKey())->lockForUpdate()->firstOrFail();
            if ($changes === []) {
                return $record;
            }
            if (! hash_equals($this->attributes->fingerprint($record), $expectedFingerprint)) {
                throw ValidationException::withMessages(['catalog_attribute_fingerprint' => __('catalog.group_attributes.stale')]);
            }
            $prepared = $this->attributes->prepare($record, $changes);
            if ($prepared['changed']) {
                $record->catalog_attributes = (object) $prepared['values'];
                $record->save();
                $this->revisions->advance([$record->id]);
            }

            return $record;
        }, attempts: 1);
    }
}
