<?php

namespace App\Services;

use App\DTO\CatalogSnapshot;
use App\Models\Group;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class CatalogSnapshots
{
    public function __construct(private BuildCatalogSnapshot $builder) {}

    public function key(int $groupId): string
    {
        $connection = DB::connection();
        $scope = [app()->environment(), $connection->getConfig('host'), $connection->getConfig('port'), $connection->getDatabaseName()];

        return 'catalog-snapshot:'.hash('sha256', json_encode($scope, JSON_THROW_ON_ERROR)).':'.$groupId;
    }

    public function revision(int $groupId): string
    {
        $group = Group::query()->select(['id', 'catalog_revision'])->withExists('children')->findOrFail($groupId);
        abort_if($group->children_exists, 409, 'This Group is now a branch.');

        return (string) $group->catalog_revision;
    }

    public function cached(int $groupId, string $revision): ?CatalogSnapshot
    {
        $data = Cache::get($this->key($groupId));

        return is_array($data) && ($data['schema'] ?? null) === CatalogSnapshot::SCHEMA && ($data['groupId'] ?? null) === (string) $groupId && ($data['revision'] ?? null) === $revision
            ? new CatalogSnapshot($data) : null;
    }

    /** Call outside read/write transactions; card callers leave their read transaction before a miss. */
    public function get(int $groupId): CatalogSnapshot
    {
        if ($cached = $this->cached($groupId, $this->revision($groupId))) {
            return $cached;
        }
        $lock = Cache::lock($this->key($groupId).':rebuild', 60);
        try {
            return $lock->block(2, function () use ($groupId, $lock): CatalogSnapshot {
                for ($attempt = 0; $attempt < 2; $attempt++) {
                    $revision = $this->revision($groupId);
                    if ($cached = $this->cached($groupId, $revision)) {
                        return $cached;
                    }
                    if (! $lock->isOwnedByCurrentProcess()) {
                        break;
                    }
                    $snapshot = $this->builder->build($groupId);
                    if (! $lock->isOwnedByCurrentProcess()) {
                        break;
                    }
                    if ($this->revision($groupId) !== $snapshot->revision()) {
                        continue;
                    }
                    if (! $lock->isOwnedByCurrentProcess()) {
                        break;
                    }
                    Cache::put($this->key($groupId), $snapshot->data, now()->addDay());

                    return $snapshot;
                }
                throw new ServiceUnavailableHttpException(2, 'The catalog is changing. Please retry.');
            });
        } catch (LockTimeoutException $exception) {
            throw new ServiceUnavailableHttpException(2, 'The catalog is being prepared. Please retry.', $exception);
        }
    }
}
