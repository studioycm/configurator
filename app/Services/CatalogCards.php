<?php

namespace App\Services;

use App\Models\Group;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class CatalogCards
{
    public function __construct(private CatalogSnapshots $snapshots, private CatalogSnapshotMatcher $matcher, private CatalogCardDisplay $display, private CatalogPresentation $presentation) {}

    /** @param array<string, mixed> $criteria @return array<string, mixed> */
    public function get(int $groupId, array $criteria, string $revision, string $requestId, ?string $expectedDisplayVersion = null): array
    {
        Validator::make(compact('revision', 'requestId', 'expectedDisplayVersion'), [
            'revision' => ['required', 'string', 'regex:/^[1-9][0-9]{0,19}$/'],
            'requestId' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9:-]+$/'],
            'expectedDisplayVersion' => ['nullable', 'string', 'regex:/^(?:defaults:v1|[0-9A-HJKMNP-TV-Z]{26})$/D'],
        ])->validate();
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $read = DB::transaction(function () use ($groupId, $criteria, $revision, $requestId, $expectedDisplayVersion): ?array {
                $current = $this->snapshots->revision($groupId);
                $shared = $this->presentation->current();
                $response = ['requestId' => $requestId, 'revision' => $current, 'displayVersion' => $shared['version'], 'status' => 'refresh_required', 'total' => null, 'htmlChunks' => [], 'propertiesNotice' => null];
                if ($current !== $revision || ($expectedDisplayVersion !== null && $shared['version'] !== $expectedDisplayVersion)) {
                    return ['response' => $response];
                }
                $snapshot = $this->snapshots->cached($groupId, $current);
                if ($snapshot === null) {
                    return null;
                }
                $group = Group::query()->findOrFail($groupId);
                $available = $this->presentation->resolveCategoryIds($shared['settings'], [$group]);
                $response['presentation'] = $this->presentation->effective($group, $shared['settings'], $available);
                $ids = $this->matcher->ids($snapshot, $criteria);
                $total = count($ids);
                $settings = CatalogPolicy::resultSettings($snapshot->data['settings']);
                $maximum = $settings['max_results'];
                $response['total'] = $total;
                $response['status'] = $total === 0 ? 'empty' : ($maximum !== 'all' && $total > $maximum ? 'above_threshold' : 'ready');
                if ($response['status'] !== 'ready') {
                    return ['response' => $response];
                }
                $products = Product::query()->where('group_id', $groupId)->where('is_active', true)->whereIn('id', $ids)->orderBy('product_code')->orderBy('id')->get(['id', 'product_code', 'profile_fields']);

                return ['response' => $response, 'products' => $products, 'group' => $group, 'snapshot' => $snapshot, 'settings' => $settings];
            });
            if ($read !== null) {
                if (isset($read['products'])) {
                    $snapshot = $read['snapshot']->data;
                    $group = $read['group'];
                    $mainGroup = $snapshot['cardGroup']['mainName'] === null ? null : new Group(['name' => $snapshot['cardGroup']['mainName']]);
                    $appearance = $read['settings'];
                    $labels = array_column($snapshot['fields'], 'label', 'key');
                    $display = $this->display->compare($read['products'], $appearance['card_properties'], $labels, $appearance['card_only_differences']);
                    $read['response']['propertiesNotice'] = match ($display['noticeReason']) {
                        'single_match' => __('catalog.messages.no_differences_single_match'),
                        'shared_properties' => __('catalog.messages.these_products_share_the_configured_properties'),
                        'no_populated_properties' => __('catalog.messages.no_populated_card_properties_are_configured_for_these_products'),
                        default => null,
                    };
                    foreach ($read['products']->chunk(24) as $products) {
                        $read['response']['htmlChunks'][] = view('components.catalog.card-chunk', ['products' => $products, 'group' => $group, 'mainGroup' => $mainGroup, 'fields' => $display['fields'], 'appearance' => $appearance, 'presentation' => $read['response']['presentation']])->render();
                    }
                }

                return $read['response'];
            }
            // The read transaction has ended before any cache-lock wait or rebuild.
            if ($attempt === 0) {
                $this->snapshots->get($groupId);
            }
        }
        throw new ServiceUnavailableHttpException(2, __('catalog.messages.the_catalog_could_not_be_prepared_please_retry'));
    }
}
