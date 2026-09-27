<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\DB;
use LogicException;

class CatalogRevisions
{
    private bool $collecting = false;

    /** @var list<int|string|null> */
    private array $pending = [];

    private bool $includeDescendants = false;

    /** Coalesce nested aggregate actions inside the caller's write transaction. */
    public function batch(Closure $operation): mixed
    {
        if ($this->collecting) {
            return $operation();
        }
        $this->collecting = true;
        try {
            $result = $operation();
            $this->collecting = false;
            $this->advance($this->pending, $this->includeDescendants);

            return $result;
        } finally {
            $this->collecting = false;
            $this->pending = [];
            $this->includeDescendants = false;
        }
    }

    /** @param list<int|string|null> $groupIds */
    public function advance(array $groupIds, bool $descendants = false): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Catalog revisions must advance in the writer transaction.');
        }
        if ($this->collecting) {
            $this->pending = [...$this->pending, ...$groupIds];
            $this->includeDescendants = $this->includeDescendants || $descendants;

            return;
        }
        $ids = array_values(array_unique(array_filter($groupIds, fn ($id): bool => $id !== null)));
        if ($descendants && $ids !== []) {
            $groups = DB::table('groups')->get(['id', 'parent_id']);
            do {
                $before = count($ids);
                foreach ($groups as $group) {
                    if (in_array($group->parent_id, $ids) && ! in_array($group->id, $ids)) {
                        $ids[] = $group->id;
                    }
                }
            } while (count($ids) !== $before);
        }
        if ($ids !== []) {
            DB::table('groups')->whereIn('id', $ids)->increment('catalog_revision');
        }
    }
}
