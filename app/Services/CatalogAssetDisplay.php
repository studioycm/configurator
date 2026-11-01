<?php

namespace App\Services;

use App\Models\CatalogAsset;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Validation\ValidationException;
use JsonException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @phpstan-type DisplayImage array{id: int, url: string, thumbnail_url: string, width: int, height: int, mime_type: string, filename: string, bytes: int, warnings: list<string>}
 */
class CatalogAssetDisplay
{
    /**
     * @param  list<int>  $assetIds
     * @return array<int, DisplayImage>
     */
    public function forIds(array $assetIds): array
    {
        if (array_filter($assetIds, fn (mixed $id): bool => ! is_int($id) || $id < 1) !== []) {
            throw ValidationException::withMessages(['image.display' => 'Choose valid saved image identities.']);
        }
        if ($assetIds === []) {
            return [];
        }
        $baseUrl = config('filesystems.disks.catalog-assets.url');
        if (! is_string($baseUrl) || $baseUrl === '') {
            return [];
        }
        $asset = new CatalogAsset;
        $media = new Media;
        if ($media->getConnectionName() === null) {
            $media->setConnection($asset->getConnectionName());
        }
        $connection = $asset->getConnection();
        if ($media->getConnection() !== $connection) {
            return [];
        }
        $images = [];
        foreach (array_chunk(array_values(array_unique($assetIds)), 500) as $chunk) {
            $rows = $connection->table($asset->getTable().' as assets')
                ->join($media->getTable().' as winner', function (JoinClause $join): void {
                    $join->on('winner.id', '=', 'assets.original_media_id')->on('winner.model_id', '=', 'assets.id');
                })
                ->whereIn('assets.id', $chunk)
                ->where('assets.preparation_status', 'ready')->whereNotNull('assets.prepared_at')->whereNotNull('assets.verified_metadata')
                ->where('winner.model_type', $asset->getMorphClass())->where('winner.collection_name', 'original')
                ->where('winner.disk', 'catalog-assets')->where('winner.conversions_disk', 'catalog-assets')
                ->get(['assets.id', 'assets.sha256', 'assets.verified_metadata', 'winner.id as media_id',
                    'winner.file_name', 'winner.mime_type', 'winner.size', 'winner.generated_conversions']);
            foreach ($rows as $row) {
                $metadata = $this->decode($row->verified_metadata);
                $mime = $metadata['mime_type'] ?? null;
                $extension = match ($mime) {
                    'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', default => null,
                };
                if ($extension === null || $mime !== $row->mime_type || ! is_int($metadata['bytes'] ?? null) || $metadata['bytes'] < 1
                    || $metadata['bytes'] > config('catalog-assets.image.max_bytes')
                    || (int) $row->size !== $metadata['bytes'] || ! is_int($metadata['width'] ?? null) || $metadata['width'] < 1
                    || ! is_int($metadata['height'] ?? null) || $metadata['height'] < 1
                    || config('catalog-assets.image.max_pixels') < $metadata['width'] * $metadata['height']
                    || ! is_string($metadata['sha256'] ?? null) || preg_match('/^[a-f0-9]{64}$/D', $metadata['sha256']) !== 1
                    || ! hash_equals($row->sha256, $metadata['sha256']) || ! is_string($row->file_name) || strlen($row->file_name) > 255
                    || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*\.'.preg_quote($extension, '/').'$/D', $row->file_name) !== 1) {
                    continue;
                }
                $original = rtrim($baseUrl, '/').'/'.$row->media_id.'/'.rawurlencode($row->file_name);
                $generated = $this->decode($row->generated_conversions);
                $conversions = is_array($metadata['conversions'] ?? null) ? $metadata['conversions'] : [];
                $thumbnail = ($conversions['image-160'] ?? false) === true && ($generated['image-160'] ?? false) === true
                    ? rtrim($baseUrl, '/').'/'.$row->media_id.'/conversions/'.rawurlencode(pathinfo($row->file_name, PATHINFO_FILENAME).'-image-160.jpg') : $original;
                $warnings = is_array($metadata['warnings'] ?? null) ? $metadata['warnings'] : [];
                $images[(int) $row->id] = ['id' => (int) $row->id, 'url' => $original, 'thumbnail_url' => $thumbnail,
                    'width' => $metadata['width'], 'height' => $metadata['height'], 'mime_type' => $mime, 'filename' => $row->file_name,
                    'bytes' => $metadata['bytes'], 'warnings' => array_values(array_unique(array_filter($warnings, fn (mixed $warning): bool => $warning === 'conversion_incomplete')))];
            }

        }

        return $images;
    }

    /** @return array<string, mixed> */
    private function decode(mixed $json): array
    {
        if (! is_string($json)) {
            return [];
        }
        try {
            $value = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($value) ? $value : [];
    }
}
