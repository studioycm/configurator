<?php

namespace App\Services;

use App\DTO\CatalogAssetChange;
use App\Models\CatalogAsset;
use App\Models\Group;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

class CatalogAssetAssignments
{
    public function __construct(private CatalogAssetTargets $targets) {}

    public static function fingerprint(Model $owner): string
    {
        return hash('sha256', json_encode($owner->getAttributes(), JSON_THROW_ON_ERROR));
    }

    public function applyToLockedRecord(Model $owner, CatalogAssetChange $change): bool
    {
        abort_unless(config('catalog-assets.enabled'), 404);
        $targetKey = match (true) {
            $owner instanceof Product => 'product.main_image',
            $owner instanceof Group => 'group.main_image',
            default => throw ValidationException::withMessages(['image.target' => 'This record does not support a main image.']),
        };
        if ($owner->getConnection()->transactionLevel() === 0) {
            throw new LogicException('Main-image assignment requires the owner writer transaction.');
        }
        if ($change->operation === 'keep') {
            return false;
        }
        $assetId = $change->operation === 'set' ? $this->validatedAsset($targetKey, $change->assetId)->id : null;
        if (($owner->main_image_id === null ? null : (int) $owner->main_image_id) === $assetId) {
            return false;
        }
        $owner->main_image_id = $assetId;

        return true;
    }

    /** Validates immutable ready metadata without reading files or acquiring catalog locks. */
    public function validatedAsset(string $targetKey, int $assetId): CatalogAsset
    {
        $policy = $this->targets->profile($targetKey);
        $asset = CatalogAsset::query()->with('originalMedia')->find($assetId);
        $this->require($asset !== null && $asset->preparation_status === 'ready' && $asset->prepared_at !== null && $asset->original_media_id !== null);
        $media = $asset->originalMedia;
        $source = $asset->source;
        $metadata = $asset->verified_metadata;
        $entry = $source['entry'] ?? null;
        $this->require(is_array($source) && ($source['target_key'] ?? null) === $targetKey && is_array($entry) && is_array($metadata)
            && $media !== null && (int) $media->id === $asset->original_media_id
            && $media->model_type === $asset->getMorphClass() && (int) $media->model_id === $asset->id
            && $media->collection_name === 'original' && $media->disk === 'catalog-assets' && $media->conversions_disk === 'catalog-assets');
        $this->require(is_string($metadata['sha256'] ?? null) && preg_match('/^[a-f0-9]{64}$/D', $metadata['sha256']) === 1
            && hash_equals($asset->sha256, $metadata['sha256']) && $media->mime_type === ($metadata['mime_type'] ?? null)
            && in_array($metadata['mime_type'] ?? null, $policy['mime_types'], true)
            && is_int($metadata['bytes'] ?? null) && $metadata['bytes'] > 0 && $metadata['bytes'] <= $policy['max_bytes']
            && (int) $media->size === $metadata['bytes'] && is_int($metadata['width'] ?? null) && is_int($metadata['height'] ?? null)
            && $metadata['width'] > 0 && $metadata['height'] > 0 && $policy['max_pixels'] >= $metadata['width'] * $metadata['height']);
        foreach (['sha256', 'bytes', 'mime_type', 'width', 'height'] as $key) {
            $this->require(($entry[$key] ?? null) === $metadata[$key]);
        }
        $properties = $media->custom_properties;
        $this->require(is_array($properties) && count($properties) === 6 && is_string($properties['preparation_token'] ?? null) && Str::isUlid($properties['preparation_token'])
            && ($properties['asset_id'] ?? null) === $asset->id && ($properties['sha256'] ?? null) === $asset->sha256);
        foreach (['source_id', 'scan_version'] as $key) {
            $this->require(($source[$key] ?? null) !== null && ($source[$key] ?? null) === ($metadata[$key] ?? null) && ($source[$key] ?? null) === ($properties[$key] ?? null));
        }
        $this->require(is_string($entry['entry_id'] ?? null) && ($entry['entry_id'] ?? null) === ($metadata['entry_id'] ?? null) && $entry['entry_id'] === ($properties['entry_id'] ?? null));

        return $asset;
    }

    private function require(bool $condition): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['image.asset' => 'The prepared image is unavailable or its ready metadata is invalid.']);
        }
    }
}
