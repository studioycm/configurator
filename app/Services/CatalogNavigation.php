<?php

namespace App\Services;

use App\Models\Group;
use App\Models\GroupTagValue;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CatalogNavigation
{
    private const CARD_PAGE_SIZE = 24;

    private const TREE_PAGE_SIZE = 50;

    public function __construct(private CatalogAvailability $availability, private CatalogPresentation $presentation) {}

    /** @param array<string, mixed> $query @return array<string, mixed> */
    public function get(User $actor, array $query): array
    {
        $query = $this->validateQuery($query);

        return DB::transaction(function () use ($actor, $query): array {
            $this->authorizeRead($actor);
            $shared = $this->presentation->current();
            $query['mode'] ??= $shared['settings']['sidebar_mode'];
            $graph = $this->graph();
            $groups = $graph['groups'];
            $branchId = $query['branchId'];
            if ($branchId !== null) {
                abort_unless($groups->has($branchId), 404);
            }
            $branchIds = $branchId === null ? array_keys($graph['models']) : $this->subtree($branchId, $graph['children']);
            $baseIds = array_values(array_intersect(array_keys($graph['models']), $branchIds));
            $selectedCategories = $this->selectedCategories($query['tagValueIds']);
            $matchingTags = $this->matchingTagGroups($baseIds, $selectedCategories);
            $resultIds = array_values(array_filter($baseIds, function (int $id) use ($groups, $query, $matchingTags): bool {
                $group = $groups[$id];

                return ($matchingTags === null || isset($matchingTags[$id]))
                    && ($query['search'] === '' || mb_stripos($group->name, $query['search']) !== false
                        || mb_stripos((string) $group->legacy_id, $query['search']) !== false);
            }));
            $this->sortIds($resultIds, $groups);
            $total = count($resultIds);
            $page = $query['page'];
            $slice = $page > max(1, (int) ceil($total / self::CARD_PAGE_SIZE)) ? [] : array_slice($resultIds, ($page - 1) * self::CARD_PAGE_SIZE, self::CARD_PAGE_SIZE);
            $cardGroups = $slice === [] ? collect() : Group::query()->whereKey($slice)->get(['id', 'catalog_revision', 'result_settings'])->keyBy('id');
            $categoryIds = $this->presentation->resolveCategoryIds($shared['settings'], $cardGroups);
            $cardTags = $this->cardTags($slice, $categoryIds);
            $cards = array_map(function (int $id) use ($groups, $cardGroups, $shared, $categoryIds, $cardTags): array {
                $group = $groups[$id];

                $effective = $this->presentation->effective($cardGroups[$id], $shared['settings'], $categoryIds);
                $tags = [];
                foreach ($effective['group_card_tag_categories'] as $categoryId) {
                    $tags = [...$tags, ...($cardTags[$id][$categoryId] ?? [])];
                }

                return [...$this->identity($group), 'url' => route('catalog.groups.show', $id), 'presentation' => $effective, 'tags' => $tags, 'legacyId' => $group->legacy_id, 'description' => $group->description,
                    'productCount' => (int) $group->active_product_count, 'mainImage' => null];
            }, $slice);
            $ancestors = [];
            $parentId = $branchId === null ? null : $groups[$branchId]->parent_id;
            while ($parentId !== null) {
                $ancestors[] = $this->identity($groups[$parentId]);
                $parentId = $groups[$parentId]->parent_id;
            }
            $identity = $query;
            unset($identity['requestId']);

            $moreRoots = count($graph['children'][0] ?? []) > self::TREE_PAGE_SIZE;

            return ['query' => $identity, 'presentation' => $shared['settings'], 'presentationRecovery' => $shared['recovery'],
                'rootTreePagination' => ['page' => 1, 'nextPage' => $moreRoots ? 2 : null, 'hasMore' => $moreRoots],
                'requestId' => $query['requestId'], 'queryIdentity' => hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR)),
                'displayVersion' => $shared['version'], 'branch' => $branchId === null ? null : $this->identity($groups[$branchId]),
                'ancestors' => array_reverse($ancestors), 'treeRows' => $this->treeRows(array_slice($graph['children'][0] ?? [], 0, self::TREE_PAGE_SIZE), $graph),
                'treeVersion' => $graph['version'], 'tags' => $this->facets($baseIds, $query['tagValueIds']), 'cards' => $cards,
                'total' => $total, 'page' => $page, 'hasMore' => $page < (int) ceil($total / self::CARD_PAGE_SIZE)];
        }, attempts: 1);
    }

    /** @return array<string, mixed> */
    public function children(User $actor, ?int $parentId, int $page, string $expectedTreeVersion, string $requestId): array
    {
        if (($parentId !== null && $parentId < 1) || $page < 1 || preg_match('/^[a-f0-9]{64}$/D', $expectedTreeVersion) !== 1 || mb_strlen($requestId) > 100) {
            throw ValidationException::withMessages(['tree' => 'Choose a current tree version, parent and positive page.']);
        }

        return DB::transaction(function () use ($actor, $parentId, $page, $expectedTreeVersion, $requestId): array {
            $this->authorizeRead($actor);
            $graph = $this->graph();
            if ($parentId !== null) {
                abort_unless($graph['groups']->has($parentId), 404);
            }
            $result = ['requestId' => $requestId, 'parentId' => $parentId, 'page' => $page, 'treeVersion' => $graph['version'],
                'status' => 'ready', 'rows' => [], 'nextPage' => null, 'hasMore' => false];
            if (! hash_equals($graph['version'], $expectedTreeVersion)) {
                return [...$result, 'status' => 'refresh_required'];
            }
            $ids = $graph['children'][$parentId ?? 0] ?? [];
            if ($page > max(1, (int) ceil(count($ids) / self::TREE_PAGE_SIZE))) {
                return $result;
            }
            $slice = array_slice($ids, ($page - 1) * self::TREE_PAGE_SIZE, self::TREE_PAGE_SIZE);
            $hasMore = $page < (int) ceil(count($ids) / self::TREE_PAGE_SIZE);

            return [...$result, 'rows' => $this->treeRows($slice, $graph), 'hasMore' => $hasMore, 'nextPage' => $hasMore ? $page + 1 : null];
        }, attempts: 1);
    }

    private function authorizeRead(User $actor): void
    {
        abort_unless($actor->exists && User::query()->whereKey($actor->id)->exists(), 401);
    }

    /** @return array{groups:Collection<int,Group>,children:array<int,list<int>>,models:array<int,bool>,counts:array<int,int>,physical:array<int,bool>,version:string} */
    private function graph(): array
    {
        $visible = $this->availability->visibleGroups()->keyBy('id');
        $groups = Group::query()->whereKey($visible->modelKeys())->orderBy('id')
            ->select(['id', 'parent_id', 'name', 'legacy_id', 'description', 'sort_order'])
            ->withCount(['products as active_product_count' => fn (Builder $query): Builder => $query->where('is_active', true)])->get()->keyBy('id');
        $children = [];
        $models = [];
        $counts = [];
        $physical = [];
        $versionRows = [];
        foreach ($groups as $group) {
            $id = (int) $group->id;
            $children[$group->parent_id ?? 0][] = $id;
            $physical[$id] = (bool) $visible[$id]->children_exists;
            if (! $physical[$id] && $group->active_product_count > 0) {
                $models[$id] = true;
            }
            $counts[$id] = isset($models[$id]) ? 1 : 0;
            $versionRows[] = [$id, $group->parent_id, $group->name, $group->sort_order, $physical[$id]];
        }
        foreach ($children as &$ids) {
            $this->sortIds($ids, $groups);
        }
        unset($ids);
        $order = $children[0] ?? [];
        for ($i = 0; $i < count($order); $i++) {
            foreach ($children[$order[$i]] ?? [] as $id) {
                $order[] = $id;
            }
        }
        foreach (array_reverse($order) as $id) {
            $parentId = $groups[$id]->parent_id;
            if ($parentId !== null) {
                $counts[$parentId] += $counts[$id];
            }
        }

        return ['groups' => $groups, 'children' => $children, 'models' => $models, 'counts' => $counts, 'physical' => $physical,
            'version' => hash('sha256', json_encode(['groups' => $versionRows, 'models' => array_keys($models)], JSON_THROW_ON_ERROR))];
    }

    /** @param list<int> $ids @param Collection<int,Group> $groups */
    private function sortIds(array &$ids, Collection $groups): void
    {
        usort($ids, fn (int $left, int $right): int => [$groups[$left]->sort_order, $left] <=> [$groups[$right]->sort_order, $right]);
    }

    /** @param array<int,list<int>> $children @return list<int> */
    private function subtree(int $id, array $children): array
    {
        $ids = [$id];
        for ($i = 0; $i < count($ids); $i++) {
            foreach ($children[$ids[$i]] ?? [] as $child) {
                $ids[] = $child;
            }
        }

        return $ids;
    }

    /** @return array{id:int,name:string,parentId:int|null} */
    private function identity(Group $group): array
    {
        return ['id' => (int) $group->id, 'name' => $group->name, 'parentId' => $group->parent_id];
    }

    /** @param list<int> $ids @param array<string,mixed> $graph @return list<array<string,mixed>> */
    private function treeRows(array $ids, array $graph): array
    {
        return array_map(fn (int $id): array => [...$this->identity($graph['groups'][$id]), 'hasChildren' => $graph['physical'][$id], 'modelCount' => $graph['counts'][$id]], $ids);
    }

    /** @param list<int> $groupIds @param list<int> $categoryIds @return array<int,array<int,list<array<string,mixed>>>> */
    private function cardTags(array $groupIds, array $categoryIds): array
    {
        if ($groupIds === [] || $categoryIds === []) {
            return [];
        }
        $rows = DB::table('group_tag_value as p')->join('group_tag_values as v', 'v.id', '=', 'p.group_tag_value_id')
            ->join('group_tag_categories as c', 'c.id', '=', 'v.group_tag_category_id')
            ->whereIn('p.group_id', $groupIds)->whereIn('c.id', $categoryIds)
            ->orderBy('p.position')->orderBy('v.id')->get(['p.group_id', 'v.id', 'v.label', 'c.id as category_id', 'c.label as category_label']);
        $tags = [];
        foreach ($rows as $row) {
            $tags[(int) $row->group_id][(int) $row->category_id][] = ['id' => (int) $row->id, 'label' => (string) $row->label,
                'categoryId' => (int) $row->category_id, 'categoryLabel' => (string) $row->category_label];
        }

        return $tags;
    }

    /** @param list<int> $ids @return array<int,int> */
    private function selectedCategories(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $categories = GroupTagValue::query()->whereKey($ids)->pluck('group_tag_category_id', 'id')->all();
        if (count($categories) !== count($ids)) {
            throw ValidationException::withMessages(['tagValueIds' => 'A selected tag value no longer exists.']);
        }

        return array_map(intval(...), $categories);
    }

    /** @param list<int> $groupIds @param array<int,int> $selected @return array<int,bool>|null */
    private function matchingTagGroups(array $groupIds, array $selected): ?array
    {
        if ($selected === []) {
            return null;
        }
        $hits = [];
        foreach (DB::table('group_tag_value')->whereIn('group_id', $groupIds)->whereIn('group_tag_value_id', array_keys($selected))->get(['group_id', 'group_tag_value_id']) as $row) {
            $hits[(int) $row->group_id][$selected[$row->group_tag_value_id]] = true;
        }
        $required = count(array_unique($selected));
        $matching = [];
        foreach ($hits as $groupId => $categories) {
            if (count($categories) === $required) {
                $matching[$groupId] = true;
            }
        }

        return $matching;
    }

    /** @param list<int> $groupIds @param list<int> $selectedIds @return list<array<string,mixed>> */
    private function facets(array $groupIds, array $selectedIds): array
    {
        $columns = ['c.id as category_id', 'c.label as category_label', 'c.sort_order as category_order', 'v.id', 'v.label', 'v.sort_order'];
        $rows = DB::table('group_tag_value as p')->join('group_tag_values as v', 'v.id', '=', 'p.group_tag_value_id')
            ->join('group_tag_categories as c', 'c.id', '=', 'v.group_tag_category_id')->whereIn('p.group_id', $groupIds)
            ->select($columns)->selectRaw('COUNT(*) as model_count')->groupBy('c.id', 'c.label', 'c.sort_order', 'v.id', 'v.label', 'v.sort_order')
            ->orderBy('c.sort_order')->orderBy('c.id')->orderBy('v.sort_order')->orderBy('v.id')->get();
        $categories = [];
        foreach ($rows as $row) {
            $categoryId = (int) $row->category_id;
            $categories[$categoryId] ??= ['id' => $categoryId, 'label' => $row->category_label, 'values' => []];
            $categories[$categoryId]['values'][] = ['id' => (int) $row->id, 'label' => $row->label,
                'count' => (int) $row->model_count, 'selected' => in_array((int) $row->id, $selectedIds, true)];
        }

        return array_values($categories);
    }

    /** @param array<string,mixed> $query @return array{branchId:int|null,search:string,tagValueIds:list<int>,mode:?string,page:int,requestId:string} */
    private function validateQuery(array $query): array
    {
        if ((array_key_exists('mode', $query) && $query['mode'] === null) || array_diff(array_keys($query), ['branchId', 'search', 'tagValueIds', 'mode', 'page', 'requestId']) !== []) {
            throw ValidationException::withMessages(['query' => 'Unsupported catalog navigation input.']);
        }
        $query = array_replace(['branchId' => null, 'search' => '', 'mode' => null, 'requestId' => '', 'tagValueIds' => [], 'page' => 1], $query);
        $search = $query['search'];
        $mode = $query['mode'];
        $requestId = $query['requestId'];
        $ids = $query['tagValueIds'];
        if (! is_string($search) || ! mb_check_encoding($search, 'UTF-8') || mb_strlen($search) > 500 || ($mode !== null && ! in_array($mode, ['tree', 'tags'], true))
            || ! is_string($requestId) || ! mb_check_encoding($requestId, 'UTF-8') || mb_strlen($requestId) > 100 || ! is_array($ids) || ! array_is_list($ids) || count($ids) > 200) {
            throw ValidationException::withMessages(['query' => 'Invalid catalog search, tags, mode or request identity.']);
        }
        $ids = array_map(fn (mixed $id): int => $this->positiveId($id, 'tagValueIds'), $ids);
        if (count(array_unique($ids)) !== count($ids)) {
            throw ValidationException::withMessages(['tagValueIds' => 'Choose distinct tag values.']);
        }
        sort($ids, SORT_NUMERIC);

        return ['branchId' => ! array_key_exists('branchId', $query) || $query['branchId'] === null ? null : $this->positiveId($query['branchId'], 'branchId'),
            'search' => $search, 'tagValueIds' => $ids, 'mode' => $mode, 'page' => $this->positiveId($query['page'], 'page'), 'requestId' => $requestId];
    }

    private function positiveId(mixed $value, string $field): int
    {
        if ((! is_int($value) && ! is_string($value)) || preg_match('/^[1-9][0-9]*$/D', (string) $value) !== 1 || (string) (int) $value !== (string) $value) {
            throw ValidationException::withMessages([$field => 'Use a canonical positive integer.']);
        }

        return (int) $value;
    }
}
