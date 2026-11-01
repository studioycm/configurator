<?php

namespace App\Actions;

use App\DTO\CatalogAssetChange;
use App\Models\Group;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogAssetAssignments;
use App\Services\CatalogAssetTargets;
use App\Services\CatalogIntegrity;
use App\Services\CatalogRevisions;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class SaveCatalogMainImage
{
    public function __construct(private CatalogAssetTargets $targets, private CatalogIntegrity $integrity, private CatalogAssetAssignments $assignments, private CatalogRevisions $revisions) {}

    /** @param array{list_key?: string, parent_id?: int} $ownerContext */
    public function handle(User $actor, string $targetKey, int $recordId, array $ownerContext, string $expectedFingerprint, ?CatalogAssetChange $change, ?Closure $resolveImageChange = null): Model
    {
        abort_unless(config('catalog-assets.enabled'), 404);
        if (($change !== null && $resolveImageChange !== null) || ($change === null && $resolveImageChange === null)) {
            throw ValidationException::withMessages(['image' => 'Choose one prepared main-image change source.']);
        }
        $actor = User::query()->find($actor->getKey());
        abort_unless($actor !== null, 403);
        $this->targets->eligibleQuery($actor, $targetKey, $ownerContext);
        $model = $this->targets->profile($targetKey)['model'];

        return (new $model)->getConnection()->transaction(function () use ($actor, $targetKey, $recordId, $ownerContext, $expectedFingerprint, $change, $model, $resolveImageChange): Model {
            $groups = $this->integrity->lockGroups();
            $actor = User::query()->find($actor->getKey());
            abort_unless($actor !== null, 403);
            $record = $model === Group::class ? $groups->get($recordId) : Product::query()->whereKey($recordId)->lockForUpdate()->first();
            if ($record === null || ($record instanceof Product && ! $groups->has($record->group_id))
                || ! $this->targets->eligibleQuery($actor, $targetKey, $ownerContext)->whereKey($recordId)->exists()) {
                throw ValidationException::withMessages(['image.context' => 'This image owner is no longer available in this context.']);
            }
            if (! hash_equals(CatalogAssetAssignments::fingerprint($record), $expectedFingerprint)) {
                throw ValidationException::withMessages(['record' => 'This record changed after the image editor was opened. Review its current image before saving.']);
            }
            if ($resolveImageChange !== null) {
                $change = $resolveImageChange($record);
                if (! $change instanceof CatalogAssetChange) {
                    throw ValidationException::withMessages(['image' => 'The prepared image change is invalid.']);
                }
            }
            if ($this->assignments->applyToLockedRecord($record, $change)) {
                $record->save();
                $this->revisions->advance($record instanceof Product ? [$record->group_id] : [$record->id, $record->parent_id], descendants: $record instanceof Group);
            }

            return $record;
        }, attempts: 3);
    }
}
