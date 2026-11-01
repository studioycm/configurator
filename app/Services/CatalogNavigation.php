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

    public function __construct(private CatalogAvailability $availability, private CatalogPresentation $presentation, private GroupAttributes $attributes) {}

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
            $this->sortIds($baseIds, $groups);
            $attributeDocuments = $this->attributeDocuments($baseIds);
            $selectedCategories = $this->selectedCategories($query['tagValueIds']);
            $matchingTags = $this->matchingTagGroups($baseIds, $selectedCategories);
            $resultIds = array_values(array_filter($baseIds, function (int $id) use ($groups, $query, $matchingTags, $attributeDocuments): bool {
                $group = $groups[$id];

                return ($matchingTags === null || isset($matchingTags[$id]))
                    && $this->matchesAttributes($attributeDocuments[$id]['values'], $query['attributeValues'])
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
            $cards = array_map(function (int $id) use ($groups, $cardGroups, $shared, $categoryIds, $cardTags, $attributeDocuments): array {
                $group = $groups[$id];

                $effective = $this->presentation->effective($cardGroups[$id], $shared['settings'], $categoryIds);
                $tags = [];
                foreach ($effective['group_card_tag_categories'] as $categoryId) {
                    $tags = [...$tags, ...($cardTags[$id][$categoryId] ?? [])];
                }
                $attributes = [];
                foreach ($effective['group_card_attribute_keys'] as $key) {
                    $values = $attributeDocuments[$id]['values'][$key] ?? [];
                    if ($values !== []) {
                        $attributes[] = ['key' => $key, 'label' => $this->attributes->definitions()[$key]['label'], 'values' => $values];
                    }
                }

                return [...$this->identity($group), 'url' => route('catalog.groups.show', $id), 'presentation' => $effective, 'tags' => $tags, 'legacyId' => $group->legacy_id, 'description' => $group->description,
                    'productCount' => (int) $group->active_product_count, 'mainImage' => null,
                    'ancestors' => $this->ancestors($group, $groups), 'attributesStatus' => $attributeDocuments[$id]['status'], 'attributes' => $attributes];
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
                'attributeDefinitions' => array_map(fn (string $key, array $definition): array => ['key' => $key, 'label' => $definition['label']], array_keys($this->attributes->definitions()), array_values($this->attributes->definitions())),
                'attributes' => $this->attributeFacets($attributeDocuments, $query['attributeValues']),
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

    /** @param Collection<int,Group> $groups @return list<array<string,mixed>> */
    private function ancestors(Group $group, Collection $groups): array
    {
        $trail = [];
        while ($group->parent_id !== null) {
            $group = $groups[$group->parent_id];
            $trail[] = $this->identity($group);
        }

        return array_reverse($trail);
    }

    /** @param list<int> $ids @return array<int,array{status:string,values:array<string,list<string>>}> */
    private function attributeDocuments(array $ids): array
    {
        $documents = [];
        foreach (Group::query()->whereKey($ids)->get(['id', 'catalog_attributes']) as $group) {
            try {
                $values = array_intersect_key($this->attributes->document($group), $this->attributes->definitions());
                $values = array_filter($values, fn (array $values): bool => $values !== []);
                $documents[$group->id] = ['status' => $values === [] ? 'missing' : 'ready', 'values' => $values];
            } catch (ValidationException) {
                $documents[$group->id] = ['status' => 'unavailable', 'values' => []];
            }
        }
        $ordered = [];
        foreach ($ids as $id) {
            $ordered[$id] = $documents[$id];
        }

        return $ordered;
    }

    /** @param array<string,list<string>> $values @param array<string,list<string>> $selected */
    private function matchesAttributes(array $values, array $selected): bool
    {
        foreach ($selected as $key => $choices) {
            $matched = false;
            foreach ($choices as $choice) {
                $matched = $matched || in_array($choice, $values[$key] ?? [], true);
            }
            if (! $matched) {
                return false;
            }
        }

        return true;
    }

    /** @param array<int,array{status:string,values:array<string,list<string>>}> $documents @param array<string,list<string>> $selected @return list<array<string,mixed>> */
    private function attributeFacets(array $documents, array $selected): array
    {
        $facets = [];
        foreach ($this->attributes->definitions() as $key => $definition) {
            $values = [];
            foreach ($documents as $document) {
                foreach ($document['values'][$key] ?? [] as $value) {
                    $token = json_encode($value, JSON_THROW_ON_ERROR);
                    $values[$token] ??= ['value' => $value, 'count' => 0, 'selected' => in_array($value, $selected[$key] ?? [], true), 'available' => true];
                    $values[$token]['count']++;
                }
            }
            foreach ($selected[$key] ?? [] as $value) {
                $values[json_encode($value, JSON_THROW_ON_ERROR)] ??= ['value' => $value, 'count' => 0, 'selected' => true, 'available' => false];
            }
            if ($values !== []) {
                $facets[] = ['key' => $key, 'label' => $definition['label'], 'values' => array_values($values)];
            }
        }

        return $facets;
    }

    /** @param array<string,mixed> $normalizedQuery @return array<string,mixed> */
    public function queryForClient(array $normalizedQuery): array
    {
        $normalizedQuery['attributeValues'] = (object) $normalizedQuery['attributeValues'];

        return $normalizedQuery;
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

    /** @param array<string,mixed> $query @return array<string,mixed> */
    public function validateQuery(array $query): array
    {
        if ((array_key_exists('mode', $query) && $query['mode'] === null) || array_diff(array_keys($query), ['branchId', 'search', 'tagValueIds', 'attributeValues', 'mode', 'page', 'requestId']) !== []) {
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

        $attributes = array_key_exists('attributeValues', $query) ? $query['attributeValues'] : [];
        $invalidAttributes = fn (): ValidationException => ValidationException::withMessages(['attributeValues' => 'Choose registered attributes with distinct exact text values.']);
        if (! is_array($attributes) || ($attributes !== [] && array_is_list($attributes)) || array_diff_key($attributes, $this->attributes->definitions()) !== []) {
            throw $invalidAttributes();
        }
        foreach ($attributes as $key => $values) {
            if (! is_array($values) || ! array_is_list($values) || count($values) > config('group_attributes.limits.values_per_attribute')) {
                throw $invalidAttributes();
            }
            foreach ($values as $value) {
                if (! is_string($value) || $value === '' || ! mb_check_encoding($value, 'UTF-8') || str_contains($value, "\0") || mb_strlen($value) > config('group_attributes.limits.value_characters')) {
                    throw $invalidAttributes();
                }
            }
            if (count(array_unique($values, SORT_STRING)) !== count($values)) {
                throw $invalidAttributes();
            }
        }
        if (strlen(json_encode((object) $attributes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS)) > config('group_attributes.limits.document_bytes')) {
            throw $invalidAttributes();
        }
        $attributes = array_filter($attributes, fn (array $values): bool => $values !== []);
        ksort($attributes, SORT_STRING);

        return ['branchId' => ! array_key_exists('branchId', $query) || $query['branchId'] === null ? null : $this->positiveId($query['branchId'], 'branchId'),
            'search' => $search, 'tagValueIds' => $ids, 'attributeValues' => $attributes, 'mode' => $mode, 'page' => $this->positiveId($query['page'], 'page'), 'requestId' => $requestId];
    }

    /** @return array<string,mixed> */
    public function queryFromUrl(mixed $state, ?int $branchOverride = null, ?string $rawQuery = null): array
    {
        if ($rawQuery !== null) {
            $state = $this->rawUrlState($rawQuery);
        }
        if (! is_array($state) || ($state !== [] && (! in_array($state['version'] ?? null, [1, '1', 2, '2'], true)
            || array_diff(array_keys($state), ['version', 'branch', 'q', 'tags', 'attributes', 'mode', 'page']) !== []
            || (in_array($state['version'], [1, '1'], true) && array_key_exists('attributes', $state))))) {
            throw ValidationException::withMessages(['g' => 'This catalog link is invalid or out of date.']);
        }
        $query = [];
        foreach (['branch' => 'branchId', 'q' => 'search', 'tags' => 'tagValueIds', 'attributes' => 'attributeValues', 'mode' => 'mode', 'page' => 'page'] as $key => $target) {
            if (array_key_exists($key, $state)) {
                $query[$target] = $state[$key];
            }
        }
        if ($branchOverride !== null) {
            $query['branchId'] = $branchOverride;
        }

        return $query;
    }

    /** @return array<string,mixed> */
    private function rawUrlState(string $rawQuery): array
    {
        $invalid = static fn (): ValidationException => ValidationException::withMessages(['g' => 'This catalog link is invalid or out of date.']);
        $state = [];
        $tags = [];
        $tagStyle = null;
        $attributeLists = [];
        $attributeStyles = [];
        foreach (explode('&', $rawQuery) as $part) {
            $pair = explode('=', $part, 2);
            $key = urldecode($pair[0]);
            if ($key !== 'g' && ! str_starts_with($key, 'g[')) {
                continue;
            }
            $rawValue = $pair[1] ?? '';
            $value = urldecode($rawValue);
            if ($key === 'g' || preg_match('/%(?![0-9a-f]{2})/i', $pair[0].$rawValue) === 1 || ! mb_check_encoding($key.$value, 'UTF-8')) {
                throw $invalid();
            }
            if (preg_match('/^g\[attributes\]\[([a-z][a-z0-9_]{0,99})\]\[([0-9]*)\]$/D', $key, $match) === 1) {
                [, $attribute, $index] = $match;
                $style = $index === '' ? 'append' : 'indexed';
                $list = $attributeLists[$attribute] ?? [];
                if ((isset($attributeStyles[$attribute]) && $attributeStyles[$attribute] !== $style) || count($list) >= 25
                    || ($style === 'indexed' && (preg_match('/^(?:0|[1-9][0-9]*)$/D', $index) !== 1 || (int) $index >= 25 || array_key_exists((int) $index, $list)))) {
                    throw $invalid();
                }
                $attributeStyles[$attribute] = $style;
                $list[$style === 'append' ? count($list) : (int) $index] = $value;
                $attributeLists[$attribute] = $list;

                continue;
            }
            if (preg_match('/^g\[tags\]\[([0-9]*)\]$/D', $key, $match) === 1) {
                $index = $match[1];
                $style = $index === '' ? 'append' : 'indexed';
                if (($tagStyle !== null && $style !== $tagStyle) || count($tags) >= 200
                    || ($style === 'indexed' && (preg_match('/^(?:0|[1-9][0-9]*)$/D', $index) !== 1 || (int) $index >= 200 || array_key_exists((int) $index, $tags)))) {
                    throw $invalid();
                }
                $tagStyle = $style;
                if ($style === 'append') {
                    $tags[] = $value;
                } else {
                    $tags[(int) $index] = $value;
                }

                continue;
            }
            $map = ['g[version]' => 'version', 'g[branch]' => 'branch', 'g[q]' => 'q', 'g[mode]' => 'mode', 'g[page]' => 'page'];
            if (! isset($map[$key]) || array_key_exists($map[$key], $state)) {
                throw $invalid();
            }
            $state[$map[$key]] = $value;
        }
        if ($tagStyle !== null) {
            ksort($tags, SORT_NUMERIC);
            if (array_keys($tags) !== range(0, count($tags) - 1)) {
                throw $invalid();
            }
            $state['tags'] = array_values($tags);
        }
        foreach ($attributeLists as $key => $values) {
            ksort($values, SORT_NUMERIC);
            if (array_keys($values) !== range(0, count($values) - 1)) {
                throw $invalid();
            }
            $state['attributes'][$key] = array_values($values);
        }

        return $state;
    }

    private function positiveId(mixed $value, string $field): int
    {
        if ((! is_int($value) && ! is_string($value)) || preg_match('/^[1-9][0-9]*$/D', (string) $value) !== 1 || (string) (int) $value !== (string) $value) {
            throw ValidationException::withMessages([$field => 'Use a canonical positive integer.']);
        }

        return (int) $value;
    }
}
