<?php

namespace App\Actions;

use App\DTO\CatalogAssetChange;
use App\Models\Configurator;
use App\Models\Group;
use App\Models\User;
use App\Services\CatalogAssetAssignments;
use App\Services\CatalogIntegrity;
use App\Services\CatalogRevisions;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SaveCatalogGroup
{
    public function __construct(private CatalogIntegrity $integrity, private CatalogRevisions $revisions) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, ?Group $group, array $data, ?CatalogAssetChange $imageChange = null, ?string $expectedImageFingerprint = null, ?Closure $resolveImageChange = null): Group
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        if ($imageChange !== null && $resolveImageChange !== null) {
            throw ValidationException::withMessages(['image' => 'Choose one main-image change source.']);
        }

        return DB::transaction(function () use ($group, $data, $imageChange, $expectedImageFingerprint, $resolveImageChange): Group {
            $ownerIds = array_values(array_unique(array_map('intval', array_filter([$group?->configurator_id, $data['configurator_id'] ?? null], is_scalar(...)))));
            $ownerIds = array_values(array_filter($ownerIds, fn (int $id): bool => $id > 0));
            Configurator::whereKey($ownerIds)->orderBy('id')->lockForUpdate()->get();
            $groups = $this->integrity->lockGroups();
            $record = $group === null ? new Group : $groups->get($group->id);
            abort_if($record === null, 404);
            if ($record->configurator_id !== null && ! in_array($record->configurator_id, $ownerIds, true)) {
                throw ValidationException::withMessages(['configurator_id' => __('configurator.validation.changes_could_not_be_saved_your_draft')]);
            }
            if ($group !== null && ($imageChange !== null || $resolveImageChange !== null)
                && (! is_string($expectedImageFingerprint) || ! hash_equals(CatalogAssetAssignments::fingerprint($record), $expectedImageFingerprint))) {
                throw ValidationException::withMessages(['record' => 'This Group changed after the image editor was opened. Review the saved record.']);
            }
            if ($resolveImageChange !== null) {
                $imageChange = $resolveImageChange($record);
                if (! $imageChange instanceof CatalogAssetChange) {
                    throw ValidationException::withMessages(['image' => 'The prepared image change is invalid.']);
                }
            }
            $validated = Validator::make(['group' => $data], [
                'group' => ['array:name,description,parent_id,sort_order,configurator_id'],
                'group.name' => ['required', 'string', 'max:255'],
                'group.description' => ['nullable', 'string', 'max:5000'],
                'group.parent_id' => ['nullable', 'integer', 'min:1'],
                'group.sort_order' => ['sometimes', 'integer'],
                'group.configurator_id' => ['nullable', 'integer', 'exists:configurators,id'],
            ])->validate()['group'];
            $oldParentId = $record->parent_id;
            $record->fill($validated);
            $this->integrity->validateGroup($record, $groups);
            if ($imageChange !== null) {
                app(CatalogAssetAssignments::class)->applyToLockedRecord($record, $imageChange);
            }
            $changed = $record->isDirty();
            $record->save();
            if ($changed) {
                $this->revisions->advance([$record->id, $oldParentId, $record->parent_id], descendants: true);
            }

            return $record;
        }, attempts: 3);
    }
}
