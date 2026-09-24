<?php

namespace App\Actions;

use App\Models\Group;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogImportParser;
use App\Services\CatalogIntegrity;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ImportCatalogProducts
{
    public function __construct(private CatalogImportParser $parser, private CatalogIntegrity $integrity) {}

    public static function lockName(): string
    {
        return 'catalog-import:'.hash('sha256', DB::connection()->getDatabaseName());
    }

    /**
     * @param  array<string, array{parent_id?: int, parent_legacy_id?: string, parent_name?: string}>  $parents
     * @return array<string, mixed>
     */
    public function handle(User $actor, string $path, bool $apply = false, ?string $sourceHash = null, ?string $mapHash = null, array $parents = [], ?string $parentsHash = null): array
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        $batch = $this->parser->parse($path);
        $parents = $this->normalizeParents($parents);
        $batch['parents_hash'] = hash('sha256', json_encode($parents, JSON_THROW_ON_ERROR));
        if ($apply && ($sourceHash === null || $mapHash === null
            || ! hash_equals($batch['source_hash'], $sourceHash) || ! hash_equals($batch['map_hash'], $mapHash))) {
            throw ValidationException::withMessages(['source' => 'Apply requires the exact reviewed source and map SHA-256 hashes. Run a new dry-run.']);
        }
        if ($apply && ($parentsHash === null || ! hash_equals($batch['parents_hash'], $parentsHash))) {
            throw ValidationException::withMessages(['parents' => 'Apply requires the exact reviewed parent metadata SHA-256 hash. Run a new dry-run.']);
        }
        $lock = Cache::lock(self::lockName(), 300);
        if (! $lock->get()) {
            throw new LockTimeoutException('Another catalog import is in progress.');
        }
        try {
            return DB::transaction(fn (): array => $this->process($batch, $parents, $apply), attempts: 3);
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  array<string, mixed>  $batch
     * @param  array<string, array<string, mixed>>  $parents
     * @return array<string, mixed>
     */
    private function process(array $batch, array $parents, bool $apply): array
    {
        $groups = $this->integrity->lockGroups();
        [$nodes, $sourceNodes] = $this->prepareGroups($groups, $batch['groups'], $parents);
        $identities = array_column(array_column($batch['rows'], 'data'), 'legacy_id');
        $codes = array_column(array_column($batch['rows'], 'data'), 'product_code');
        $products = Product::query()->where(function ($query) use ($identities, $codes, $batch): void {
            $query->whereIn('legacy_id', $identities)->orWhereIn('product_code', $codes)
                ->orWhereIn('legacy_group_id', array_keys($batch['groups']));
        })->orderBy('id')->lockForUpdate()->get();
        $byIdentity = $products->keyBy(fn (Product $product): string => 'id:'.$product->legacy_id);
        $byCode = $products->groupBy(fn (Product $product): string => 'code:'.$product->product_code);
        $planned = [];
        foreach ($batch['rows'] as $row) {
            $data = $row['data'];
            foreach ($byCode->get('code:'.$data['product_code'], collect()) as $candidate) {
                if ($candidate->legacy_id !== $data['legacy_id']) {
                    throw ValidationException::withMessages(['source' => 'Product code conflicts with another legacy identity at row '.$row['row'].'.']);
                }
            }
            $product = $byIdentity->get('id:'.$data['legacy_id']);
            if ($product !== null && $groups->get($product->group_id)?->legacy_id !== $product->legacy_group_id) {
                throw ValidationException::withMessages(['source' => 'Existing Product relationship conflicts with its recorded Group identity at row '.$row['row'].'.']);
            }
            $product = $product === null ? new Product : clone $product;
            foreach (['properties', 'parts', 'extra_data'] as $bucket) {
                $data[$bucket] = array_replace($product->{$bucket} ?? [], $data[$bucket]);
            }
            $product->fill($data);
            $planned[] = ['row' => $row['row'], 'product' => $product, 'group_key' => $sourceNodes[$data['legacy_group_id']]];
        }
        $totals = ['groups_created' => 0, 'groups_updated' => 0, 'created' => 0, 'updated' => 0, 'unchanged' => 0, 'absent' => 0];
        $remaining = $nodes;
        $resolved = [];
        while ($remaining !== []) {
            foreach ($remaining as $key => $node) {
                if ($node['parent'] !== null && ! array_key_exists($node['parent'], $resolved)) {
                    continue;
                }
                $group = $node['model'];
                $group->parent_id = $node['parent'] === null ? null : $resolved[$node['parent']];
                if (! $group->exists) {
                    $totals['groups_created']++;
                } elseif ($group->isDirty()) {
                    $totals['groups_updated']++;
                }
                if ($apply && (! $group->exists || $group->isDirty())) {
                    $group->save();
                }
                $resolved[$key] = $group->id;
                unset($remaining[$key]);
            }
        }
        $details = [];
        foreach ($planned as $item) {
            $product = $item['product'];
            $product->group_id = $resolved[$item['group_key']];
            $outcome = ! $product->exists ? 'created' : ($product->isDirty() ? 'updated' : 'unchanged');
            $fields = array_keys($product->getDirty());
            $totals[$outcome]++;
            if ($apply && $outcome !== 'unchanged') {
                $product->save();
            }
            if (count($details) < 1000) {
                $details[] = ['row' => $item['row'], 'legacy_id' => $product->legacy_id, 'internal_id' => $product->id, 'outcome' => $outcome, 'changed_fields' => $fields];
            }
        }
        $groupIds = array_map('strval', array_keys($batch['groups']));
        $totals['absent'] = $products->filter(fn (Product $product): bool => in_array($product->legacy_group_id, $groupIds, true) && ! in_array($product->legacy_id, $identities, true))->count();

        return ['mode' => $apply ? 'applied' : 'dry-run', 'format' => $batch['format'], 'source_hash' => $batch['source_hash'], 'map_hash' => $batch['map_hash'], 'parents_hash' => $batch['parents_hash'], 'parents' => $parents, 'totals' => $totals, 'rows' => $details, 'details_truncated' => count($planned) > 1000];
    }

    /**
     * @param  array<string, array<string, mixed>>  $parents
     * @return array<string, array{parent_id?: int, parent_legacy_id?: string, parent_name?: string}>
     */
    private function normalizeParents(array $parents): array
    {
        $normalized = [];
        foreach ($parents as $legacyId => $metadata) {
            $validated = Validator::make(['parent' => $metadata], [
                'parent' => ['required', 'array:parent_id,parent_legacy_id,parent_name'],
                'parent.parent_id' => ['required_without:parent.parent_legacy_id', 'integer', 'min:1', 'prohibits:parent.parent_legacy_id,parent.parent_name'],
                'parent.parent_legacy_id' => ['required_without:parent.parent_id', 'string', 'max:64'],
                'parent.parent_name' => ['required_with:parent.parent_legacy_id', 'string', 'max:255'],
            ])->validate()['parent'];
            $normalized[$legacyId] = isset($validated['parent_id'])
                ? ['parent_id' => (int) $validated['parent_id']]
                : ['parent_legacy_id' => $validated['parent_legacy_id'], 'parent_name' => $validated['parent_name']];
        }
        ksort($normalized, SORT_STRING);

        return $normalized;
    }

    /**
     * @param  Collection<int, Group>  $groups
     * @param  array<string, string>  $imported
     * @param  array<string, array<string, mixed>>  $parents
     * @return array{array<string, array{model: Group, parent: ?string}>, array<string, string>}
     */
    private function prepareGroups(Collection $groups, array $imported, array $parents): array
    {
        $nodes = [];
        $legacyNodes = [];
        $requestedNames = [];
        foreach ($groups as $group) {
            $key = 'group:'.$group->id;
            $nodes[$key] = ['model' => clone $group, 'parent' => $group->parent_id === null ? null : 'group:'.$group->parent_id];
            if ($group->legacy_id !== null) {
                $legacyNodes[$group->legacy_id] = $key;
            }
        }
        $resolve = function (string $legacyId, string $name) use (&$nodes, &$legacyNodes, &$requestedNames): string {
            if (isset($requestedNames[$legacyId]) && $requestedNames[$legacyId] !== $name) {
                throw ValidationException::withMessages(['parents' => 'Conflicting Group names in parent metadata.']);
            }
            $requestedNames[$legacyId] = $name;
            $key = $legacyNodes[$legacyId] ??= 'new:'.$legacyId;
            $nodes[$key] ??= ['model' => new Group(['legacy_id' => $legacyId]), 'parent' => null];
            $nodes[$key]['model']->name = $name;

            return $key;
        };
        $sourceNodes = [];
        foreach ($imported as $legacyId => $name) {
            $sourceNodes[$legacyId] = $resolve((string) $legacyId, $name);
        }
        foreach ($parents as $legacyId => $metadata) {
            if (! isset($sourceNodes[$legacyId]) || ! is_array($metadata)) {
                throw ValidationException::withMessages(['parents' => 'Parent metadata must name a Group represented in this CSV.']);
            }
            $parent = isset($metadata['parent_id']) ? 'group:'.$metadata['parent_id'] : $resolve($metadata['parent_legacy_id'], $metadata['parent_name']);
            if (! isset($nodes[$parent])) {
                throw ValidationException::withMessages(['parents' => 'The supplied parent Group does not exist.']);
            }
            $nodes[$sourceNodes[$legacyId]]['parent'] = $parent;
        }
        $productGroupIds = Product::query()->distinct()->pluck('group_id')->all();
        foreach ($nodes as $key => $node) {
            $seen = [$key => true];
            $parentKey = $node['parent'];
            while ($parentKey !== null) {
                if (isset($seen[$parentKey]) || ! isset($nodes[$parentKey])) {
                    throw ValidationException::withMessages(['parents' => 'The proposed Group hierarchy contains a cycle or missing parent.']);
                }
                $seen[$parentKey] = true;
                $parent = $nodes[$parentKey]['model'];
                if (in_array($parentKey, $sourceNodes, true) || $parent->configurator_id !== null || in_array($parent->id, $productGroupIds, true)) {
                    throw ValidationException::withMessages(['parents' => 'Products and configurator assignments require a leaf Group.']);
                }
                $parentKey = $nodes[$parentKey]['parent'];
            }
        }

        return [$nodes, $sourceNodes];
    }
}
