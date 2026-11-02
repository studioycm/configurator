<?php

namespace App\Services;

use App\Actions\ReviewCatalogAssets;
use App\Models\CatalogAssetBatch;
use App\Models\CatalogAssetBatchItem;
use App\Models\CatalogAssetImportSource;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CatalogAssetReviewQuery
{
    public function __construct(private ReviewCatalogAssets $reviews, private CatalogAssetReviewFiles $files, private CatalogAssetTargets $targets, private CatalogAssetSources $sources, private CatalogAssetMatcher $matcher) {}

    public function items(User $actor, string $batchId, int $version, array $filters): Builder
    {
        $batch = $this->reviews->owned($actor, $batchId);
        $this->checkVersion($batch, $version);
        Validator::make(['filters' => $filters], ['filters' => ['array:query,status,included,apply_status'], 'filters.apply_status' => ['sometimes', 'nullable', Rule::in(['pending', 'processing', 'applied', 'unchanged', 'failed', 'stale', 'skipped'])], 'filters.query' => ['sometimes', 'string', 'max:500'], 'filters.status' => ['sometimes', 'nullable', Rule::in(['ready', 'existing', 'missing', 'ambiguous', 'invalid', 'suggestion'])], 'filters.included' => ['sometimes', 'nullable', 'boolean']])->validate();
        $query = CatalogAssetBatchItem::query()->where('batch_id', $batchId)
            ->whereHas('batch', fn ($q) => $q->where('actor_id', $actor->id)->where('preview_version', $version));
        if (($filters['apply_status'] ?? '') !== '') {
            $query->where('apply_status', $filters['apply_status']);
        }
        if (($filters['status'] ?? '') !== '') {
            $query->where('match_status', $filters['status']);
        }
        if (($filters['included'] ?? null) !== null && ($filters['included'] ?? null) !== '') {
            $query->where('included', (bool) $filters['included']);
        }
        $terms = preg_split('/\s+/u', trim($filters['query'] ?? ''), flags: PREG_SPLIT_NO_EMPTY);
        if ($terms === false || count($terms) > 20) {
            throw ValidationException::withMessages(['reviewSearch' => 'Use valid text and at most20 fragments.']);
        }
        foreach ($terms as $term) {
            $query->where(function (Builder $q) use ($term): void {
                foreach (['title', 'code', 'identifier', 'filename'] as $key) {
                    $expression = DB::getDriverName() === 'mysql' ? "JSON_UNQUOTE(JSON_EXTRACT(snapshot, '$.{$key}'))" : "json_extract(snapshot, '$.{$key}')";
                    $expression = 'LOWER('.$expression.')'.(DB::getDriverName() === 'mysql' ? ' COLLATE utf8mb4_0900_bin' : ' COLLATE BINARY');
                    $q->orWhereRaw('INSTR('.$expression.', ?) > 0', [mb_strtolower($term)]);
                }
            });
        }

        return $query->orderBy('position')->orderBy('id');
    }

    public function neighbor(User $actor, string $batchId, int $version, ?int $itemId, string $direction, array $filters, int $pageSize): ?array
    {
        Validator::make(compact('direction', 'pageSize'), ['direction' => [Rule::in(['previous', 'next'])], 'pageSize' => ['integer', 'min:1', 'max:500']])->validate();
        $query = $this->items($actor, $batchId, $version, $filters);
        $current = $itemId === null ? null : (clone $query)->find($itemId);
        $next = clone $query;
        if ($current !== null) {
            $next->where('position', $direction === 'next' ? '>' : '<', $current->position);
        }
        if ($direction === 'previous') {
            $next->reorder()->orderByDesc('position')->orderByDesc('id');
        }
        $row = $next->first();
        if ($row === null) {
            return null;
        }

        return $this->position($actor, $batchId, $version, $row->id, $filters, $pageSize);
    }

    public function position(User $actor, string $batchId, int $version, int $itemId, array $filters, int $pageSize): ?array
    {
        $query = $this->items($actor, $batchId, $version, $filters);
        $row = (clone $query)->find($itemId);
        if ($row === null) {
            return null;
        }
        $position = (clone $query)->where('position', '<', $row->position)->count() + 1;

        return ['item_id' => $row->id, 'position' => $position, 'total' => (clone $query)->count(), 'page' => intdiv($position - 1, $pageSize) + 1];
    }

    public function candidates(User $actor, string $batchId, int $version, int $itemId, int $page, string $search = ''): array
    {
        $batch = $this->reviews->owned($actor, $batchId);
        $this->checkVersion($batch, $version);
        $item = $batch->items()->findOrFail($itemId);
        $evidence = $this->reviews->reviewEvidence($batch);
        $matches = $batch->operation === 'one_image' ? array_keys(array_filter($evidence['entries'], fn ($e) => $e['status'] === 'valid')) : iterator_to_array($this->matcher->candidateIds($evidence['index'], $item->snapshot['identifier'] ?? '', $batch->recipe['parameters']), false);

        Validator::make(compact('search'), ['search' => ['string', 'max:250']])->validate();
        if ($search !== '') {
            $matches = array_values(array_filter($matches, fn ($id): bool => str_contains(mb_strtolower($evidence['entries'][$id]['name']), mb_strtolower($search))));
        }

        return $this->candidatePage($evidence['entries'], $matches, $batch->source_id, $batch->source_version, $page);
    }

    public function sourceCandidates(User $actor, string $scopeToken, string $sourceId, int $version, int $page, string $search = ''): array
    {
        $this->reviews->authorize($actor);
        $scope = $this->files->openLaunch($actor, $scopeToken);
        $this->targets->eligibleQuery($actor, $scope['target_key'], $scope['owner_context']);
        $source = $this->scopedSources($actor, $scope)->findOrFail($sourceId);
        Validator::make(compact('search'), ['search' => ['string', 'max:250']])->validate();
        $entries = array_column($this->sources->manifest($actor, $source->id, $version)['entries'], null, 'entry_id');
        $ids = array_keys(array_filter($entries, fn ($e) => $e['status'] === 'valid' && ($search === '' || str_contains(mb_strtolower($e['name']), mb_strtolower($search)))));

        return $this->candidatePage($entries, $ids, $source->id, $version, $page);
    }

    private function candidatePage(array $entries, array $ids, string $sourceId, int $version, int $page): array
    {
        $last = max(1, (int) ceil(count($ids) / 24));
        $page = max(1, min($last, $page));
        $result = [];
        foreach (array_slice($ids, ($page - 1) * 24, 24) as $id) {
            $entry = $entries[$id];
            $result[] = ['choice' => ['source_id' => $sourceId, 'scan_version' => $version, 'entry_id' => $id, 'sha256' => $entry['sha256']], 'filename' => $entry['name'], 'relative_path' => $entry['relative_path'], 'width' => $entry['width'], 'height' => $entry['height'], 'url' => URL::temporarySignedRoute('catalog.images.source-preview', now()->addMinutes(10), ['source' => $sourceId, 'version' => $version, 'entry' => $id], false)];
        }

        return ['entries' => $result, 'total' => count($ids), 'page' => $page, 'pages' => $last];
    }

    public function fieldOptions(User $actor, string $scopeToken, string $query): array
    {
        $this->reviews->authorize($actor);
        $scope = $this->files->openLaunch($actor, $scopeToken);
        if ($scope['target_key'] !== 'product.main_image' || mb_strlen($query) > 100) {
            return [];
        }
        $eligible = $this->targets->eligibleQuery($actor, $scope['target_key'], $scope['owner_context'])->whereKey($scope['record_ids']);
        $products = DB::query()->fromSub($eligible->select('products.id', 'products.profile_fields')->toBase(), 'review_products');
        if (DB::getDriverName() === 'mysql') {
            $products->crossJoin(DB::raw('JSON_TABLE(JSON_KEYS(review_products.profile_fields), \'$[*]\' COLUMNS (field_key VARCHAR(100) PATH \'$\')) AS review_fields'));
            $key = 'review_fields.field_key COLLATE utf8mb4_0900_bin';
        } else {
            $products->crossJoin(DB::raw('json_each(review_products.profile_fields) AS review_fields'));
            $key = 'CAST(review_fields.key AS TEXT) COLLATE BINARY';
        }
        $observed = $products->selectRaw($key.' AS field_key')->distinct()->whereRaw('INSTR(LOWER('.$key.'), ?) > 0', [mb_strtolower($query)])->limit(30)->pluck('field_key')->all();
        $keys = array_values(array_unique(array_filter([...ProductFields::mappedKeys(), 'product_pic', ...$observed], fn ($k) => ProductFields::validKey($k) && ($query === '' || str_contains(mb_strtolower($k), mb_strtolower($query)))), SORT_STRING));
        sort($keys, SORT_STRING);
        $keys = array_slice($keys, 0, 30);

        return array_combine($keys, $keys);
    }

    public function sourceOptions(User $actor, string $scopeToken, string $query = ''): array
    {
        $this->reviews->authorize($actor);
        $scope = $this->files->openLaunch($actor, $scopeToken);
        Validator::make(compact('query'), ['query' => ['string', 'max:100']])->validate();
        $options = [];
        foreach (config('catalog-assets.sources', []) as $key => $source) {
            if ($query === '' || str_contains(mb_strtolower($source['label']), mb_strtolower($query))) {
                $options['alias:'.$key] = $source['label'].' · server folder';
            }
        }
        $q = $this->scopedSources($actor, $scope)->orderByDesc('created_at')->orderBy('id');
        if ($query !== '') {
            $label = DB::getDriverName() === 'mysql' ? "JSON_UNQUOTE(JSON_EXTRACT(source, '$.label'))" : "json_extract(source, '$.label')";
            $q->where(fn ($q) => $q->whereRaw('INSTR(LOWER(id), ?) > 0', [mb_strtolower($query)])->orWhereRaw('INSTR(LOWER('.$label.'), ?) > 0', [mb_strtolower($query)]));
        }
        foreach ($q->limit(30)->get(['id', 'status', 'source->label as source_label']) as $source) {
            $options['source:'.$source->id] = ($source->source_label ?? 'Image source').' · '.$source->status.' · '.substr($source->id, -6);
        }

        return array_slice($options, 0, 30, true);
    }

    public function scopedSources(User $actor, array $scope): Builder
    {
        $this->reviews->authorize($actor);
        $this->targets->eligibleQuery($actor, $scope['target_key'], $scope['owner_context']);
        $q = CatalogAssetImportSource::query()->where('actor_id', $actor->id)->where('source->target_key', $scope['target_key'])->whereRaw((DB::getDriverName() === 'mysql' ? "JSON_LENGTH(JSON_EXTRACT(source, '$.owner_context'))" : "(SELECT COUNT(*) FROM json_each(json_extract(source, '$.owner_context')))").' = ?', [count($scope['owner_context'])]);
        foreach ($scope['owner_context'] as $key => $value) {
            $q->where('source->owner_context->'.$key, $value);
        }

        return $q;
    }

    public function eligibleApplyItems(User $actor, string $batchId, int $version, array $filters = []): Builder
    {
        return $this->items($actor, $batchId, $version, $filters)->where('included', true)->where('match_status', 'ready')->whereNull('acceptance_id');
    }

    public function applyProgress(User $actor, string $batchId, ?string $acceptanceId = null): array
    {
        $batch = $this->reviews->owned($actor, $batchId);
        $acceptanceId ??= $batch->active_acceptance_id ?? ($batch->recipe['last_acceptance_id'] ?? null);
        $rows = $batch->items()->whereNotNull('acceptance_id')->where('acceptance_id', $acceptanceId)->orderBy('position')->orderBy('id')->get();
        abort_if($acceptanceId !== null && $rows->isEmpty(), 404);
        $first = $rows->first(fn ($row) => in_array($row->apply_status, ['pending', 'processing'], true));
        $active = $acceptanceId !== null && $batch->active_acceptance_id === $acceptanceId;
        $busy = $active && $first?->apply_status === 'processing' && $first->processing_expires_at?->isFuture();
        $recovery = $active && $first?->apply_status === 'processing' && ! $busy;
        $intent = $batch->pending_apply_intent;

        return ['batch_id' => $batch->id, 'preview_version' => $batch->preview_version, 'acceptance_id' => $acceptanceId, 'active_acceptance_id' => $batch->active_acceptance_id,
            'status' => $batch->status, 'counts' => $rows->countBy('apply_status')->all(), 'total' => $rows->count(),
            'next_step_id' => $active && $first?->apply_status === 'pending' ? $first->request_step_id : null, 'busy' => (bool) $busy, 'recovery_required' => (bool) $recovery,
            'retryable' => $rows->contains(fn ($row) => $row->apply_status === 'failed' && ($row->snapshot['apply_result']['retryable'] ?? false) === true),
            'pending_intent' => $intent === null ? null : ['id' => $intent['id'], 'preview_version' => $intent['preview_version'], 'item_ids' => $intent['item_ids']],
            'review_total' => $batch->scope_count, 'review_complete' => $batch->build_position === $batch->scope_count, 'review_expired' => ! $batch->expires_at?->isFuture(),
            'accepted' => $batch->items()->whereNotNull('acceptance_id')->exists()];
    }

    private function checkVersion(CatalogAssetBatch $batch, int $version): void
    {
        if ($batch->preview_version !== $version) {
            throw ValidationException::withMessages(['review' => 'This review changed. Refresh its current version.']);
        }
    }
}
