<?php

namespace App\Services;

use App\Models\Group;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class CatalogCards
{
    public function __construct(private CatalogSnapshots $snapshots, private CatalogSnapshotMatcher $matcher) {}

    /** @param array<string, mixed> $criteria @return array<string, mixed> */
    public function get(int $groupId, array $criteria, string $revision, string $requestId): array
    {
        Validator::make(compact('revision', 'requestId'), [
            'revision' => ['required', 'string', 'regex:/^[1-9][0-9]{0,19}$/'],
            'requestId' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9:-]+$/'],
        ])->validate();
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $read = DB::transaction(function () use ($groupId, $criteria, $revision, $requestId): ?array {
                $current = $this->snapshots->revision($groupId);
                $response = ['requestId' => $requestId, 'revision' => $current, 'status' => 'refresh_required', 'total' => null, 'htmlChunks' => []];
                if ($current !== $revision) {
                    return ['response' => $response];
                }
                $snapshot = $this->snapshots->cached($groupId, $current);
                if ($snapshot === null) {
                    return null;
                }
                $ids = $this->matcher->ids($snapshot, $criteria);
                $total = count($ids);
                $maximum = $snapshot->data['settings']['max_results'];
                $response['total'] = $total;
                $response['status'] = $total === 0 ? 'empty' : ($maximum !== 'all' && $total > $maximum ? 'above_threshold' : 'ready');
                if ($response['status'] !== 'ready') {
                    return ['response' => $response];
                }
                $products = Product::query()->where('group_id', $groupId)->whereIn('id', $ids)->orderBy('product_code')->orderBy('id')->get(['id', 'product_code', 'properties']);

                return ['response' => $response, 'products' => $products, 'snapshot' => $snapshot];
            });
            if ($read !== null) {
                if (isset($read['products'])) {
                    $snapshot = $read['snapshot']->data;
                    $group = new Group(['name' => $snapshot['cardGroup']['name']]);
                    $mainGroup = $snapshot['cardGroup']['mainName'] === null ? null : new Group(['name' => $snapshot['cardGroup']['mainName']]);
                    foreach ($read['products']->chunk(24) as $products) {
                        $read['response']['htmlChunks'][] = view('components.catalog.card-chunk', ['products' => $products, 'group' => $group, 'mainGroup' => $mainGroup, 'propertyKeys' => $snapshot['settings']['card_properties']])->render();
                    }
                }

                return $read['response'];
            }
            // The read transaction has ended before any cache-lock wait or rebuild.
            if ($attempt === 0) {
                $this->snapshots->get($groupId);
            }
        }
        throw new ServiceUnavailableHttpException(2, 'The catalog could not be prepared. Please retry.');
    }
}
