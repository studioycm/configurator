<?php

namespace App\Actions;

use App\Models\Group;
use App\Models\User;
use App\Services\CatalogIntegrity;
use App\Services\CatalogRevisions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ReorderCatalogGroups
{
    public function __construct(private CatalogIntegrity $integrity, private CatalogRevisions $revisions) {}

    /** @param list<int|string> $order */
    public function handle(User $actor, ?int $parentId, array $order): void
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        if (! array_is_list($order) || array_filter($order, fn (mixed $id): bool => ! is_int($id) && ! is_string($id)) !== []) {
            throw ValidationException::withMessages(['order' => 'Submit the complete unique sibling order.']);
        }
        DB::transaction(function () use ($parentId, $order): void {
            $groups = $this->integrity->lockGroups();
            if ($parentId !== null && ! $groups->has($parentId)) {
                throw ValidationException::withMessages(['order' => 'The selected parent no longer exists.']);
            }
            $siblings = $groups->filter(fn (Group $group): bool => $group->parent_id === $parentId);
            $ids = array_map('strval', $order);
            $expected = $siblings->keys()->map(fn (int $id): string => (string) $id)->all();
            if (count($ids) !== count(array_unique($ids)) || count($ids) !== count($expected) || array_diff($ids, $expected) !== []) {
                throw ValidationException::withMessages(['order' => 'Reorder the complete current sibling list. Refresh it if Groups were added, moved or removed.']);
            }
            $this->saveOrder($siblings, $ids, $parentId);
        }, attempts: 3);
    }

    public function move(User $actor, int $id, int $direction): void
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        if (! in_array($direction, [-1, 1], true)) {
            throw ValidationException::withMessages(['order' => 'Choose move up or move down.']);
        }
        DB::transaction(function () use ($id, $direction): void {
            $groups = $this->integrity->lockGroups();
            $record = $groups->get($id);
            abort_unless($record, 404);
            $siblings = $groups->filter(fn (Group $group): bool => $group->parent_id === $record->parent_id)->sortBy([['sort_order', 'asc'], ['id', 'asc']]);
            $ids = $siblings->keys()->map(fn (int $key): string => (string) $key)->values()->all();
            $index = array_search((string) $id, $ids, true);
            if (isset($ids[$index + $direction])) {
                [$ids[$index], $ids[$index + $direction]] = [$ids[$index + $direction], $ids[$index]];
                $this->saveOrder($siblings, $ids, $record->parent_id);
            }
        }, attempts: 3);
    }

    /** @param Collection<int, Group> $siblings @param list<string> $ids */
    private function saveOrder(Collection $siblings, array $ids, ?int $parentId): void
    {
        $changed = [];
        foreach ($ids as $position => $id) {
            $group = $siblings->get((int) $id);
            if ($group->sort_order !== $position) {
                $group->sort_order = $position;
                $group->save();
                $changed[] = $group->id;
            }
        }
        if ($changed !== []) {
            $this->revisions->advance([...$changed, $parentId], descendants: true);
        }
    }
}
